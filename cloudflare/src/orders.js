import { calculateFinancialSummary } from "./finance.js";

const measurementFields = [
    "shoulder_cm",
    "chest_cm",
    "sleeve_cm",
    "waist_circumference_cm",
    "length_cm",
];
const maximumAmountMinor = 999_999_999_999_999n;

function moneyInMinorUnits(value, allowZero) {
    const normalized = typeof value === "number" ? String(value) : value;
    if (typeof normalized !== "string" || !/^\d{1,13}(?:\.\d{1,2})?$/.test(normalized)) return null;
    const [whole, fraction = ""] = normalized.split(".");
    const minor = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0"));
    if (minor > maximumAmountMinor || (!allowZero && minor === 0n)) return null;
    return minor;
}

function moneyInMajorUnits(minor) {
    return `${minor / 100n}.${String(minor % 100n).padStart(2, "0")}`;
}

function positiveId(value) {
    if (value === null || value === undefined || value === "") return null;
    const id = Number(value);
    return Number.isSafeInteger(id) && id > 0 ? id : undefined;
}

function validateDueDate(value) {
    if (value === undefined || value === null || value === "") return null;
    if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return undefined;
    const parsed = new Date(`${value}T00:00:00.000Z`);
    if (Number.isNaN(parsed.valueOf()) || parsed.toISOString().slice(0, 10) !== value) return undefined;
    if (value < new Date().toISOString().slice(0, 10)) return undefined;
    return value;
}

