const maximumAmountMinor = 999_999_999_999_999n;

function amountInMinorUnits(value, allowZero = false) {
    const normalized = typeof value === "number" ? String(value) : value;
    if (typeof normalized !== "string" || !/^\d{1,13}(?:\.\d{1,2})?$/.test(normalized)) return null;
    const [whole, fraction = ""] = normalized.split(".");
    const amount = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0"));
    if (amount > maximumAmountMinor || (!allowZero && amount === 0n)) return null;
    return amount;
}

function amountInMajorUnits(minor) {
    return `${minor / 100n}.${String(minor % 100n).padStart(2, "0")}`;
}

function optionalText(value, maximum) {
    if (value === undefined || value === null || value === "") return null;
    return typeof value === "string" && value.length <= maximum ? value.trim() || null : undefined;
}

function dateTime(value) {
    if (typeof value !== "string" || value.length > 40) return null;
    const date = new Date(value);
    if (Number.isNaN(date.valueOf()) || date.valueOf() > Date.now()) return null;
    return date.toISOString();
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

async function parseBody(request, tools) {
    const parsed = await tools.requestData(request);
    return parsed.error
        ? { response: tools.json({ error: parsed.error }, parsed.status) }
        : { data: parsed.data };
}

export async function calculateFinancialSummary(order, env) {
    const totals = await env.DB.prepare(
        `SELECT
            CAST(ROUND(coalesce(sum(amount), 0) * 100) AS INTEGER) AS recorded_minor,
            CAST(ROUND(coalesce(sum(
                CASE WHEN method = 'cash' OR verification_status = 'verified' THEN amount ELSE 0 END
            ), 0) * 100) AS INTEGER) AS confirmed_minor,
            coalesce(max(CASE WHEN method = 'bank_transfer' AND verification_status = 'pending' THEN 1 ELSE 0 END), 0) AS has_pending_transfer
         FROM payments WHERE order_id = ?`,
    ).bind(order.id).first();
    const refunds = await env.DB.prepare(
        "SELECT CAST(ROUND(coalesce(sum(refunded_amount), 0) * 100) AS INTEGER) AS refunded_minor FROM refunds WHERE order_id = ? AND refunded_at IS NOT NULL",
    ).bind(order.id).first();
    const cancellation = await env.DB.prepare(
        "SELECT CAST(ROUND(cancellation_discount * 100) AS INTEGER) AS discount_minor FROM cancellations WHERE order_id = ? ORDER BY id DESC LIMIT 1",
    ).bind(order.id).first();
    const approvedMinor = amountInMinorUnits(order.approved_total, true) ?? 0n;
    const confirmedMinor = BigInt(totals?.confirmed_minor ?? 0);
    const recordedMinor = BigInt(totals?.recorded_minor ?? 0);
    const refundedMinor = BigInt(refunds?.refunded_minor ?? 0);
    const cancellationDiscount = BigInt(cancellation?.discount_minor ?? 0);
    const netPaid = confirmedMinor > refundedMinor ? confirmedMinor - refundedMinor : 0n;
    const effectiveTotal = approvedMinor > cancellationDiscount
        ? approvedMinor - cancellationDiscount
        : 0n;
    const due = order.operational_status === "cancelled" || netPaid >= effectiveTotal
        ? 0n
        : effectiveTotal - netPaid;

    return {
        recorded: amountInMajorUnits(recordedMinor),
        confirmed: amountInMajorUnits(confirmedMinor),
        refunded: amountInMajorUnits(refundedMinor),
        net_paid: amountInMajorUnits(netPaid),
        due: amountInMajorUnits(due),
        has_pending_transfer: Boolean(totals?.has_pending_transfer),
        paid: due === 0n && order.operational_status !== "cancelled",
    };
}

async function createPayment(request, env, user, tools) {
    const parsed = await parseBody(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const orderId = Number(data.order_id);
    const amount = amountInMinorUnits(data.amount);
    const paidAt = dateTime(data.paid_at);
    const reference = optionalText(data.transaction_reference, 120);
    const notes = optionalText(data.notes, 2000);
    if (
        !Number.isSafeInteger(orderId) || orderId < 1 || amount === null || paidAt === null
        || !["cash", "bank_transfer"].includes(data.method)
        || reference === undefined || notes === undefined
    ) return tools.json({ error: "invalid_payment_data" }, 422);

    const order = await env.DB.prepare(
        "SELECT id, approved_total, operational_status FROM orders WHERE id = ? AND deleted_at IS NULL",
    ).bind(orderId).first();
    if (!order) return tools.json({ error: "order_not_found" }, 404);
    if (reference) {
        const duplicate = await env.DB.prepare(
            "SELECT id FROM payments WHERE order_id = ? AND transaction_reference = ?",
        ).bind(orderId, reference).first();
        if (duplicate) return tools.json({ error: "transaction_reference_in_use" }, 409);
    }

    const now = new Date().toISOString();
    const verification = data.method === "cash" ? "not_required" : "pending";
    try {
        const result = await env.DB.batch([
            env.DB.prepare(
                `INSERT INTO payments
                    (order_id, amount, paid_at, method, verification_status, transaction_reference,
                     recorded_by, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            ).bind(
                orderId,
                amountInMajorUnits(amount),
                paidAt,
                data.method,
                verification,
                reference,
                user.id,
                notes,
                now,
                now,
            ),
            env.DB.prepare(
                `INSERT INTO audit_logs
                    (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
                 VALUES (?, 'payment_recorded', 'App\\\\Models\\\\Payment', last_insert_rowid(), ?, ?, ?)`,
            ).bind(
                user.id,
                JSON.stringify({ order_id: orderId, amount: amountInMajorUnits(amount), method: data.method, verification_status: verification }),
                request.headers.get("cf-ray"),
                now,
            ),
        ]);
        const summary = await calculateFinancialSummary(order, env);
        return tools.json({ id: result[0].meta.last_row_id, order_id: orderId, amount: amountInMajorUnits(amount), method: data.method, verification_status: verification, summary }, 201);
    } catch (error) {
        const errorType = error instanceof Error ? error.name : "unknown";
        console.error("D1 payment transaction failed", errorType);
        return tools.json({ error: "payment_exceeds_order_or_transaction_failed" }, 409);
    }
}

async function listPayments(request, env, user, tools) {
    const result = await env.DB.prepare(
        `SELECT payments.id, payments.order_id, orders.number AS order_number,
                printf('%.2f', payments.amount) AS amount, payments.paid_at, payments.method,
                payments.verification_status, payments.transaction_reference,
                payments.recorded_by, users.name AS recorded_by_name
         FROM payments
         JOIN orders ON orders.id = payments.order_id
         JOIN users ON users.id = payments.recorded_by
         WHERE (? = 1 OR payments.recorded_by = ?)
         ORDER BY payments.paid_at DESC, payments.id DESC LIMIT 100`,
    ).bind(Number(user.permissions.includes("finance.view")), user.id).all();
    return tools.json({ data: result.results ?? [] });
}

async function verifyPayment(request, paymentId, env, user, tools) {
    const payment = await env.DB.prepare(
        "SELECT id, method, verification_status FROM payments WHERE id = ?",
    ).bind(paymentId).first();
    if (!payment) return tools.json({ error: "not_found" }, 404);
    if (payment.method !== "bank_transfer") return tools.json({ error: "verification_not_required" }, 422);
    if (payment.verification_status !== "pending") return tools.json({ error: "payment_not_pending" }, 409);

    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(
            `UPDATE payments SET verification_status = 'verified', verified_by = ?, verified_at = ?, updated_at = ?
             WHERE id = ? AND method = 'bank_transfer' AND verification_status = 'pending'`,
        ).bind(user.id, now, now, paymentId),
        env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, 'bank_transfer_verified', 'App\\\\Models\\\\Payment', ?, ?, ?, ?
             WHERE changes() > 0`,
        ).bind(user.id, paymentId, JSON.stringify({ verification_status: "verified" }), request.headers.get("cf-ray"), now),
    ]);
    if ((results[0]?.meta?.changes ?? 0) !== 1) return tools.json({ error: "payment_not_pending" }, 409);
    return tools.json({ status: "verified" });
}

async function createDelivery(request, orderId, env, user, tools) {
    const parsed = await parseBody(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const deliveredAt = dateTime(data.delivered_at);
    const receiver = data.received_by_name;
    const receiptNumber = optionalText(data.receipt_number, 80);
    const notes = optionalText(data.notes, 2000);
    if (
        deliveredAt === null || typeof receiver !== "string" || !receiver.trim() || receiver.trim().length > 160
        || receiptNumber === undefined || notes === undefined
        || !Array.isArray(data.items) || data.items.length === 0 || data.items.length > 100
    ) return tools.json({ error: "invalid_delivery_data" }, 422);

    const requested = [];
    const uniqueItems = new Set();
    for (const entry of data.items) {
        const itemId = Number(entry?.order_item_id);
        const quantity = Number(entry?.quantity_delivered);
        if (
            !Number.isSafeInteger(itemId) || itemId < 1
            || !Number.isSafeInteger(quantity) || quantity < 0 || quantity > 500
            || uniqueItems.has(itemId)
        ) return tools.json({ error: "invalid_delivery_items" }, 422);
        uniqueItems.add(itemId);
        if (quantity > 0) requested.push({ itemId, quantity });
    }
    if (requested.length === 0) return tools.json({ error: "positive_delivery_quantity_required" }, 422);

    const order = await env.DB.prepare(
        "SELECT id, type, operational_status, approved_total FROM orders WHERE id = ? AND deleted_at IS NULL",
    ).bind(orderId).first();
    if (!order) return tools.json({ error: "order_not_found" }, 404);
    if (order.operational_status === "cancelled") return tools.json({ error: "cancelled_order" }, 409);
    const summary = await calculateFinancialSummary(order, env);
    if (!summary.paid) return tools.json({ error: "order_must_be_fully_paid", summary }, 409);

    if (receiptNumber) {
        const duplicate = await env.DB.prepare("SELECT id FROM deliveries WHERE receipt_number = ?")
            .bind(receiptNumber).first();
        if (duplicate) return tools.json({ error: "receipt_number_in_use" }, 409);
    }
    const placeholders = requested.map(() => "?").join(", ");
    const itemResult = await env.DB.prepare(
        `SELECT id, quantity FROM order_items WHERE order_id = ? AND id IN (${placeholders})`,
    ).bind(orderId, ...requested.map((item) => item.itemId)).all();
    if ((itemResult.results ?? []).length !== requested.length) {
        return tools.json({ error: "delivery_item_not_in_order" }, 422);
    }
    const itemMap = new Map(itemResult.results.map((item) => [item.id, item]));
    const existing = await env.DB.prepare(
        `SELECT order_item_id, coalesce(sum(quantity_delivered), 0) AS delivered
         FROM delivery_items WHERE order_item_id IN (${placeholders})
         GROUP BY order_item_id`,
    ).bind(...requested.map((item) => item.itemId)).all();
    const existingMap = new Map((existing.results ?? []).map((item) => [item.order_item_id, item.delivered]));
    for (const item of requested) {
        const remaining = itemMap.get(item.itemId).quantity - (existingMap.get(item.itemId) ?? 0);
        if (item.quantity > remaining) return tools.json({ error: "overdelivery" }, 409);
    }

    const totals = await env.DB.prepare(
        `SELECT order_items.id, order_items.quantity, coalesce(sum(delivery_items.quantity_delivered), 0) AS delivered
         FROM order_items LEFT JOIN delivery_items ON delivery_items.order_item_id = order_items.id
         WHERE order_items.order_id = ? GROUP BY order_items.id`,
    ).bind(orderId).all();
    const proposed = new Map(requested.map((item) => [item.itemId, item.quantity]));
    const willBeComplete = (totals.results ?? []).every(
        (item) => item.delivered + (proposed.get(item.id) ?? 0) >= item.quantity,
    );

    const now = new Date().toISOString();
    const statements = [
        env.DB.prepare(
            `INSERT INTO deliveries
                (order_id, delivered_at, received_by_name, receipt_number, recorded_by, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        ).bind(orderId, deliveredAt, receiver.trim(), receiptNumber, user.id, notes, now, now),
    ];
    for (const item of requested) {
        statements.push(env.DB.prepare(
            `INSERT INTO delivery_items (delivery_id, order_item_id, quantity_delivered, created_at, updated_at)
             VALUES ((SELECT id FROM deliveries WHERE order_id = ? ORDER BY id DESC LIMIT 1), ?, ?, ?, ?)`,
        ).bind(orderId, item.itemId, item.quantity, now, now));
    }
    statements.push(env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
         SELECT ?, 'delivery_recorded', 'App\\\\Models\\\\Delivery', id, ?, ?, ?
         FROM deliveries WHERE order_id = ? ORDER BY id DESC LIMIT 1`,
    ).bind(user.id, JSON.stringify({ order_id: orderId, items: requested }), request.headers.get("cf-ray"), now, orderId));
    if (willBeComplete) {
        statements.push(env.DB.prepare(
            "UPDATE orders SET operational_status = 'delivered', updated_at = ? WHERE id = ? AND operational_status != 'cancelled'",
        ).bind(now, orderId));
    }

    try {
        const results = await env.DB.batch(statements);
        return tools.json({ id: results[0].meta.last_row_id, status: willBeComplete ? "delivered" : "partially_delivered" }, 201);
    } catch (error) {
        const errorType = error instanceof Error ? error.name : "unknown";
        console.error("D1 delivery transaction failed", errorType);
        return tools.json({ error: "delivery_transaction_failed" }, 409);
    }
}

export async function handleFinanceApi(request, env, url, tools) {
    if (url.pathname === "/api/payments") {
        const write = request.method !== "GET";
        const access = await authorize(request, env, "payments.record", tools, write);
        if (access.response) return access.response;
        if (request.method === "GET") return listPayments(request, env, access.user, tools);
        if (request.method === "POST") return createPayment(request, env, access.user, tools);
        return tools.json({ error: "method_not_allowed" }, 405);
    }

    const verifyMatch = url.pathname.match(/^\/api\/payments\/(\d+)\/verify$/);
    if (verifyMatch) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "payments.verify", tools, true);
        return access.response ?? verifyPayment(request, Number(verifyMatch[1]), env, access.user, tools);
    }

    const deliveryMatch = url.pathname.match(/^\/api\/orders\/(\d+)\/deliveries$/);
    if (deliveryMatch) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "deliveries.create", tools, true);
        return access.response ?? createDelivery(request, Number(deliveryMatch[1]), env, access.user, tools);
    }

    return null;
}
