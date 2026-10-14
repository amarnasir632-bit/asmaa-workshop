async function authorize(request, env, tools, write) {
    const user = await tools.authenticatedUser(request, env);
    if (!user) return { response: tools.json({ error: "unauthenticated" }, 401) };
    if (!user.permissions.includes("production.manage")) return { response: tools.json({ error: "forbidden" }, 403) };
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

function text(value, maximum, required = false) {
    if (value === undefined || value === null || value === "") return required ? undefined : null;
    return typeof value === "string" && value.trim().length <= maximum && (!required || value.trim().length > 0)
        ? value.trim() || null
        : undefined;
}

function id(value) {
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : null;
}

function integer(value, minimum, maximum) {
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) && parsed >= minimum && parsed <= maximum ? parsed : null;
}

function date(value, allowEmpty = false) {
    if (allowEmpty && (value === undefined || value === null || value === "")) return null;
    if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return undefined;
    const parsed = new Date(`${value}T00:00:00.000Z`);
    return !Number.isNaN(parsed.valueOf()) && parsed.toISOString().slice(0, 10) === value ? value : undefined;
}

function money(value, allowZero = false) {
    const normalized = typeof value === "number" ? String(value) : value;
    if (typeof normalized !== "string" || !/^\d{1,13}(?:\.\d{1,2})?$/.test(normalized)) return undefined;
    const [whole, fraction = ""] = normalized.split(".");
    const minor = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0"));
    if (minor > 999_999_999_999_999n || (!allowZero && minor === 0n)) return undefined;
    return `${minor / 100n}.${String(minor % 100n).padStart(2, "0")}`;
}

