import { env, exports } from "cloudflare:workers";
import { beforeAll, describe, expect, it } from "vitest";

const bootstrapToken = "test-bootstrap-token-0123456789abcdef";
let manager;
let employee;
let guardianId;
let variantId;

async function send(path, { method = "GET", body, session = manager, headers: extraHeaders = {} } = {}) {
    const headers = new Headers(extraHeaders);
    if (session?.cookie) headers.set("cookie", session.cookie);
    if (session?.csrfToken && method !== "GET") headers.set("x-csrf-token", session.csrfToken);
    if (body !== undefined) headers.set("content-type", "application/json");
    const response = await exports.default.fetch(new Request(`https://workshop.test${path}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
    }), env);
    return { response, data: await response.json() };
}

async function login(loginName, password) {
    const { response } = await send("/api/login", {
        method: "POST",
        session: null,
        body: { login: loginName, password },
    });
    expect(response.status).toBe(200);
    const cookie = response.headers.get("set-cookie").split(";")[0];
    const { data } = await send("/api/me", { session: { cookie } });
    return { cookie, csrfToken: data.csrfToken, permissions: data.permissions };
}

async function uploadEvidence(paymentId, bytes, { session = manager, filename = "receipt.png", type = "image/png" } = {}) {
    const response = await exports.default.fetch(new Request(
        `https://workshop.test/api/payments/${paymentId}/evidence`,
        {
            method: "POST",
            headers: {
                cookie: session.cookie,
                "x-csrf-token": session.csrfToken,
                "x-original-filename": encodeURIComponent(filename),
                "content-type": type,
            },
            body: bytes,
        },
    ), env);
    return { response, data: await response.json() };
}

async function createOrder({ type = "custom", price = "100.00", quantity = 1 } = {}) {
    const { response, data } = await send("/api/orders", {
        method: "POST",
        body: {
            guardian_id: guardianId,
            type,
            items: [{
                description: type === "ready" ? "قميص مدرسي" : "طلب تفصيل",
                inventory_variant_id: type === "ready" ? variantId : null,
                quantity,
                unit_price: price,
                discount: "0",
            }],
        },
    });
    expect(response.status).toBe(201);
    return data;
}

beforeAll(async () => {
    const setup = await send("/api/setup", {
        method: "POST",
        session: null,
        headers: { "x-bootstrap-token": bootstrapToken },
        body: {
            name: "مدير اختبار",
            username: "manager-test",
            password: "Synthetic-password-123",
        },
    });
    expect(setup.response.status).toBe(201);
    manager = await login("manager-test", "Synthetic-password-123");

    const employeeCreate = await send("/api/users", {
        method: "POST",
        body: {
            name: "موظف اختبار",
            username: "employee-test",
            role: "employee",
            is_active: true,
            password: "Synthetic-password-456",
            password_confirmation: "Synthetic-password-456",
        },
    });
    expect(employeeCreate.response.status).toBe(201);
    employee = await login("employee-test", "Synthetic-password-456");

    const now = new Date().toISOString();
    const guardian = await env.DB.prepare(
        "INSERT INTO guardians (name, phone, created_at, updated_at) VALUES (?, ?, ?, ?)",
    ).bind("عميل اختباري", "0000000000", now, now).run();
    guardianId = guardian.meta.last_row_id;
    const variant = await env.DB.prepare(
        `INSERT INTO inventory_variants
            (garment_type_id, size, attributes_key, is_active, created_at, updated_at)
         SELECT id, 'M', 'test-variant', 1, ?, ? FROM garment_types ORDER BY id LIMIT 1`,
    ).bind(now, now).run();
    variantId = variant.meta.last_row_id;
    await env.DB.prepare(
        "INSERT INTO inventory_balances (inventory_variant_id, quantity_available, created_at, updated_at) VALUES (?, 20, ?, ?)",
    ).bind(variantId, now, now).run();
});

