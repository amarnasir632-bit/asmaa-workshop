import { calculateFinancialSummary } from "./finance.js";

const maximumAmountMinor = 999_999_999_999_999n;

function amount(value, allowZero = false) {
    const normalized = typeof value === "number" ? String(value) : value;
    if (typeof normalized !== "string" || !/^\d{1,13}(?:\.\d{1,2})?$/.test(normalized)) return null;
    const [whole, fraction = ""] = normalized.split(".");
    const minor = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0"));
    if (minor > maximumAmountMinor || (!allowZero && minor === 0n)) return null;
    return minor;
}

function asMajor(minor) {
    return `${minor / 100n}.${String(minor % 100n).padStart(2, "0")}`;
}

function positiveId(value) {
    const id = Number(value);
    return Number.isSafeInteger(id) && id > 0 ? id : null;
}

function optionalText(value, maximum) {
    if (value === undefined || value === null || value === "") return null;
    return typeof value === "string" && value.length <= maximum ? value.trim() || null : undefined;
}

function date(value) {
    if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
    const parsed = new Date(`${value}T00:00:00.000Z`);
    const now = new Date().toISOString().slice(0, 10);
    return !Number.isNaN(parsed.valueOf()) && parsed.toISOString().slice(0, 10) === value && value <= now
        ? value
        : null;
}

function dateTime(value) {
    if (typeof value !== "string" || value.length > 40) return null;
    const parsed = new Date(value);
    return !Number.isNaN(parsed.valueOf()) && parsed.valueOf() <= Date.now() ? parsed.toISOString() : null;
}

async function authorize(request, env, permission, tools, write) {
    const user = await tools.authenticatedUser(request, env);
    if (!user) return { response: tools.json({ error: "unauthenticated" }, 401) };
    if (!user.permissions.includes(permission)) return { response: tools.json({ error: "forbidden" }, 403) };
    if (write && !tools.csrfValid(request, user)) {
        return { response: tools.json({ error: "csrf_failed" }, 419) };
    }
    return { user };
}

async function body(request, tools) {
    const parsed = await tools.requestData(request);
    return parsed.error
        ? { response: tools.json({ error: parsed.error }, parsed.status) }
        : { data: parsed.data };
}

function audit(env, request, userId, action, type, subjectId, after, reason, now) {
    return env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, reason, request_id, occurred_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    ).bind(userId, action, type, subjectId, JSON.stringify(after), reason, request.headers.get("cf-ray"), now);
}

async function listExpenses(request, env, user, tools) {
    const result = await env.DB.prepare(
        `SELECT expenses.id, expenses.expense_category_id, categories.name AS category_name,
                printf('%.2f', expenses.amount) AS amount, expenses.spent_on, expenses.description,
                expenses.notes, expenses.recorded_by, users.name AS recorded_by_name
         FROM expenses
         JOIN expense_categories AS categories ON categories.id = expenses.expense_category_id
         JOIN users ON users.id = expenses.recorded_by
         WHERE (? = 1 OR expenses.recorded_by = ?)
         ORDER BY expenses.spent_on DESC, expenses.id DESC LIMIT 100`,
    ).bind(Number(user.permissions.includes("finance.view")), user.id).all();
    return tools.json({ data: result.results ?? [] });
}

