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

function optionalText(value, maximum) {
    if (value === undefined || value === null || value === "") return null;
    return typeof value === "string" && value.length <= maximum ? value.trim() || null : undefined;
}

function positiveId(value) {
    const id = Number(value);
    return Number.isSafeInteger(id) && id > 0 ? id : null;
}

function date(value) {
    if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
    const parsed = new Date(`${value}T00:00:00.000Z`);
    return !Number.isNaN(parsed.valueOf()) && parsed.toISOString().slice(0, 10) === value ? value : null;
}

function reference(prefix, tools) {
    return `${prefix}-${new Date().toISOString().slice(0, 10).replaceAll("-", "")}-${tools.randomToken().slice(0, 8).toUpperCase()}`;
}

function auditStatement(env, user, request, action, subjectType, subjectId, after, reason, occurredAt) {
    return env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, reason, request_id, occurred_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    ).bind(
        user.id,
        action,
        subjectType,
        subjectId,
        JSON.stringify(after),
        reason,
        request.headers.get("cf-ray"),
        occurredAt,
    );
}

async function listInventory(env, tools) {
    const [variants, openings, adjustments, movements] = await Promise.all([
        env.DB.prepare(
            `SELECT inventory_variants.id, inventory_variants.sku, inventory_variants.size,
                    inventory_variants.color, inventory_variants.description, inventory_variants.is_active,
                    schools.name AS school_name, grades.name AS grade_name, garment_types.name AS garment_type_name,
                    coalesce(inventory_balances.quantity_available, 0) AS quantity_available
             FROM inventory_variants
             LEFT JOIN schools ON schools.id = inventory_variants.school_id
             LEFT JOIN grades ON grades.id = inventory_variants.grade_id
             JOIN garment_types ON garment_types.id = inventory_variants.garment_type_id
             LEFT JOIN inventory_balances ON inventory_balances.inventory_variant_id = inventory_variants.id
             ORDER BY inventory_variants.is_active DESC, inventory_variants.id LIMIT 500`,
        ).all(),
        env.DB.prepare(
            `SELECT batches.id, batches.reference, batches.counted_on, batches.status, batches.notes,
                    users.name AS created_by_name, count(entries.id) AS entry_count
             FROM opening_inventory_batches AS batches
             JOIN users ON users.id = batches.created_by
             LEFT JOIN opening_inventory_entries AS entries ON entries.opening_inventory_batch_id = batches.id
             GROUP BY batches.id ORDER BY batches.id DESC LIMIT 20`,
        ).all(),
        env.DB.prepare(
            `SELECT adjustments.id, adjustments.reference, adjustments.status, adjustments.reason,
                    users.name AS created_by_name, count(items.id) AS item_count
             FROM inventory_adjustments AS adjustments
             JOIN users ON users.id = adjustments.created_by
             LEFT JOIN inventory_adjustment_items AS items ON items.inventory_adjustment_id = adjustments.id
             GROUP BY adjustments.id ORDER BY adjustments.id DESC LIMIT 20`,
        ).all(),
        env.DB.prepare(
            `SELECT movements.id, movements.inventory_variant_id, variants.sku, variants.size, variants.color,
                    types.name AS garment_type_name, movements.quantity_change, movements.movement_type,
                    movements.reason, movements.occurred_at, users.name AS recorded_by_name
             FROM inventory_movements AS movements
             JOIN inventory_variants AS variants ON variants.id = movements.inventory_variant_id
             JOIN garment_types AS types ON types.id = variants.garment_type_id
             JOIN users ON users.id = movements.recorded_by
             ORDER BY movements.occurred_at DESC, movements.id DESC LIMIT 50`,
        ).all(),
    ]);
    return tools.json({
        variants: variants.results ?? [],
        opening_batches: openings.results ?? [],
        adjustments: adjustments.results ?? [],
        movements: movements.results ?? [],
    });
}