describe("role boundaries", () => {
    it("does not allow an employee to record a ready-stock sale without inventory.sell", async () => {
        const permission = await env.DB.prepare("SELECT id FROM permissions WHERE name = 'inventory.sell'").first();
        const role = await env.DB.prepare("SELECT id FROM roles WHERE name = 'employee'").first();
        await env.DB.prepare(
            "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?",
        ).bind(role.id, permission.id).run();
        const before = await env.DB.prepare("SELECT count(*) AS total FROM orders").first();

        const { response, data } = await send("/api/orders", {
            method: "POST",
            session: employee,
            body: {
                guardian_id: guardianId,
                type: "ready",
                items: [{
                    description: "صنف جاهز",
                    inventory_variant_id: variantId,
                    quantity: 1,
                    unit_price: "50.00",
                }],
            },
        });

        expect(response.status).toBe(403);
        expect(data.error).toBe("forbidden");
        const after = await env.DB.prepare("SELECT count(*) AS total FROM orders").first();
        expect(after.total).toBe(before.total);
    });

    it("does not expose order values in the operational report", async () => {
        const order = await createOrder({ price: "9876.54" });
        const today = new Date().toISOString().slice(0, 10);
        const { response, data } = await send(
            `/api/reports?from=${today}&to=${today}`,
            { session: employee },
        );

        expect(response.status).toBe(200);
        expect(data.financial).toBeNull();
        expect(data.orders[0].approved_total).toBeNull();

        const listed = await send("/api/orders", { session: employee });
        expect(listed.response.status).toBe(200);
        expect(listed.data.data.find((record) => record.id === order.id)).not.toHaveProperty("approved_total");

        const detail = await send(`/api/orders/${order.id}`, { session: employee });
        expect(detail.response.status).toBe(200);
        expect(detail.data).not.toHaveProperty("approved_total");
        expect(detail.data).not.toHaveProperty("financial_summary");
        expect(detail.data.items[0]).not.toHaveProperty("unit_price");
        expect(detail.data.delivery_eligible).toBe(false);
    });

    it("keeps cancellation and refund approval manager-only", async () => {
        const order = await createOrder();
        const cancel = await send(`/api/orders/${order.id}/cancel`, {
            method: "POST",
            session: employee,
            body: { reason: "اختبار", cancellation_discount: "0" },
        });
        const refund = await send(`/api/orders/${order.id}/refunds`, {
            method: "POST",
            session: employee,
            body: { approved_amount: "1.00", reason: "اختبار" },
        });

        expect(cancel.response.status).toBe(403);
        expect(refund.response.status).toBe(403);
    });
});