function audit(env, request, userId, action, type, subjectId, values, now) {
    return env.DB.prepare(
        `INSERT INTO audit_logs (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
    ).bind(userId, action, type, subjectId, JSON.stringify(values), request.headers.get("cf-ray"), now);
}

async function listProduction(env, tools) {
    const [jobs, eligible, dispatches] = await Promise.all([
        env.DB.prepare(
            `SELECT jobs.id, jobs.order_id, jobs.order_item_id, jobs.quantity, jobs.stage, jobs.expected_on, jobs.notes,
                    orders.number AS order_number, orders.operational_status, orders.customer_name_snapshot,
                    items.description_snapshot, items.quantity AS ordered_quantity, children.name AS child_name,
                    coalesce((SELECT sum(dispatch_items.quantity_sent)
                        FROM tailor_dispatch_items AS dispatch_items WHERE dispatch_items.order_item_id = jobs.order_item_id), 0) AS quantity_sent,
                    coalesce((SELECT sum(receipt_items.quantity_received)
                        FROM tailor_receipt_items AS receipt_items
                        JOIN tailor_dispatch_items AS dispatch_items ON dispatch_items.id = receipt_items.tailor_dispatch_item_id
                        WHERE dispatch_items.order_item_id = jobs.order_item_id), 0) AS quantity_received
             FROM production_jobs AS jobs
             JOIN orders ON orders.id = jobs.order_id
             JOIN order_items AS items ON items.id = jobs.order_item_id
             LEFT JOIN children ON children.id = items.child_id
             ORDER BY jobs.id DESC LIMIT 100`,
        ).all(),
        env.DB.prepare(
            `SELECT items.id AS order_item_id, items.order_id, items.quantity, items.description_snapshot,
                    orders.number AS order_number, orders.customer_name_snapshot
             FROM order_items AS items JOIN orders ON orders.id = items.order_id
             WHERE orders.type IN ('custom', 'alteration')
               AND orders.operational_status NOT IN ('cancelled', 'delivered')
               AND NOT EXISTS (SELECT 1 FROM production_jobs WHERE production_jobs.order_item_id = items.id)
             ORDER BY items.id DESC LIMIT 100`,
        ).all(),
        env.DB.prepare(
            `SELECT dispatches.id, dispatches.production_job_id, dispatches.recipient_name,
                    dispatches.sent_on, dispatches.notes, dispatch_items.id AS dispatch_item_id,
                    dispatch_items.order_item_id, dispatch_items.quantity_sent,
                    coalesce((SELECT sum(receipt_items.quantity_received)
                        FROM tailor_receipt_items AS receipt_items
                        WHERE receipt_items.tailor_dispatch_item_id = dispatch_items.id), 0) AS quantity_received
             FROM tailor_dispatches AS dispatches
             JOIN tailor_dispatch_items AS dispatch_items ON dispatch_items.tailor_dispatch_id = dispatches.id
             ORDER BY dispatches.id DESC, dispatch_items.id`,
        ).all(),
    ]);
    return tools.json({
        jobs: jobs.results ?? [],
        eligible_items: eligible.results ?? [],
        dispatches: dispatches.results ?? [],
    });
}

async function createJob(request, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const orderItemId = id(data.order_item_id);
    const quantity = integer(data.quantity, 1, 500);
    const expectedOn = date(data.expected_on, true);
    const notes = text(data.notes, 2000);
    if (!orderItemId || !quantity || expectedOn === undefined || notes === undefined) {
        return tools.json({ error: "invalid_production_job" }, 422);
    }
    const item = await env.DB.prepare(
        `SELECT order_items.id, order_items.order_id, order_items.quantity,
                orders.number, orders.type, orders.operational_status, orders.due_on
         FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE order_items.id = ?`,
    ).bind(orderItemId).first();
    if (!item) return tools.json({ error: "order_item_not_found" }, 404);
    if (!["custom", "alteration"].includes(item.type)
        || ["cancelled", "delivered"].includes(item.operational_status)) {
        return tools.json({ error: "production_not_allowed_for_order" }, 422);
    }
    if (quantity !== item.quantity) return tools.json({ error: "production_quantity_must_match_order_item" }, 422);
    const existing = await env.DB.prepare("SELECT id FROM production_jobs WHERE order_item_id = ?").bind(orderItemId).first();
    if (existing) return tools.json({ error: "production_job_exists" }, 409);

    const now = new Date().toISOString();
    const due = expectedOn ?? item.due_on;
    try {
        const results = await env.DB.batch([
            env.DB.prepare(
                `INSERT INTO production_jobs (order_id, order_item_id, quantity, stage, expected_on, notes, created_at, updated_at)
                 VALUES (?, ?, ?, 'sewing', ?, ?, ?, ?)`,
            ).bind(item.order_id, orderItemId, quantity, due, notes, now, now),
            env.DB.prepare(
                "UPDATE orders SET operational_status = 'in_progress', updated_at = ? WHERE id = ? AND operational_status = 'new'",
            ).bind(now, item.order_id),
            env.DB.prepare(
                `INSERT INTO audit_logs
                    (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
                 SELECT ?, 'production_job_created', 'App\\\\Models\\\\ProductionJob', id, ?, ?, ?
                 FROM production_jobs WHERE order_item_id = ?`,
            ).bind(user.id, JSON.stringify({ order_item_id: orderItemId, quantity, expected_on: due }), request.headers.get("cf-ray"), now, orderItemId),
        ]);
        return tools.json({ id: results[0].meta.last_row_id, order_id: item.order_id, order_item_id: orderItemId, quantity, stage: "sewing" }, 201);
    } catch (error) {
        console.error("D1 production job creation failed", error instanceof Error ? error.name : "unknown");
        return tools.json({ error: "production_job_conflict" }, 409);
    }
}

async function updateStage(request, idValue, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const next = parsed.data.stage;
    const reason = text(parsed.data.reason, 500, true);
    const allowedStages = ["not_started", "sewing", "with_tailor", "waiting_completion", "completed"];
    if (!allowedStages.includes(next) || !reason) return tools.json({ error: "invalid_production_stage" }, 422);
    const job = await env.DB.prepare("SELECT id, stage FROM production_jobs WHERE id = ?").bind(idValue).first();
    if (!job) return tools.json({ error: "not_found" }, 404);

    const now = new Date().toISOString();
    const result = await env.DB.batch([
        env.DB.prepare("UPDATE production_jobs SET stage = ?, updated_at = ? WHERE id = ?").bind(next, now, idValue),
        env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, before_values, after_values, reason, request_id, occurred_at)
             VALUES (?, 'production_stage_changed', 'App\\\\Models\\\\ProductionJob', ?, ?, ?, ?, ?, ?)`,
        ).bind(
            user.id,
            idValue,
            JSON.stringify({ stage: job.stage }),
            JSON.stringify({ stage: next }),
            reason,
            request.headers.get("cf-ray"),
            now,
        ),
    ]);
    if ((result[0]?.meta?.changes ?? 0) !== 1) return tools.json({ error: "not_found" }, 404);
    return tools.json({ id: idValue, stage: next });
}