function validateMeasurementSnapshot(value) {
    if (value === undefined || value === null) return null;
    if (!value || typeof value !== "object" || Array.isArray(value)) return undefined;
    if (Object.keys(value).some((field) => !measurementFields.includes(field))) return undefined;

    const snapshot = Object.fromEntries(measurementFields.map((field) => [field, null]));
    for (const [field, raw] of Object.entries(value)) {
        if (raw === null || raw === "") continue;
        const number = Number(raw);
        if (!Number.isFinite(number) || number <= 0 || number > 500) return undefined;
        snapshot[field] = number;
    }
    return snapshot;
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

function validOrderInput(data) {
    if (!["custom", "ready", "alteration"].includes(data.type)) return null;
    const guardianId = positiveId(data.guardian_id);
    const dueOn = validateDueDate(data.due_on);
    if (!guardianId || dueOn === undefined || !Array.isArray(data.items) || data.items.length === 0 || data.items.length > 30) {
        return null;
    }

    const items = [];
    let grossTotal = 0n;
    let discountTotal = 0n;
    for (const raw of data.items) {
        if (!raw || typeof raw !== "object" || Array.isArray(raw)) return null;
        const childId = positiveId(raw.child_id);
        const variantId = positiveId(raw.inventory_variant_id);
        const garmentTypeId = positiveId(raw.garment_type_id);
        const quantity = Number(raw.quantity);
        const unitPrice = moneyInMinorUnits(raw.unit_price, true);
        const discount = moneyInMinorUnits(raw.discount ?? "0", true);
        const description = raw.description;
        const measurements = validateMeasurementSnapshot(raw.measurements);
        if (
            childId === undefined || variantId === undefined || garmentTypeId === undefined
            || !Number.isSafeInteger(quantity) || quantity < 1 || quantity > 500
            || unitPrice === null || discount === null
            || typeof description !== "string" || !description.trim() || description.trim().length > 180
            || measurements === undefined
            || (data.type === "ready" && !variantId)
        ) return null;

        const gross = unitPrice * BigInt(quantity);
        if (discount > gross || gross > maximumAmountMinor) return null;
        grossTotal += gross;
        discountTotal += discount;
        if (grossTotal > maximumAmountMinor || discountTotal > grossTotal) return null;
        items.push({
            childId,
            variantId,
            garmentTypeId,
            quantity,
            unitPrice,
            discount,
            gross,
            net: gross - discount,
            description: description.trim(),
            measurements,
        });
    }

    const notes = data.notes ?? null;
    if (notes !== null && (typeof notes !== "string" || notes.length > 2000)) return null;
    return {
        type: data.type,
        guardianId,
        dueOn,
        items,
        grossTotal,
        discountTotal,
        approvedTotal: grossTotal - discountTotal,
        notes: notes?.trim() || null,
    };
}

async function createOrder(request, env, user, tools) {
    const parsed = await parseBody(request, tools);
    if (parsed.response) return parsed.response;
    const input = validOrderInput(parsed.data);
    if (!input) return tools.json({ error: "invalid_order_data" }, 422);

    const guardian = await env.DB.prepare(
        "SELECT id, name FROM guardians WHERE id = ? AND deleted_at IS NULL",
    ).bind(input.guardianId).first();
    if (!guardian) return tools.json({ error: "guardian_not_found" }, 422);

    const variantDemand = new Map();
    for (const item of input.items) {
        if (item.childId !== null) {
            const child = await env.DB.prepare(
                "SELECT id FROM children WHERE id = ? AND guardian_id = ? AND deleted_at IS NULL",
            ).bind(item.childId, input.guardianId).first();
            if (!child) return tools.json({ error: "child_guardian_mismatch" }, 422);
        }
        if (item.garmentTypeId !== null) {
            const type = await env.DB.prepare(
                "SELECT id FROM garment_types WHERE id = ? AND is_active = 1",
            ).bind(item.garmentTypeId).first();
            if (!type) return tools.json({ error: "garment_type_not_found" }, 422);
        }
        if (item.variantId !== null) {
            const variant = await env.DB.prepare(
                "SELECT id, is_active FROM inventory_variants WHERE id = ?",
            ).bind(item.variantId).first();
            if (!variant?.is_active) return tools.json({ error: "variant_not_active" }, 422);
        }
        if (input.type === "ready") {
            if (item.variantId === null) return tools.json({ error: "ready_variant_required" }, 422);
            variantDemand.set(item.variantId, (variantDemand.get(item.variantId) ?? 0) + item.quantity);
        }
        if (item.measurements && item.childId === null) {
            return tools.json({ error: "measurements_require_child" }, 422);
        }
    }

    for (const [variantId, quantity] of variantDemand) {
        const balance = await env.DB.prepare(
            "SELECT quantity_available FROM inventory_balances WHERE inventory_variant_id = ?",
        ).bind(variantId).first();
        if (!balance || balance.quantity_available < quantity) {
            return tools.json({ error: "insufficient_stock" }, 409);
        }
    }

    const date = new Date().toISOString();
    const orderNumber = `ORD-${date.slice(0, 10).replaceAll("-", "")}-${tools.randomToken().slice(0, 6).toUpperCase()}`;
    const statements = [
        env.DB.prepare(
            `INSERT INTO orders
                (number, type, guardian_id, customer_name_snapshot, created_by, created_on, due_on,
                 operational_status, notes, items_total, discount_total, approved_total, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, ?, ?, ?)`,
        ).bind(
            orderNumber,
            input.type,
            input.guardianId,
            guardian.name,
            user.id,
            date.slice(0, 10),
            input.dueOn,
            input.notes,
            moneyInMajorUnits(input.grossTotal),
            moneyInMajorUnits(input.discountTotal),
            moneyInMajorUnits(input.approvedTotal),
            date,
            date,
        ),
    ];

    for (const item of input.items) {
        if (input.type === "ready") {
            statements.push(env.DB.prepare(
                `UPDATE inventory_balances
                 SET quantity_available = quantity_available - ?, updated_at = ?
                    WHERE inventory_variant_id = ?`,
            ).bind(item.quantity, date, item.variantId));
        }
        statements.push(env.DB.prepare(
            `INSERT INTO order_items
                (order_id, child_id, inventory_variant_id, garment_type_id, description_snapshot,
                 quantity, unit_price, discount_amount, line_total, created_at, updated_at)
             SELECT orders.id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
             FROM orders WHERE orders.number = ?`,
        ).bind(
            item.childId,
            item.variantId,
            item.garmentTypeId,
            item.description,
            item.quantity,
            moneyInMajorUnits(item.unitPrice),
            moneyInMajorUnits(item.discount),
            moneyInMajorUnits(item.net),
            date,
            date,
            orderNumber,
        ));
        if (input.type === "ready") {
            statements.push(env.DB.prepare(
                `INSERT INTO inventory_movements
                    (inventory_variant_id, quantity_change, movement_type, order_item_id, reason, occurred_at, recorded_by)
                 VALUES (?, ?, 'ready_sale', last_insert_rowid(), ?, ?, ?)`,
            ).bind(
                item.variantId,
                -item.quantity,
                `بيع مخزون جاهز للطلب ${orderNumber}`,
                date,
                user.id,
            ));
        }

        if (item.childId !== null) {
            let measurement = item.measurements;
            if (measurement === null) {
                const current = await env.DB.prepare(
                    `SELECT shoulder_cm, chest_cm, sleeve_cm, waist_circumference_cm, length_cm
                     FROM current_measurements WHERE child_id = ?`,
                ).bind(item.childId).first();
                measurement = current
                    ? Object.fromEntries(measurementFields.map((field) => [field, current[field]]))
                    : null;
            }
            if (measurement && Object.values(measurement).some((value) => value !== null)) {
                statements.push(env.DB.prepare(
                    `INSERT INTO measurement_snapshots
                        (order_item_id, ${measurementFields.join(", ")}, created_at, updated_at)
                     SELECT order_items.id, ${measurementFields.map(() => "?").join(", ")}, ?, ?
                     FROM order_items
                     WHERE order_items.order_id = (SELECT id FROM orders WHERE number = ?)
                     ORDER BY order_items.id DESC LIMIT 1`,
                ).bind(
                    ...measurementFields.map((field) => measurement[field]),
                    date,
                    date,
                    orderNumber,
                ));
            }
        }
    }

    statements.push(env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
         SELECT ?, 'order_created', 'App\\\\Models\\\\Order', orders.id, ?, ?, ?
         FROM orders WHERE orders.number = ?`,
    ).bind(
        user.id,
        JSON.stringify({ type: input.type, guardian_id: input.guardianId, item_count: input.items.length }),
        request.headers.get("cf-ray"),
        date,
        orderNumber,
    ));

    try {
        const results = await env.DB.batch(statements);
        return tools.json({
            id: results[0].meta.last_row_id,
            number: orderNumber,
            type: input.type,
            guardian_id: input.guardianId,
            customer_name_snapshot: guardian.name,
            items_total: moneyInMajorUnits(input.grossTotal),
            discount_total: moneyInMajorUnits(input.discountTotal),
            approved_total: moneyInMajorUnits(input.approvedTotal),
            operational_status: "new",
        }, 201);
    } catch (error) {
        const errorType = error instanceof Error ? error.name : "unknown";
        console.error("D1 order transaction failed", errorType);
        return tools.json({ error: "order_transaction_failed" }, 409);
    }
}

async function listOrders(url, env, tools) {
    const search = (url.searchParams.get("q") ?? "").trim().slice(0, 120);
    const limit = Math.min(100, Math.max(1, Number(url.searchParams.get("limit") ?? 30) || 30));
    const pattern = `%${search}%`;
    const result = await env.DB.prepare(
        `SELECT orders.id, orders.number, orders.type, orders.customer_name_snapshot,
                orders.created_on, orders.due_on, orders.operational_status,
                printf('%.2f', orders.approved_total) AS approved_total,
                (SELECT coalesce(sum(order_items.quantity), 0) FROM order_items WHERE order_items.order_id = orders.id) AS item_quantity
         FROM orders
         WHERE orders.deleted_at IS NULL
           AND (? = '' OR orders.number LIKE ? OR orders.customer_name_snapshot LIKE ?
                OR EXISTS (SELECT 1 FROM guardians WHERE guardians.id = orders.guardian_id AND guardians.name LIKE ?))
         ORDER BY orders.id DESC LIMIT ?`,
    ).bind(search, pattern, pattern, pattern, limit).all();

    return tools.json({ data: result.results ?? [] });
}

async function showOrder(id, env, user, tools) {
    const order = await env.DB.prepare(
        `SELECT orders.id, orders.number, orders.type, orders.guardian_id,
                orders.customer_name_snapshot, orders.created_by, orders.created_on, orders.due_on,
                orders.operational_status, orders.notes,
                printf('%.2f', orders.items_total) AS items_total,
                printf('%.2f', orders.discount_total) AS discount_total,
                printf('%.2f', orders.approved_total) AS approved_total,
                orders.cancelled_at, orders.created_at, orders.updated_at,
                guardians.name AS guardian_name
         FROM orders LEFT JOIN guardians ON guardians.id = orders.guardian_id
         WHERE orders.id = ? AND orders.deleted_at IS NULL`,
    ).bind(id).first();
    if (!order) return tools.json({ error: "not_found" }, 404);

    const [items, deliveries] = await Promise.all([
        env.DB.prepare(
            `SELECT order_items.id, order_items.order_id, order_items.beneficiary_id,
                    order_items.child_id, order_items.inventory_variant_id, order_items.garment_type_id,
                    order_items.description_snapshot, order_items.quantity,
                    printf('%.2f', order_items.unit_price) AS unit_price,
                    printf('%.2f', order_items.discount_amount) AS discount_amount,
                    printf('%.2f', order_items.line_total) AS line_total,
                    order_items.created_at, order_items.updated_at,
                    children.name AS child_name, garment_types.name AS garment_type_name,
                    inventory_variants.sku, coalesce((
                        SELECT sum(delivery_items.quantity_delivered)
                        FROM delivery_items
                        JOIN deliveries ON deliveries.id = delivery_items.delivery_id
                        WHERE deliveries.order_id = order_items.order_id
                          AND delivery_items.order_item_id = order_items.id
                    ), 0) AS delivered_quantity,
                    measurement_snapshots.shoulder_cm, measurement_snapshots.chest_cm,
                    measurement_snapshots.sleeve_cm, measurement_snapshots.waist_circumference_cm,
                    measurement_snapshots.length_cm
             FROM order_items
             LEFT JOIN children ON children.id = order_items.child_id
             LEFT JOIN garment_types ON garment_types.id = order_items.garment_type_id
             LEFT JOIN inventory_variants ON inventory_variants.id = order_items.inventory_variant_id
             LEFT JOIN measurement_snapshots ON measurement_snapshots.order_item_id = order_items.id
             WHERE order_items.order_id = ? ORDER BY order_items.id`,
        ).bind(id).all(),
        env.DB.prepare(
            `SELECT deliveries.id, deliveries.delivered_at, deliveries.received_by_name,
                    deliveries.receipt_number, delivery_items.order_item_id, delivery_items.quantity_delivered
             FROM deliveries
             JOIN delivery_items ON delivery_items.delivery_id = deliveries.id
             WHERE deliveries.order_id = ? ORDER BY deliveries.id, delivery_items.id`,
        ).bind(id).all(),
    ]);
    const result = { ...order, items: items.results ?? [], deliveries: deliveries.results ?? [] };
    result.financial_summary = await calculateFinancialSummary(order, env);
    if (user.permissions.includes("finance.view")) {
        const payments = await env.DB.prepare(
            `SELECT id, printf('%.2f', amount) AS amount, paid_at, method,
                    verification_status, transaction_reference, notes
             FROM payments WHERE order_id = ? ORDER BY paid_at, id`,
        ).bind(id).all();
        result.payments = payments.results ?? [];
    }

    return tools.json(result);
}

export async function handleOrderApi(request, env, url, tools) {
    if (url.pathname === "/api/orders") {
        const write = request.method !== "GET";
        const access = await authorize(request, env, write ? "orders.create" : "orders.view", tools, write);
        if (access.response) return access.response;
        if (request.method === "GET") return listOrders(url, env, tools);
        if (request.method === "POST") return createOrder(request, env, access.user, tools);
        return tools.json({ error: "method_not_allowed" }, 405);
    }

    const match = url.pathname.match(/^\/api\/orders\/(\d+)$/);
    if (match) {
        if (request.method !== "GET") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "orders.view", tools, false);
        return access.response ?? showOrder(Number(match[1]), env, access.user, tools);
    }

    return null;
}