describe("financial and inventory invariants", () => {
    it("rejects a payment above the order total without changing the balance", async () => {
        const order = await createOrder({ price: "100.00" });
        const { response, data } = await send("/api/payments", {
            method: "POST",
            body: {
                order_id: order.id,
                amount: "100.01",
                method: "cash",
                paid_at: new Date().toISOString(),
            },
        });

        expect(response.status).toBe(409);
        expect(data.error).toBe("payment_exceeds_order_or_transaction_failed");
        const payments = await env.DB.prepare("SELECT count(*) AS total FROM payments WHERE order_id = ?")
            .bind(order.id).first();
        expect(payments.total).toBe(0);
    });

    it("records a partial refund without exceeding the approved amount", async () => {
        const order = await createOrder({ price: "100.00" });
        const payment = await send("/api/payments", {
            method: "POST",
            body: {
                order_id: order.id,
                amount: "100.00",
                method: "cash",
                paid_at: new Date().toISOString(),
            },
        });
        expect(payment.response.status).toBe(201);

        const approved = await send(`/api/orders/${order.id}/refunds`, {
            method: "POST",
            body: {
                approved_amount: "30.00",
                original_payment_id: payment.data.id,
                reason: "اختبار اصطناعي",
            },
        });
        expect(approved.response.status).toBe(201);

        const overpayment = await send(`/api/refunds/${approved.data.id}/pay`, {
            method: "POST",
            body: { amount: "30.01", method: "cash", refunded_at: new Date().toISOString() },
        });
        expect(overpayment.response.status).toBe(409);

        const paid = await send(`/api/refunds/${approved.data.id}/pay`, {
            method: "POST",
            body: { amount: "20.00", method: "cash", refunded_at: new Date().toISOString() },
        });
        expect(paid.response.status).toBe(201);
        const refund = await env.DB.prepare("SELECT refunded_amount FROM refunds WHERE id = ?")
            .bind(approved.data.id).first();
        expect(Number(refund.refunded_amount)).toBe(20);
    });

    it("rejects a ready order that exceeds available stock", async () => {
        const before = await env.DB.prepare(
            "SELECT quantity_available FROM inventory_balances WHERE inventory_variant_id = ?",
        ).bind(variantId).first();
        const { response, data } = await send("/api/orders", {
            method: "POST",
            body: {
                guardian_id: guardianId,
                type: "ready",
                items: [{
                    description: "صنف جاهز",
                    inventory_variant_id: variantId,
                    quantity: Number(before.quantity_available) + 1,
                    unit_price: "10.00",
                }],
            },
        });

        expect(response.status).toBe(409);
        expect(data.error).toBe("insufficient_stock");
        const after = await env.DB.prepare(
            "SELECT quantity_available FROM inventory_balances WHERE inventory_variant_id = ?",
        ).bind(variantId).first();
        expect(after.quantity_available).toBe(before.quantity_available);
    });

    it("does not approve a stock adjustment that would make inventory negative", async () => {
        const { response: created, data } = await send("/api/inventory/adjustments", {
            method: "POST",
            body: {
                reason: "اختبار فرق جرد",
                items: [{
                    inventory_variant_id: variantId,
                    quantity_delta: -21,
                    reason: "قيمة اصطناعية تتجاوز الرصيد",
                }],
            },
        });
        expect(created.status).toBe(201);

        const decision = await send(`/api/inventory/adjustments/${data.id}/approve`, {
            method: "POST",
            body: {},
        });
        expect(decision.response.status).toBe(409);
        expect(decision.data.error).toBe("adjustment_would_make_negative_or_failed");
        const balance = await env.DB.prepare(
            "SELECT quantity_available FROM inventory_balances WHERE inventory_variant_id = ?",
        ).bind(variantId).first();
        expect(balance.quantity_available).toBeGreaterThanOrEqual(0);
    });

    it("preserves partial tailor receipts and rejects over-dispatch and over-receipt", async () => {
        const order = await createOrder({ quantity: 3 });
        const orderItem = await env.DB.prepare("SELECT id FROM order_items WHERE order_id = ?")
            .bind(order.id).first();
        const job = await send("/api/production/jobs", {
            method: "POST",
            body: { order_item_id: orderItem.id, quantity: 3 },
        });
        expect(job.response.status).toBe(201);

        const dispatch = await send(`/api/production/jobs/${job.data.id}/dispatches`, {
            method: "POST",
            body: {
                recipient_name: "ترزي اختباري",
                sent_on: new Date().toISOString().slice(0, 10),
                quantity_sent: 2,
            },
        });
        expect(dispatch.response.status).toBe(201);
        const dispatchItem = await env.DB.prepare(
            "SELECT id FROM tailor_dispatch_items WHERE tailor_dispatch_id = ?",
        ).bind(dispatch.data.id).first();

        const overDispatch = await send(`/api/production/jobs/${job.data.id}/dispatches`, {
            method: "POST",
            body: {
                recipient_name: "ترزي اختباري",
                sent_on: new Date().toISOString().slice(0, 10),
                quantity_sent: 2,
            },
        });
        expect(overDispatch.response.status).toBe(409);

        const overReceipt = await send(`/api/production/dispatches/${dispatch.data.id}/receipts`, {
            method: "POST",
            body: {
                received_on: new Date().toISOString().slice(0, 10),
                items: [{ dispatch_item_id: dispatchItem.id, quantity_received: 3 }],
            },
        });
        expect(overReceipt.response.status).toBe(409);

        const firstReceipt = await send(`/api/production/dispatches/${dispatch.data.id}/receipts`, {
            method: "POST",
            body: {
                received_on: new Date().toISOString().slice(0, 10),
                items: [{ dispatch_item_id: dispatchItem.id, quantity_received: 1 }],
            },
        });
        expect(firstReceipt.response.status).toBe(201);
        const secondReceipt = await send(`/api/production/dispatches/${dispatch.data.id}/receipts`, {
            method: "POST",
            body: {
                received_on: new Date().toISOString().slice(0, 10),
                items: [{ dispatch_item_id: dispatchItem.id, quantity_received: 1 }],
            },
        });
        expect(secondReceipt.response.status).toBe(201);
        const total = await env.DB.prepare(
            `SELECT sum(receipt_items.quantity_received) AS total
             FROM tailor_receipt_items AS receipt_items
             JOIN tailor_dispatch_items AS dispatch_items ON dispatch_items.id = receipt_items.tailor_dispatch_item_id
             WHERE dispatch_items.id = ?`,
        ).bind(dispatchItem.id).first();
        expect(total.total).toBe(2);
    });

    it("stores transfer evidence privately and limits downloads to finance viewers", async () => {
        const order = await createOrder();
        const payment = await send("/api/payments", {
            method: "POST",
            body: {
                order_id: order.id,
                amount: "10.00",
                method: "bank_transfer",
                paid_at: new Date().toISOString(),
            },
        });
        expect(payment.response.status).toBe(201);

        const pngSignature = new Uint8Array([137, 80, 78, 71, 13, 10, 26, 10]);
        const uploaded = await uploadEvidence(payment.data.id, pngSignature);
        expect(uploaded.response.status).toBe(201);

        const duplicate = await uploadEvidence(payment.data.id, pngSignature);
        expect(duplicate.response.status).toBe(409);
        expect(duplicate.data.error).toBe("duplicate_payment_evidence");

        const employeeDownload = await exports.default.fetch(new Request(
            `https://workshop.test/api/payment-evidence/${uploaded.data.id}`,
            { headers: { cookie: employee.cookie } },
        ), env);
        expect(employeeDownload.status).toBe(403);

        const download = await exports.default.fetch(new Request(
            `https://workshop.test/api/payment-evidence/${uploaded.data.id}`,
            { headers: { cookie: manager.cookie } },
        ), env);
        expect(download.status).toBe(200);
        expect(download.headers.get("content-disposition")).toContain("receipt.png");
        expect(new Uint8Array(await download.arrayBuffer())).toEqual(pngSignature);

        const invalid = await uploadEvidence(payment.data.id, new Uint8Array([1, 2, 3]));
        expect(invalid.response.status).toBe(422);
    });
});