async function createOpeningBatch(request, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const countedOn = date(data.counted_on);
    const notes = optionalText(data.notes, 2000);
    if (!countedOn || notes === undefined || !Array.isArray(data.entries)
        || data.entries.length < 1 || data.entries.length > 200) {
        return tools.json({ error: "invalid_opening_batch" }, 422);
    }

    const entries = [];
    const seen = new Set();
    for (const entry of data.entries) {
        const variantId = positiveId(entry?.inventory_variant_id);
        const quantity = Number(entry?.quantity_counted);
        const reason = optionalText(entry?.difference_reason, 500);
        if (!variantId || !Number.isSafeInteger(quantity) || quantity < 0 || quantity > 1_000_000
            || reason === undefined || seen.has(variantId)) {
            return tools.json({ error: "invalid_opening_entries" }, 422);
        }
        seen.add(variantId);
        entries.push({ variantId, quantity, reason });
    }

    const ids = [...seen];
    const variants = await env.DB.prepare(
        `SELECT id FROM inventory_variants WHERE is_active = 1 AND id IN (${ids.map(() => "?").join(", ")})`,
    ).bind(...ids).all();
    if ((variants.results ?? []).length !== ids.length) {
        return tools.json({ error: "variant_not_active" }, 422);
    }

    const now = new Date().toISOString();
    const batchReference = reference("OPEN", tools);
    const statements = [
        env.DB.prepare(
            `INSERT INTO opening_inventory_batches
                (reference, counted_on, status, created_by, notes, created_at, updated_at)
             VALUES (?, ?, 'draft', ?, ?, ?, ?)`,
        ).bind(batchReference, countedOn, user.id, notes, now, now),
    ];
    for (const entry of entries) {
        statements.push(env.DB.prepare(
            `INSERT INTO opening_inventory_entries
                (opening_inventory_batch_id, inventory_variant_id, quantity_counted, difference_reason, created_at, updated_at)
             SELECT id, ?, ?, ?, ?, ? FROM opening_inventory_batches WHERE reference = ?`,
        ).bind(entry.variantId, entry.quantity, entry.reason, now, now, batchReference));
    }
    statements.push(env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
         SELECT ?, 'opening_inventory_batch_created', 'App\\\\Models\\\\OpeningInventoryBatch', id, ?, ?, ?
         FROM opening_inventory_batches WHERE reference = ?`,
    ).bind(user.id, JSON.stringify({ counted_on: countedOn, entries: entries.length }), request.headers.get("cf-ray"), now, batchReference));

    try {
        const result = await env.DB.batch(statements);
        return tools.json({ id: result[0].meta.last_row_id, reference: batchReference, status: "draft" }, 201);
    } catch (error) {
        console.error("D1 opening inventory creation failed", error instanceof Error ? error.name : "unknown");
        return tools.json({ error: "opening_inventory_transaction_failed" }, 409);
    }
}

async function approveOpeningBatch(request, id, env, user, tools) {
    const batch = await env.DB.prepare(
        "SELECT id, reference, status, counted_on, notes FROM opening_inventory_batches WHERE id = ?",
    ).bind(id).first();
    if (!batch) return tools.json({ error: "not_found" }, 404);
    if (batch.status !== "draft") return tools.json({ error: "opening_batch_not_draft" }, 409);
    const entries = await env.DB.prepare(
        "SELECT id, inventory_variant_id, quantity_counted, difference_reason FROM opening_inventory_entries WHERE opening_inventory_batch_id = ? ORDER BY id",
    ).bind(id).all();
    if (!entries.results?.length) return tools.json({ error: "opening_batch_empty" }, 409);

    for (const entry of entries.results) {
        const movement = await env.DB.prepare(
            "SELECT id FROM inventory_movements WHERE inventory_variant_id = ? LIMIT 1",
        ).bind(entry.inventory_variant_id).first();
        if (movement) return tools.json({ error: "opening_balance_already_initialized" }, 409);
    }

    const now = new Date().toISOString();
    const statements = [
        env.DB.prepare(
            `UPDATE opening_inventory_batches SET status = 'approved', approved_by = ?, approved_at = ?, updated_at = ?
             WHERE id = ? AND status = 'draft'`,
        ).bind(user.id, now, now, id),
    ];
    for (const entry of entries.results) {
        statements.push(env.DB.prepare(
            `INSERT INTO inventory_balances (inventory_variant_id, quantity_available, created_at, updated_at)
             SELECT ?, ?, ?, ? WHERE changes() > 0
             ON CONFLICT (inventory_variant_id) DO UPDATE
             SET quantity_available = inventory_balances.quantity_available + excluded.quantity_available,
                 updated_at = excluded.updated_at`,
        ).bind(entry.inventory_variant_id, entry.quantity_counted, now, now));
        statements.push(env.DB.prepare(
            `INSERT INTO inventory_movements
                (inventory_variant_id, quantity_change, movement_type, opening_inventory_entry_id,
                 reason, occurred_at, recorded_by, created_at, updated_at)
             SELECT ?, ?, 'opening', ?, ?, ?, ?, ?, ?
             WHERE changes() > 0
               AND (SELECT status FROM opening_inventory_batches WHERE id = ?) = 'approved'`,
        ).bind(
            entry.inventory_variant_id,
            entry.quantity_counted,
            entry.id,
            entry.difference_reason ?? batch.notes ?? "رصيد افتتاحي بعد الجرد الفعلي",
            now,
            user.id,
            now,
            now,
            id,
        ));
    }
    statements.push(env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
         SELECT ?, 'opening_inventory_batch_approved', 'App\\\\Models\\\\OpeningInventoryBatch', id, ?, ?, ?
         FROM opening_inventory_batches WHERE id = ? AND status = 'approved' AND changes() > 0`,
    ).bind(user.id, JSON.stringify({ status: "approved" }), request.headers.get("cf-ray"), now, id));

    try {
        const results = await env.DB.batch(statements);
        if ((results[0]?.meta?.changes ?? 0) !== 1) return tools.json({ error: "opening_batch_not_draft" }, 409);
        return tools.json({ id, status: "approved" });
    } catch (error) {
        console.error("D1 opening inventory approval failed", error instanceof Error ? error.name : "unknown");
        return tools.json({ error: "opening_approval_failed" }, 409);
    }
}