async function createExpense(request, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const categoryId = positiveId(data.expense_category_id);
    const minor = amount(data.amount);
    const spentOn = date(data.spent_on);
    const description = typeof data.description === "string" ? data.description.trim() : "";
    const notes = optionalText(data.notes, 2000);
    if (!categoryId || minor === null || !spentOn || !description || description.length > 180 || notes === undefined) {
        return tools.json({ error: "invalid_expense_data" }, 422);
    }
    const category = await env.DB.prepare(
        "SELECT id FROM expense_categories WHERE id = ? AND is_active = 1",
    ).bind(categoryId).first();
    if (!category) return tools.json({ error: "expense_category_not_found" }, 422);
    const now = new Date().toISOString();
    try {
        const result = await env.DB.batch([
            env.DB.prepare(
                `INSERT INTO expenses
                    (expense_category_id, amount, spent_on, description, notes, recorded_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
            ).bind(categoryId, asMajor(minor), spentOn, description, notes, user.id, now, now),
            env.DB.prepare(
                `INSERT INTO audit_logs
                    (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
                 VALUES (?, 'expense_recorded', 'App\\\\Models\\\\Expense', last_insert_rowid(), ?, ?, ?)`,
            ).bind(user.id, JSON.stringify({ expense_category_id: categoryId, amount: asMajor(minor), spent_on: spentOn, description }), request.headers.get("cf-ray"), now),
        ]);
        return tools.json({ id: result[0].meta.last_row_id, expense_category_id: categoryId, amount: asMajor(minor), spent_on: spentOn, description }, 201);
    } catch (error) {
        console.error("D1 expense transaction failed", error instanceof Error ? error.message : "unknown");
        return tools.json({ error: "expense_transaction_failed" }, 409);
    }
}

async function cancelOrder(request, orderId, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const reason = parsed.data.reason;
    const discount = amount(parsed.data.cancellation_discount, true);
    if (typeof reason !== "string" || !reason.trim() || reason.trim().length > 3000 || discount === null) {
        return tools.json({ error: "invalid_cancellation_data" }, 422);
    }
    const order = await env.DB.prepare(
        `SELECT id, number, type, operational_status, approved_total
         FROM orders WHERE id = ? AND deleted_at IS NULL`,
    ).bind(orderId).first();
    if (!order) return tools.json({ error: "order_not_found" }, 404);
    if (order.operational_status === "cancelled") return tools.json({ error: "order_already_cancelled" }, 409);
    if (discount > (amount(String(order.approved_total), true) ?? 0n)) {
        return tools.json({ error: "cancellation_discount_exceeds_total" }, 422);
    }
    const summary = await calculateFinancialSummary(order, env);
    const refundDue = BigInt(summary.net_paid.replace(".", "")) > discount
        ? BigInt(summary.net_paid.replace(".", "")) - discount
        : 0n;
    const stage = await env.DB.prepare(
        `SELECT jobs.stage FROM production_jobs AS jobs
         JOIN order_items ON order_items.id = jobs.order_item_id
         WHERE order_items.order_id = ? ORDER BY jobs.id DESC LIMIT 1`,
    ).bind(orderId).first();
    const cancellationStage = stage?.stage ?? order.operational_status;
    const now = new Date().toISOString();
    const statements = [
        env.DB.prepare(
            `INSERT INTO cancellations
                (order_id, production_stage_snapshot, reason, cancellation_discount, refund_due,
                 approved_by, approved_at, created_at, updated_at)
             SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?
             WHERE EXISTS (SELECT 1 FROM orders WHERE id = ? AND operational_status != 'cancelled')`,
        ).bind(orderId, cancellationStage, reason.trim(), asMajor(discount), asMajor(refundDue), user.id, now, now, now, orderId),
        env.DB.prepare(
            `UPDATE orders SET operational_status = 'cancelled', cancelled_at = ?, updated_at = ?
             WHERE id = ? AND operational_status != 'cancelled' AND changes() > 0`,
        ).bind(now, now, orderId),
    ];

    if (order.type === "ready") {
        const items = await env.DB.prepare(
            `SELECT order_items.id, order_items.quantity, order_items.inventory_variant_id,
                    max(0, -coalesce((SELECT sum(quantity_change) FROM inventory_movements
                        WHERE inventory_movements.order_item_id = order_items.id
                          AND movement_type = 'ready_sale'), 0)) AS deducted,
                    coalesce((SELECT sum(quantity_delivered) FROM delivery_items
                        WHERE delivery_items.order_item_id = order_items.id), 0) AS delivered
             FROM order_items WHERE order_items.order_id = ?`,
        ).bind(orderId).all();
        for (const item of items.results ?? []) {
            if (!item.inventory_variant_id) continue;
            const release = Math.min(
                Math.max(0, Number(item.quantity) - Number(item.delivered)),
                Number(item.deducted),
            );
            if (!release) continue;
            statements.push(env.DB.prepare(
                `INSERT INTO inventory_balances (inventory_variant_id, quantity_available, created_at, updated_at)
                 SELECT ?, ?, ?, ? WHERE changes() > 0
                 ON CONFLICT (inventory_variant_id) DO UPDATE
                 SET quantity_available = inventory_balances.quantity_available + excluded.quantity_available,
                     updated_at = excluded.updated_at`,
            ).bind(item.inventory_variant_id, release, now, now));
            statements.push(env.DB.prepare(
                `INSERT INTO inventory_movements
                    (inventory_variant_id, quantity_change, movement_type, order_item_id, reason, occurred_at,
                     recorded_by, created_at, updated_at)
                 SELECT ?, ?, 'ready_sale_cancel', ?, ?, ?, ?, ?, ? WHERE changes() > 0`,
            ).bind(
                item.inventory_variant_id,
                release,
                item.id,
                `إرجاع كمية غير مسلمة عند إلغاء الطلب ${order.number}`,
                now,
                user.id,
                now,
                now,
            ));
        }
    }
    statements.push(env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, reason, request_id, occurred_at)
         SELECT ?, 'order_cancelled', 'App\\\\Models\\\\Cancellation', id, ?, ?, ?, ?
         FROM cancellations WHERE order_id = ?`,
    ).bind(
        user.id,
        JSON.stringify({ order_id: orderId, production_stage_snapshot: cancellationStage, cancellation_discount: asMajor(discount), refund_due: asMajor(refundDue) }),
        reason.trim(),
        request.headers.get("cf-ray"),
        now,
        orderId,
    ));
    try {
        const result = await env.DB.batch(statements);
        if ((result[0]?.meta?.changes ?? 0) !== 1) return tools.json({ error: "order_already_cancelled" }, 409);
        return tools.json({ id: result[0].meta.last_row_id, order_id: orderId, refund_due: asMajor(refundDue), status: "cancelled" }, 201);
    } catch (error) {
        console.error("D1 cancellation transaction failed", error instanceof Error ? error.message : "unknown");
        return tools.json({ error: "cancellation_transaction_failed" }, 409);
    }
}

async function correctCancellation(request, orderId, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const discount = amount(parsed.data.cancellation_discount, true);
    const reason = parsed.data.reason;
    if (discount === null || typeof reason !== "string" || !reason.trim() || reason.trim().length > 3000) {
        return tools.json({ error: "invalid_cancellation_correction" }, 422);
    }
    const order = await env.DB.prepare(
        "SELECT id, approved_total, operational_status FROM orders WHERE id = ?",
    ).bind(orderId).first();
    if (!order || order.operational_status !== "cancelled") return tools.json({ error: "cancellation_not_found" }, 404);
    const cancellation = await env.DB.prepare(
        "SELECT id, cancellation_discount, refund_due FROM cancellations WHERE order_id = ? ORDER BY id DESC LIMIT 1",
    ).bind(orderId).first();
    if (!cancellation) return tools.json({ error: "cancellation_not_found" }, 404);
    if (discount > (amount(String(order.approved_total), true) ?? 0n)) {
        return tools.json({ error: "cancellation_discount_exceeds_total" }, 422);
    }
    const financial = await calculateFinancialSummary(order, env);
    const confirmed = BigInt(financial.confirmed.replace(".", ""));
    const refunded = BigInt(financial.refunded.replace(".", ""));
    const newDue = confirmed > refunded + discount ? confirmed - refunded - discount : 0n;
    const outstanding = await env.DB.prepare(
        `SELECT CAST(ROUND(max(0, coalesce(sum(approved_amount - refunded_amount), 0)) * 100) AS INTEGER) AS amount
         FROM refunds WHERE order_id = ?`,
    ).bind(orderId).first();
    if (BigInt(outstanding?.amount ?? 0) > newDue) {
        return tools.json({ error: "correction_below_outstanding_refunds" }, 409);
    }

    const oldDiscount = amount(String(cancellation.cancellation_discount), true) ?? 0n;
    const now = new Date().toISOString();
    try {
        const results = await env.DB.batch([
            env.DB.prepare(
                "UPDATE cancellations SET cancellation_discount = ?, refund_due = ?, updated_at = ? WHERE id = ?",
            ).bind(asMajor(discount), asMajor(newDue), now, cancellation.id),
            env.DB.prepare(
                `INSERT INTO financial_corrections
                    (order_id, subject_type, subject_id, field_name, old_amount, new_amount, reason, approved_by, occurred_at, created_at, updated_at)
                 SELECT ?, 'App\\\\Models\\\\Cancellation', ?, 'cancellation_discount', ?, ?, ?, ?, ?, ?, ?
                 WHERE changes() > 0`,
            ).bind(orderId, cancellation.id, asMajor(oldDiscount), asMajor(discount), reason.trim(), user.id, now, now, now),
            audit(env, request, user.id, "cancellation_corrected", "App\\\\Models\\\\Cancellation", cancellation.id,
                { cancellation_discount: asMajor(discount), refund_due: asMajor(newDue) }, reason.trim(), now),
        ]);
        return tools.json({ id: cancellation.id, cancellation_discount: asMajor(discount), refund_due: asMajor(newDue) });
    } catch (error) {
        console.error("D1 cancellation correction failed", error instanceof Error ? error.message : "unknown");
        return tools.json({ error: "cancellation_correction_failed" }, 409);
    }
}

async function approveRefund(request, orderId, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const approved = amount(data.approved_amount);
    const reason = data.reason;
    const paymentId = data.original_payment_id === null || data.original_payment_id === undefined || data.original_payment_id === ""
        ? null
        : positiveId(data.original_payment_id);
    const deliveryItemId = data.returned_delivery_item_id === null || data.returned_delivery_item_id === undefined || data.returned_delivery_item_id === ""
        ? null
        : positiveId(data.returned_delivery_item_id);
    const returnedQuantity = data.returned_quantity === null || data.returned_quantity === undefined || data.returned_quantity === ""
        ? null
        : Number(data.returned_quantity);
    const condition = data.returned_item_condition ?? null;
    const inspectionNotes = optionalText(data.inspection_notes, 2000);
    if (approved === null || typeof reason !== "string" || !reason.trim() || reason.trim().length > 3000
        || (data.original_payment_id && !paymentId) || (data.returned_delivery_item_id && !deliveryItemId)
        || (returnedQuantity !== null && (!Number.isSafeInteger(returnedQuantity) || returnedQuantity < 1 || returnedQuantity > 500))
        || ![null, "resalable", "damaged", "altered"].includes(condition)
        || inspectionNotes === undefined) {
        return tools.json({ error: "invalid_refund_data" }, 422);
    }
    const order = await env.DB.prepare(
        "SELECT id, number, type, operational_status FROM orders WHERE id = ? AND deleted_at IS NULL",
    ).bind(orderId).first();
    if (!order) return tools.json({ error: "order_not_found" }, 404);
    const summary = await calculateFinancialSummary(order, env);
    const confirmed = BigInt(summary.confirmed.replace(".", ""));
    const refunded = BigInt(summary.refunded.replace(".", ""));
    const reservations = await env.DB.prepare(
        "SELECT CAST(ROUND(coalesce(sum(approved_amount - refunded_amount), 0) * 100) AS INTEGER) AS reserved FROM refunds WHERE order_id = ?",
    ).bind(orderId).first();
    let available = confirmed > refunded + BigInt(reservations?.reserved ?? 0)
        ? confirmed - refunded - BigInt(reservations?.reserved ?? 0)
        : 0n;

    let cancellationId = null;
    if (order.operational_status === "cancelled") {
        const cancellation = await env.DB.prepare(
            "SELECT id, refund_due FROM cancellations WHERE order_id = ? ORDER BY id DESC LIMIT 1",
        ).bind(orderId).first();
        if (!cancellation) return tools.json({ error: "cancellation_not_found" }, 409);
        cancellationId = cancellation.id;
        const approvedCancellations = await env.DB.prepare(
            "SELECT CAST(ROUND(coalesce(sum(approved_amount), 0) * 100) AS INTEGER) AS approved FROM refunds WHERE cancellation_id = ?",
        ).bind(cancellationId).first();
        const room = (amount(String(cancellation.refund_due), true) ?? 0n)
            - BigInt(approvedCancellations?.approved ?? 0);
        if (room < available) available = room > 0n ? room : 0n;
    }
    if (approved > available) return tools.json({ error: "refund_exceeds_available" }, 409);

    if (paymentId !== null) {
        const payment = await env.DB.prepare(
            `SELECT id, amount, method, verification_status FROM payments WHERE id = ? AND order_id = ?`,
        ).bind(paymentId, orderId).first();
        if (!payment || (payment.method === "bank_transfer" && payment.verification_status !== "verified")) {
            return tools.json({ error: "original_payment_not_confirmed" }, 422);
        }
        const prior = await env.DB.prepare(
            "SELECT CAST(ROUND(coalesce(sum(approved_amount), 0) * 100) AS INTEGER) AS approved FROM refunds WHERE original_payment_id = ?",
        ).bind(paymentId).first();
        const availableForPayment = (amount(String(payment.amount), true) ?? 0n)
            - BigInt(prior?.approved ?? 0);
        if (approved > availableForPayment) return tools.json({ error: "refund_exceeds_original_payment" }, 409);
    }

    let returnedDelivery = null;
    if (deliveryItemId !== null) {
        returnedDelivery = await env.DB.prepare(
            `SELECT delivery_items.id, delivery_items.order_item_id, delivery_items.quantity_delivered,
                    orders.id AS order_id, order_items.inventory_variant_id
             FROM delivery_items
             JOIN deliveries ON deliveries.id = delivery_items.delivery_id
             JOIN orders ON orders.id = deliveries.order_id
             JOIN order_items ON order_items.id = delivery_items.order_item_id
             WHERE delivery_items.id = ? AND orders.id = ?`,
        ).bind(deliveryItemId, orderId).first();
        if (!returnedDelivery) return tools.json({ error: "returned_item_not_in_order" }, 422);
        if (!returnedQuantity || !condition) return tools.json({ error: "returned_item_inspection_required" }, 422);
        const alreadyReturned = await env.DB.prepare(
            "SELECT coalesce(sum(returned_quantity), 0) AS quantity FROM refunds WHERE returned_delivery_item_id = ?",
        ).bind(deliveryItemId).first();
        if (Number(alreadyReturned?.quantity ?? 0) + returnedQuantity > returnedDelivery.quantity_delivered) {
            return tools.json({ error: "returned_quantity_exceeds_delivery" }, 409);
        }
    } else {
        const delivery = await env.DB.prepare("SELECT id FROM deliveries WHERE order_id = ? LIMIT 1").bind(orderId).first();
        if (delivery) return tools.json({ error: "returned_item_inspection_required" }, 422);
        if (returnedQuantity !== null || condition !== null) return tools.json({ error: "invalid_refund_inspection" }, 422);
    }

    const now = new Date().toISOString();
    const statements = [
        env.DB.prepare(
            `INSERT INTO refunds
                (order_id, cancellation_id, original_payment_id, returned_delivery_item_id,
                 returned_item_received_at, returned_quantity, returned_item_condition, inspection_notes,
                 approved_amount, refunded_amount, approved_by, approved_at, reason, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '0.00', ?, ?, ?, ?, ?)`,
        ).bind(
            orderId,
            cancellationId,
            paymentId,
            deliveryItemId,
            deliveryItemId === null ? null : now,
            returnedQuantity,
            condition,
            inspectionNotes,
            asMajor(approved),
            user.id,
            now,
            reason.trim(),
            now,
            now,
        ),
    ];
    if (returnedDelivery && condition === "resalable" && order.type === "ready") {
        if (!returnedDelivery.inventory_variant_id) return tools.json({ error: "returned_ready_item_missing_variant" }, 422);
        statements.push(env.DB.prepare(
            `INSERT INTO inventory_balances (inventory_variant_id, quantity_available, created_at, updated_at)
             VALUES (?, ?, ?, ?)
             ON CONFLICT (inventory_variant_id) DO UPDATE
             SET quantity_available = inventory_balances.quantity_available + excluded.quantity_available,
                 updated_at = excluded.updated_at`,
        ).bind(returnedDelivery.inventory_variant_id, returnedQuantity, now, now));
        statements.push(env.DB.prepare(
            `INSERT INTO inventory_movements
                (inventory_variant_id, quantity_change, movement_type, order_item_id, delivery_item_id, reason,
                 occurred_at, recorded_by, created_at, updated_at)
             VALUES (?, ?, 'customer_return', ?, ?, ?, ?, ?, ?, ?)`,
        ).bind(
            returnedDelivery.inventory_variant_id,
            returnedQuantity,
            returnedDelivery.order_item_id,
            deliveryItemId,
            `مرتجع مفحوص صالح للبيع؛ الاسترداد ${order.number}`,
            now,
            user.id,
            now,
            now,
        ));
    }
    statements.push(env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
         SELECT ?, 'refund_approved', 'App\\\\Models\\\\Refund', id, ?, ?, ?
         FROM refunds WHERE order_id = ? AND approved_by = ? ORDER BY id DESC LIMIT 1`,
    ).bind(
        user.id,
        JSON.stringify({ order_id: orderId, approved_amount: asMajor(approved), original_payment_id: paymentId, returned_delivery_item_id: deliveryItemId }),
        request.headers.get("cf-ray"),
        now,
        orderId,
        user.id,
    ));
    try {
        const result = await env.DB.batch(statements);
        return tools.json({ id: result[0].meta.last_row_id, order_id: orderId, approved_amount: asMajor(approved), refunded_amount: "0.00" }, 201);
    } catch (error) {
        console.error("D1 refund approval failed", error instanceof Error ? error.message : "unknown");
        return tools.json({ error: "refund_transaction_failed" }, 409);
    }
}

async function payRefund(request, refundId, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const paymentAmount = amount(parsed.data.amount);
    const method = parsed.data.method;
    const refundedAt = dateTime(parsed.data.refunded_at);
    if (paymentAmount === null || !["cash", "bank_transfer"].includes(method) || !refundedAt) {
        return tools.json({ error: "invalid_refund_transaction" }, 422);
    }
    const refund = await env.DB.prepare(
        "SELECT id, order_id, approved_amount, refunded_amount FROM refunds WHERE id = ?",
    ).bind(refundId).first();
    if (!refund) return tools.json({ error: "not_found" }, 404);
    const remaining = (amount(String(refund.approved_amount), true) ?? 0n) - (amount(String(refund.refunded_amount), true) ?? 0n);
    if (paymentAmount > remaining) return tools.json({ error: "refund_payment_exceeds_approved" }, 409);
    const now = new Date().toISOString();
    try {
        const results = await env.DB.batch([
            env.DB.prepare(
                `UPDATE refunds SET refunded_amount = refunded_amount + ?, method = ?, refunded_at = ?, updated_at = ?
                 WHERE id = ? AND refunded_amount + ? <= approved_amount`,
            ).bind(asMajor(paymentAmount), method, refundedAt, now, refundId, asMajor(paymentAmount)),
            env.DB.prepare(
                `INSERT INTO refund_transactions
                    (refund_id, amount, method, refunded_at, recorded_by, created_at, updated_at)
                 SELECT ?, ?, ?, ?, ?, ?, ? WHERE changes() > 0`,
            ).bind(refundId, asMajor(paymentAmount), method, refundedAt, user.id, now, now),
            env.DB.prepare(
                `INSERT INTO audit_logs
                    (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
                 SELECT ?, 'refund_paid', 'App\\\\Models\\\\RefundTransaction', id, ?, ?, ?
                 FROM refund_transactions WHERE refund_id = ? AND recorded_by = ? AND created_at = ?
                   AND changes() > 0 ORDER BY id DESC LIMIT 1`,
            ).bind(user.id, JSON.stringify({ refund_id: refundId, amount: asMajor(paymentAmount), method, refunded_at: refundedAt }), request.headers.get("cf-ray"), now, refundId, user.id, now),
        ]);
        if ((results[0]?.meta?.changes ?? 0) !== 1) return tools.json({ error: "refund_payment_exceeds_approved" }, 409);
        return tools.json({ id: results[1].meta.last_row_id, refund_id: refundId, amount: asMajor(paymentAmount), status: "recorded" }, 201);
    } catch (error) {
        console.error("D1 refund payment failed", error instanceof Error ? error.message : "unknown");
        return tools.json({ error: "refund_payment_failed" }, 409);
    }
}

async function correctRefund(request, refundId, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const newAmount = amount(parsed.data.approved_amount);
    const reason = parsed.data.reason;
    if (newAmount === null || typeof reason !== "string" || !reason.trim() || reason.trim().length > 3000) {
        return tools.json({ error: "invalid_refund_correction" }, 422);
    }
    const refund = await env.DB.prepare(
        `SELECT refunds.id, refunds.order_id, refunds.cancellation_id, refunds.original_payment_id,
                refunds.approved_amount, refunds.refunded_amount, orders.operational_status
         FROM refunds JOIN orders ON orders.id = refunds.order_id WHERE refunds.id = ?`,
    ).bind(refundId).first();
    if (!refund) return tools.json({ error: "not_found" }, 404);
    const oldAmount = amount(String(refund.approved_amount), true) ?? 0n;
    const alreadyRefunded = amount(String(refund.refunded_amount), true) ?? 0n;
    if (newAmount < alreadyRefunded) return tools.json({ error: "correction_below_refunded_amount" }, 409);
    const orderForSummary = await env.DB.prepare(
        "SELECT id, approved_total, operational_status FROM orders WHERE id = ?",
    ).bind(refund.order_id).first();
    const financial = await calculateFinancialSummary(orderForSummary, env);
    const others = await env.DB.prepare(
        "SELECT CAST(ROUND(coalesce(sum(approved_amount - refunded_amount), 0) * 100) AS INTEGER) AS remaining FROM refunds WHERE order_id = ? AND id != ?",
    ).bind(refund.order_id, refundId).first();
    const room = BigInt(financial.confirmed.replace(".", ""))
        - BigInt(financial.refunded.replace(".", ""))
        - BigInt(others?.remaining ?? 0);
    if (newAmount - alreadyRefunded > room) return tools.json({ error: "refund_correction_exceeds_available" }, 409);
    if (refund.original_payment_id) {
        const payment = await env.DB.prepare(
            `SELECT payments.amount,
                    CAST(ROUND(coalesce(sum(other.approved_amount), 0) * 100) AS INTEGER) AS others_approved
             FROM payments LEFT JOIN refunds AS other ON other.original_payment_id = payments.id AND other.id != ?
             WHERE payments.id = ? GROUP BY payments.id`,
        ).bind(refundId, refund.original_payment_id).first();
        const paymentRoom = (amount(String(payment.amount), true) ?? 0n)
            - BigInt(payment.others_approved ?? 0);
        if (newAmount > paymentRoom) return tools.json({ error: "refund_correction_exceeds_original_payment" }, 409);
    }
    if (refund.cancellation_id) {
        const cancellation = await env.DB.prepare("SELECT refund_due FROM cancellations WHERE id = ?").bind(refund.cancellation_id).first();
        const siblings = await env.DB.prepare(
            "SELECT CAST(ROUND(coalesce(sum(approved_amount), 0) * 100) AS INTEGER) AS amount FROM refunds WHERE cancellation_id = ? AND id != ?",
        ).bind(refund.cancellation_id, refundId).first();
        if (newAmount + BigInt(siblings?.amount ?? 0) > (amount(String(cancellation.refund_due), true) ?? 0n)) {
            return tools.json({ error: "refund_correction_exceeds_cancellation_due" }, 409);
        }
    }
    const now = new Date().toISOString();
    try {
        await env.DB.batch([
            env.DB.prepare(
                "UPDATE refunds SET approved_amount = ?, updated_at = ? WHERE id = ?",
            ).bind(asMajor(newAmount), now, refundId),
            env.DB.prepare(
                `INSERT INTO financial_corrections
                    (order_id, subject_type, subject_id, field_name, old_amount, new_amount, reason, approved_by, occurred_at, created_at, updated_at)
                 SELECT ?, 'App\\\\Models\\\\Refund', ?, 'approved_amount', ?, ?, ?, ?, ?, ?, ? WHERE changes() > 0`,
            ).bind(refund.order_id, refundId, asMajor(oldAmount), asMajor(newAmount), reason.trim(), user.id, now, now, now),
            audit(env, request, user.id, "refund_corrected", "App\\\\Models\\\\Refund", refundId,
                { approved_amount: asMajor(newAmount) }, reason.trim(), now),
        ]);
        return tools.json({ id: refundId, approved_amount: asMajor(newAmount) });
    } catch (error) {
        console.error("D1 refund correction failed", error instanceof Error ? error.message : "unknown");
        return tools.json({ error: "refund_correction_failed" }, 409);
    }
}

export async function handleFinanceControlsApi(request, env, url, tools) {
    if (url.pathname === "/api/expenses") {
        const write = request.method !== "GET";
        const access = await authorize(request, env, "expenses.record", tools, write);
        if (access.response) return access.response;
        if (request.method === "GET") return listExpenses(request, env, access.user, tools);
        if (request.method === "POST") return createExpense(request, env, access.user, tools);
        return tools.json({ error: "method_not_allowed" }, 405);
    }
    const cancel = url.pathname.match(/^\/api\/orders\/(\d+)\/cancel$/);
    if (cancel) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "orders.cancel", tools, true);
        return access.response ?? cancelOrder(request, Number(cancel[1]), env, access.user, tools);
    }
    const cancellationCorrection = url.pathname.match(/^\/api\/orders\/(\d+)\/cancellation$/);
    if (cancellationCorrection) {
        if (request.method !== "PATCH") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "history.correct", tools, true);
        return access.response ?? correctCancellation(request, Number(cancellationCorrection[1]), env, access.user, tools);
    }
    const refund = url.pathname.match(/^\/api\/orders\/(\d+)\/refunds$/);
    if (refund) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "refunds.approve", tools, true);
        return access.response ?? approveRefund(request, Number(refund[1]), env, access.user, tools);
    }
    const refundPayment = url.pathname.match(/^\/api\/refunds\/(\d+)\/pay$/);
    if (refundPayment) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "refunds.approve", tools, true);
        return access.response ?? payRefund(request, Number(refundPayment[1]), env, access.user, tools);
    }
    const refundCorrection = url.pathname.match(/^\/api\/refunds\/(\d+)$/);
    if (refundCorrection) {
        if (request.method !== "PATCH") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "history.correct", tools, true);
        return access.response ?? correctRefund(request, Number(refundCorrection[1]), env, access.user, tools);
    }
    return null;
}