async function createDispatch(request, jobId, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const recipient = text(data.recipient_name, 160, true);
    const sentOn = date(data.sent_on);
    const quantity = integer(data.quantity_sent, 1, 500);
    const agreedCost = data.agreed_cost === undefined || data.agreed_cost === null || data.agreed_cost === ""
        ? null
        : money(data.agreed_cost, true);
    const notes = text(data.notes, 2000);
    if (!recipient || !sentOn || !quantity || agreedCost === undefined || notes === undefined) {
        return tools.json({ error: "invalid_tailor_dispatch" }, 422);
    }
    const job = await env.DB.prepare(
        `SELECT jobs.id, jobs.order_id, jobs.order_item_id, jobs.quantity, orders.operational_status
         FROM production_jobs AS jobs JOIN orders ON orders.id = jobs.order_id WHERE jobs.id = ?`,
    ).bind(jobId).first();
    if (!job) return tools.json({ error: "not_found" }, 404);
    if (["cancelled", "delivered"].includes(job.operational_status)) {
        return tools.json({ error: "dispatch_not_allowed_for_order" }, 409);
    }
    const sent = await env.DB.prepare(
        "SELECT coalesce(sum(quantity_sent), 0) AS total FROM tailor_dispatch_items WHERE order_item_id = ?",
    ).bind(job.order_item_id).first();
    if (Number(sent?.total ?? 0) + quantity > job.quantity) {
        return tools.json({ error: "dispatch_exceeds_production_quantity" }, 409);
    }
    const now = new Date().toISOString();
    try {
        const result = await env.DB.batch([
            env.DB.prepare(
                `INSERT INTO tailor_dispatches
                    (production_job_id, recipient_name, sent_on, notes, recorded_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)`,
            ).bind(jobId, recipient, sentOn, notes, user.id, now, now),
            env.DB.prepare(
                `INSERT INTO tailor_dispatch_items
                    (tailor_dispatch_id, order_item_id, quantity_sent, agreed_cost, created_at, updated_at)
                 VALUES (last_insert_rowid(), ?, ?, ?, ?, ?)`,
            ).bind(job.order_item_id, quantity, agreedCost, now, now),
            env.DB.prepare("UPDATE production_jobs SET stage = 'with_tailor', updated_at = ? WHERE id = ?")
                .bind(now, jobId),
            env.DB.prepare(
                `INSERT INTO audit_logs
                    (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
                 SELECT ?, 'tailor_dispatch_created', 'App\\\\Models\\\\TailorDispatch', id, ?, ?, ?
                 FROM tailor_dispatches WHERE production_job_id = ? AND recorded_by = ? AND created_at = ?`,
            ).bind(user.id, JSON.stringify({ production_job_id: jobId, quantity_sent: quantity, agreed_cost: agreedCost }), request.headers.get("cf-ray"), now, jobId, user.id, now),
            env.DB.prepare(
                `INSERT INTO audit_logs
                    (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
                 SELECT ?, 'tailor_dispatch_item_created', 'App\\\\Models\\\\TailorDispatchItem', items.id, ?, ?, ?
                 FROM tailor_dispatch_items AS items
                 JOIN tailor_dispatches AS dispatches ON dispatches.id = items.tailor_dispatch_id
                 WHERE dispatches.production_job_id = ? AND dispatches.recorded_by = ? AND dispatches.created_at = ?
                 ORDER BY items.id DESC LIMIT 1`,
            ).bind(user.id, JSON.stringify({ quantity_sent: quantity, agreed_cost: agreedCost }), request.headers.get("cf-ray"), now, jobId, user.id, now),
        ]);
        return tools.json({ id: result[0].meta.last_row_id, production_job_id: jobId, quantity_sent: quantity }, 201);
    } catch (error) {
        console.error("D1 tailor dispatch transaction failed", error instanceof Error ? error.name : "unknown");
        return tools.json({ error: "tailor_dispatch_failed" }, 409);
    }
}