async function createAdjustment(request, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const { reason: rawReason, items: rawItems } = parsed.data;
    if (typeof rawReason !== "string" || !rawReason.trim() || rawReason.trim().length > 1000
        || !Array.isArray(rawItems) || rawItems.length < 1 || rawItems.length > 100) {
        return tools.json({ error: "invalid_adjustment" }, 422);
    }
    const ids = new Set();
    const items = [];
    for (const item of rawItems) {
        const variantId = positiveId(item?.inventory_variant_id);
        const delta = Number(item?.quantity_delta);
        const itemReason = optionalText(item?.reason, 500) ?? rawReason.trim();
        if (!variantId || !Number.isSafeInteger(delta) || delta === 0
            || delta < -1_000_000 || delta > 1_000_000
            || itemReason.length > 500 || ids.has(variantId)) {
            return tools.json({ error: "invalid_adjustment_items" }, 422);
        }
        ids.add(variantId);
        items.push({ variantId, delta, reason: itemReason });
    }
    const variantIds = [...ids];
    const found = await env.DB.prepare(
        `SELECT id FROM inventory_variants WHERE is_active = 1 AND id IN (${variantIds.map(() => "?").join(", ")})`,
    ).bind(...variantIds).all();
    if ((found.results ?? []).length !== variantIds.length) {
        return tools.json({ error: "variant_not_active" }, 422);
    }

    const now = new Date().toISOString();
    const adjustmentReference = reference("ADJ", tools);
    const statements = [
        env.DB.prepare(
            `INSERT INTO inventory_adjustments
                (reference, status, reason, created_by, created_at, updated_at)
             VALUES (?, 'pending', ?, ?, ?, ?)`,
        ).bind(adjustmentReference, rawReason.trim(), user.id, now, now),
    ];
    for (const item of items) {
        statements.push(env.DB.prepare(
            `INSERT INTO inventory_adjustment_items
                (inventory_adjustment_id, inventory_variant_id, quantity_delta, reason, created_at, updated_at)
             SELECT id, ?, ?, ?, ?, ? FROM inventory_adjustments WHERE reference = ?`,
        ).bind(item.variantId, item.delta, item.reason, now, now, adjustmentReference));
    }
    statements.push(env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
         SELECT ?, 'inventory_adjustment_created', 'App\\\\Models\\\\InventoryAdjustment', id, ?, ?, ?
         FROM inventory_adjustments WHERE reference = ?`,
    ).bind(user.id, JSON.stringify({ reason: rawReason.trim(), item_count: items.length }), request.headers.get("cf-ray"), now, adjustmentReference));
    try {
        const result = await env.DB.batch(statements);
        return tools.json({ id: result[0].meta.last_row_id, reference: adjustmentReference, status: "pending" }, 201);
    } catch (error) {
        console.error("D1 inventory adjustment creation failed", error instanceof Error ? error.name : "unknown");
        return tools.json({ error: "adjustment_transaction_failed" }, 409);
    }
}

async function decideAdjustment(request, id, env, user, tools, decision) {
    const parsed = decision === "rejected" ? await body(request, tools) : { data: {} };
    if (parsed.response) return parsed.response;
    const rejectionReason = optionalText(parsed.data.rejection_reason, 1000);
    if (decision === "rejected" && !rejectionReason) {
        return tools.json({ error: "rejection_reason_required" }, 422);
    }
    const adjustment = await env.DB.prepare(
        "SELECT id, reference, reason, status FROM inventory_adjustments WHERE id = ?",
    ).bind(id).first();
    if (!adjustment) return tools.json({ error: "not_found" }, 404);
    if (adjustment.status !== "pending") return tools.json({ error: "adjustment_not_pending" }, 409);

    if (decision === "rejected") {
        const now = new Date().toISOString();
        try {
            const results = await env.DB.batch([
                env.DB.prepare(
                    "UPDATE inventory_adjustments SET status = 'rejected', approved_by = ?, approved_at = ?, updated_at = ? WHERE id = ? AND status = 'pending'",
                ).bind(user.id, now, now, id),
                env.DB.prepare(
                    `INSERT INTO audit_logs
                        (user_id, action, subject_type, subject_id, after_values, reason, request_id, occurred_at)
                     SELECT ?, 'inventory_adjustment_rejected', 'App\\\\Models\\\\InventoryAdjustment', id, ?, ?, ?, ?
                     FROM inventory_adjustments WHERE id = ? AND status = 'rejected' AND changes() > 0`,
                ).bind(user.id, JSON.stringify({ status: "rejected" }), rejectionReason, request.headers.get("cf-ray"), now, id),
            ]);
            if ((results[0]?.meta?.changes ?? 0) !== 1) return tools.json({ error: "adjustment_not_pending" }, 409);
            return tools.json({ id, status: "rejected" });
        } catch (error) {
            console.error("D1 inventory adjustment rejection failed", error instanceof Error ? error.name : "unknown");
            return tools.json({ error: "adjustment_decision_failed" }, 409);
        }
    }

    const items = await env.DB.prepare(
        "SELECT id, inventory_variant_id, quantity_delta, reason FROM inventory_adjustment_items WHERE inventory_adjustment_id = ? ORDER BY id",
    ).bind(id).all();
    if (!items.results?.length) return tools.json({ error: "adjustment_empty" }, 409);
    const now = new Date().toISOString();
    const statements = [
        env.DB.prepare(
            "UPDATE inventory_adjustments SET status = 'approved', approved_by = ?, approved_at = ?, updated_at = ? WHERE id = ? AND status = 'pending'",
        ).bind(user.id, now, now, id),
    ];
    for (const item of items.results) {
        statements.push(env.DB.prepare(
            `INSERT INTO inventory_balances (inventory_variant_id, quantity_available, created_at, updated_at)
             SELECT ?, ?, ?, ? WHERE changes() > 0
             ON CONFLICT (inventory_variant_id) DO UPDATE
             SET quantity_available = inventory_balances.quantity_available + excluded.quantity_available,
                 updated_at = excluded.updated_at`,
        ).bind(item.inventory_variant_id, item.quantity_delta, now, now));
        statements.push(env.DB.prepare(
            `INSERT INTO inventory_movements
                (inventory_variant_id, quantity_change, movement_type, inventory_adjustment_item_id,
                 reason, occurred_at, recorded_by, created_at, updated_at)
             SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?
             WHERE changes() > 0
               AND (SELECT status FROM inventory_adjustments WHERE id = ?) = 'approved'`,
        ).bind(
            item.inventory_variant_id,
            item.quantity_delta,
            item.quantity_delta > 0 ? "adjustment_in" : "adjustment_out",
            item.id,
            item.reason || adjustment.reason,
            now,
            user.id,
            now,
            now,
            id,
        ));
    }
    statements.push(env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
         SELECT ?, 'inventory_adjustment_approved', 'App\\\\Models\\\\InventoryAdjustment', id, ?, ?, ?
         FROM inventory_adjustments WHERE id = ? AND status = 'approved' AND changes() > 0`,
    ).bind(user.id, JSON.stringify({ status: "approved" }), request.headers.get("cf-ray"), now, id));
    try {
        const results = await env.DB.batch(statements);
        if ((results[0]?.meta?.changes ?? 0) !== 1) return tools.json({ error: "adjustment_not_pending" }, 409);
        return tools.json({ id, status: "approved" });
    } catch (error) {
        console.error("D1 inventory adjustment approval failed", error instanceof Error ? error.name : "unknown");
        return tools.json({ error: "adjustment_would_make_negative_or_failed" }, 409);
    }
}

export async function handleInventoryApi(request, env, url, tools) {
    if (url.pathname === "/api/inventory") {
        if (request.method !== "GET") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "inventory.view", tools, false);
        return access.response ?? listInventory(env, tools);
    }
    if (url.pathname === "/api/inventory/opening-batches") {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "inventory.manage", tools, true);
        return access.response ?? createOpeningBatch(request, env, access.user, tools);
    }
    const opening = url.pathname.match(/^\/api\/inventory\/opening-batches\/(\d+)\/approve$/);
    if (opening) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "inventory.manage", tools, true);
        return access.response ?? approveOpeningBatch(request, Number(opening[1]), env, access.user, tools);
    }
    if (url.pathname === "/api/inventory/adjustments") {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "inventory.manage", tools, true);
        return access.response ?? createAdjustment(request, env, access.user, tools);
    }
    const adjustment = url.pathname.match(/^\/api\/inventory\/adjustments\/(\d+)\/(approve|reject)$/);
    if (adjustment) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "inventory.manage", tools, true);
        return access.response ?? decideAdjustment(
            request,
            Number(adjustment[1]),
            env,
            access.user,
            tools,
            adjustment[2] === "approve" ? "approved" : "rejected",
        );
    }
    return null;
}