async function createReceipt(request, dispatchId, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const receivedOn = date(data.received_on);
    const notes = text(data.notes, 2000);
    if (!receivedOn || notes === undefined || !Array.isArray(data.items) || data.items.length < 1 || data.items.length > 100) {
        return tools.json({ error: "invalid_tailor_receipt" }, 422);
    }
    const items = [];
    const seen = new Set();
    for (const entry of data.items) {
        const dispatchItemId = id(entry?.dispatch_item_id);
        const quantity = integer(entry?.quantity_received, 1, 500);
        if (!dispatchItemId || !quantity || seen.has(dispatchItemId)) {
            return tools.json({ error: "invalid_tailor_receipt_items" }, 422);
        }
        seen.add(dispatchItemId);
        items.push({ dispatchItemId, quantity });
    }
    const dispatch = await env.DB.prepare(
        `SELECT dispatches.id, dispatches.production_job_id, jobs.order_id, jobs.order_item_id,
                orders.operational_status
         FROM tailor_dispatches AS dispatches
         JOIN production_jobs AS jobs ON jobs.id = dispatches.production_job_id
         JOIN orders ON orders.id = jobs.order_id
         WHERE dispatches.id = ?`,
    ).bind(dispatchId).first();
    if (!dispatch) return tools.json({ error: "not_found" }, 404);
    if (["cancelled", "delivered"].includes(dispatch.operational_status)) {
        return tools.json({ error: "receipt_not_allowed_for_order" }, 409);
    }
    for (const entry of items) {
        const dispatchItem = await env.DB.prepare(
            `SELECT id, quantity_sent FROM tailor_dispatch_items
             WHERE id = ? AND tailor_dispatch_id = ?`,
        ).bind(entry.dispatchItemId, dispatchId).first();
        if (!dispatchItem) return tools.json({ error: "dispatch_item_mismatch" }, 422);
        const received = await env.DB.prepare(
            "SELECT coalesce(sum(quantity_received), 0) AS total FROM tailor_receipt_items WHERE tailor_dispatch_item_id = ?",
        ).bind(entry.dispatchItemId).first();
        if (Number(received?.total ?? 0) + entry.quantity > dispatchItem.quantity_sent) {
            return tools.json({ error: "receipt_exceeds_dispatch_quantity" }, 409);
        }
    }

    const now = new Date().toISOString();
    const statements = [
        env.DB.prepare(
            `INSERT INTO tailor_receipts (tailor_dispatch_id, received_on, notes, recorded_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)`,
        ).bind(dispatchId, receivedOn, notes, user.id, now, now),
    ];
    for (const entry of items) {
        statements.push(env.DB.prepare(
            `INSERT INTO tailor_receipt_items
                (tailor_receipt_id, tailor_dispatch_item_id, quantity_received, created_at, updated_at)
             SELECT receipts.id, ?, ?, ?, ?
             FROM tailor_receipts AS receipts
             WHERE receipts.tailor_dispatch_id = ? AND receipts.recorded_by = ? AND receipts.created_at = ?
             ORDER BY receipts.id DESC LIMIT 1`,
        ).bind(entry.dispatchItemId, entry.quantity, now, now, dispatchId, user.id, now));
        statements.push(env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, 'tailor_receipt_item_created', 'App\\\\Models\\\\TailorReceiptItem', receipt_items.id, ?, ?, ?
             FROM tailor_receipt_items AS receipt_items
             JOIN tailor_receipts AS receipts ON receipts.id = receipt_items.tailor_receipt_id
             WHERE receipts.tailor_dispatch_id = ? AND receipts.recorded_by = ? AND receipts.created_at = ?
             ORDER BY receipt_items.id DESC LIMIT 1`,
        ).bind(user.id, JSON.stringify({ quantity_received: entry.quantity }), request.headers.get("cf-ray"), now, dispatchId, user.id, now));
    }
    statements.push(env.DB.prepare(
        `UPDATE orders SET operational_status = CASE
             WHEN (SELECT coalesce(sum(order_items.quantity), 0) FROM order_items WHERE order_items.order_id = orders.id)
                  <= (SELECT coalesce(sum(tailor_receipt_items.quantity_received), 0)
                      FROM production_jobs
                      JOIN tailor_dispatches ON tailor_dispatches.production_job_id = production_jobs.id
                      JOIN tailor_dispatch_items ON tailor_dispatch_items.tailor_dispatch_id = tailor_dispatches.id
                      JOIN tailor_receipt_items ON tailor_receipt_items.tailor_dispatch_item_id = tailor_dispatch_items.id
                      WHERE production_jobs.order_id = orders.id)
             THEN 'fully_received' ELSE 'partially_received' END,
             updated_at = ?
         WHERE id = ? AND operational_status NOT IN ('cancelled', 'delivered')
           AND (SELECT coalesce(sum(tailor_receipt_items.quantity_received), 0)
                FROM production_jobs
                JOIN tailor_dispatches ON tailor_dispatches.production_job_id = production_jobs.id
                JOIN tailor_dispatch_items ON tailor_dispatch_items.tailor_dispatch_id = tailor_dispatches.id
                JOIN tailor_receipt_items ON tailor_receipt_items.tailor_dispatch_item_id = tailor_dispatch_items.id
                WHERE production_jobs.order_id = orders.id) > 0`,
    ).bind(now, dispatch.order_id));
    statements.push(env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
         SELECT ?, 'tailor_receipt_created', 'App\\\\Models\\\\TailorReceipt', id, ?, ?, ?
         FROM tailor_receipts WHERE tailor_dispatch_id = ? AND recorded_by = ? AND created_at = ?
         ORDER BY id DESC LIMIT 1`,
    ).bind(user.id, JSON.stringify({ tailor_dispatch_id: dispatchId, received_on: receivedOn }), request.headers.get("cf-ray"), now, dispatchId, user.id, now));
    try {
        const result = await env.DB.batch(statements);
        return tools.json({ id: result[0].meta.last_row_id, status: "recorded" }, 201);
    } catch (error) {
        console.error("D1 tailor receipt transaction failed", error instanceof Error ? error.message : "unknown");
        return tools.json({ error: "tailor_receipt_failed" }, 409);
    }
}

export async function handleProductionApi(request, env, url, tools) {
    if (url.pathname === "/api/production") {
        if (request.method !== "GET") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, tools, false);
        return access.response ?? listProduction(env, tools);
    }
    if (url.pathname === "/api/production/jobs") {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, tools, true);
        return access.response ?? createJob(request, env, access.user, tools);
    }
    const stage = url.pathname.match(/^\/api\/production\/jobs\/(\d+)\/stage$/);
    if (stage) {
        if (request.method !== "PATCH") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, tools, true);
        return access.response ?? updateStage(request, Number(stage[1]), env, access.user, tools);
    }
    const dispatch = url.pathname.match(/^\/api\/production\/jobs\/(\d+)\/dispatches$/);
    if (dispatch) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, tools, true);
        return access.response ?? createDispatch(request, Number(dispatch[1]), env, access.user, tools);
    }
    const receipt = url.pathname.match(/^\/api\/production\/dispatches\/(\d+)\/receipts$/);
    if (receipt) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, tools, true);
        return access.response ?? createReceipt(request, Number(receipt[1]), env, access.user, tools);
    }
    return null;
}
