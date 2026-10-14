function validDate(value) {
    if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
    const parsed = new Date(`${value}T00:00:00.000Z`);
    return !Number.isNaN(parsed.valueOf()) && parsed.toISOString().slice(0, 10) === value;
}

function validId(value) {
    if (value === null || value === "") return null;
    const id = Number(value);
    return Number.isSafeInteger(id) && id > 0 ? id : undefined;
}

async function access(request, env, tools, permissions) {
    const user = await tools.authenticatedUser(request, env);
    if (!user) return { response: tools.json({ error: "unauthenticated" }, 401) };
    if (!permissions.some((permission) => user.permissions.includes(permission))) {
        return { response: tools.json({ error: "forbidden" }, 403) };
    }
    return { user };
}

function orderFilters(url) {
    const schoolId = validId(url.searchParams.get("school_id"));
    const guardianId = validId(url.searchParams.get("guardian_id"));
    const status = url.searchParams.get("status") || null;
    const allowedStatuses = ["new", "in_progress", "partially_received", "fully_received", "ready", "delivered", "cancelled"];
    return schoolId === undefined || guardianId === undefined || (status && !allowedStatuses.includes(status))
        ? null
        : { schoolId, guardianId, status };
}

function whereOrders(filters, includeDates = true) {
    const clauses = ["orders.deleted_at IS NULL"];
    const parameters = [];
    if (includeDates) {
        clauses.push("orders.created_on >= ?", "orders.created_on <= ?");
    }
    if (filters.schoolId !== null) {
        clauses.push("EXISTS (SELECT 1 FROM school_contracts WHERE school_contracts.order_id = orders.id AND school_contracts.school_id = ?)");
        parameters.push(filters.schoolId);
    }
    if (filters.guardianId !== null) {
        clauses.push("orders.guardian_id = ?");
        parameters.push(filters.guardianId);
    }
    if (filters.status) {
        clauses.push("orders.operational_status = ?");
        parameters.push(filters.status);
    }
    return { sql: clauses.join(" AND "), parameters };
}

async function getReport(request, env, url, tools) {
    const allowed = await access(request, env, tools, ["reports.operational", "reports.financial"]);
    if (allowed.response) return allowed.response;
    const today = new Date().toISOString().slice(0, 10);
    const from = url.searchParams.get("from") ?? `${today.slice(0, 7)}-01`;
    const to = url.searchParams.get("to") ?? today;
    const filters = orderFilters(url);
    if (!validDate(from) || !validDate(to) || from > to || !filters) {
        return tools.json({ error: "invalid_report_filters" }, 422);
    }
    const canSeeFinancial = allowed.user.permissions.includes("reports.financial");
    const where = whereOrders(filters);
    const currentWhere = whereOrders(filters, false);
    const params = [from, to, ...where.parameters];
    const [orders, states, jobs, inventory, movements] = await Promise.all([
        env.DB.prepare(
            `SELECT orders.id, orders.number, orders.type, orders.customer_name_snapshot,
                    orders.created_on, orders.due_on, orders.operational_status,
                    printf('%.2f', orders.approved_total) AS approved_total
             FROM orders WHERE ${where.sql} ORDER BY orders.created_on DESC, orders.id DESC LIMIT 500`,
        ).bind(...params).all(),
        env.DB.prepare(
            `SELECT orders.operational_status, count(*) AS total
             FROM orders WHERE ${where.sql} AND orders.created_on >= ? AND orders.created_on <= ?
             GROUP BY orders.operational_status`,
        ).bind(...params, from, to).all(),
        env.DB.prepare(
            `SELECT jobs.id, jobs.stage, jobs.quantity, jobs.expected_on, orders.number AS order_number,
                    items.description_snapshot,
                    coalesce((SELECT sum(dispatch_items.quantity_sent) FROM tailor_dispatch_items AS dispatch_items
                        WHERE dispatch_items.order_item_id = jobs.order_item_id), 0) AS quantity_sent,
                    coalesce((SELECT sum(receipt_items.quantity_received)
                        FROM tailor_receipt_items AS receipt_items
                        JOIN tailor_dispatch_items AS dispatch_items ON dispatch_items.id = receipt_items.tailor_dispatch_item_id
                        WHERE dispatch_items.order_item_id = jobs.order_item_id), 0) AS quantity_received
             FROM production_jobs AS jobs
             JOIN orders ON orders.id = jobs.order_id
             JOIN order_items AS items ON items.id = jobs.order_item_id
             WHERE ${where.sql} AND orders.created_on >= ? AND orders.created_on <= ?
             ORDER BY jobs.id DESC LIMIT 500`,
        ).bind(...params, from, to).all(),
        env.DB.prepare(
            `SELECT variants.id, variants.sku, variants.size, variants.color, variants.is_active,
                    schools.name AS school_name, types.name AS garment_type_name,
                    coalesce(balances.quantity_available, 0) AS quantity_available
             FROM inventory_variants AS variants
             JOIN garment_types AS types ON types.id = variants.garment_type_id
             LEFT JOIN schools ON schools.id = variants.school_id
             LEFT JOIN inventory_balances AS balances ON balances.inventory_variant_id = variants.id
             WHERE (? IS NULL OR variants.school_id = ?) ORDER BY variants.id LIMIT 500`,
        ).bind(filters.schoolId, filters.schoolId).all(),
        env.DB.prepare(
            `SELECT movements.id, movements.quantity_change, movements.movement_type, movements.reason,
                    movements.occurred_at, variants.sku, variants.size, types.name AS garment_type_name
             FROM inventory_movements AS movements
             JOIN inventory_variants AS variants ON variants.id = movements.inventory_variant_id
             JOIN garment_types AS types ON types.id = variants.garment_type_id
             WHERE movements.occurred_at >= ? AND movements.occurred_at < ?
               AND (? IS NULL OR variants.school_id = ?)
             ORDER BY movements.occurred_at DESC LIMIT 500`,
        ).bind(`${from}T00:00:00.000Z`, `${to}T23:59:59.999Z`, filters.schoolId, filters.schoolId).all(),
    ]);

    let financial = null;
    if (canSeeFinancial) {
        const [sales, cash, transfers, refunds, expenses, debts] = await Promise.all([
            env.DB.prepare(
                `SELECT printf('%.2f', coalesce(sum(orders.approved_total), 0)) AS amount, count(*) AS orders_count
                 FROM orders WHERE ${where.sql} AND orders.created_on >= ? AND orders.created_on <= ?`,
            ).bind(...params, from, to).first(),
            env.DB.prepare(
                `SELECT printf('%.2f', coalesce(sum(payments.amount), 0)) AS amount
                 FROM payments JOIN orders ON orders.id = payments.order_id
                 WHERE ${where.sql} AND payments.method = 'cash' AND payments.paid_at >= ? AND payments.paid_at < ?`,
            ).bind(...params, `${from}T00:00:00.000Z`, `${to}T23:59:59.999Z`).first(),
            env.DB.prepare(
                `SELECT printf('%.2f', coalesce(sum(payments.amount), 0)) AS amount
                 FROM payments JOIN orders ON orders.id = payments.order_id
                 WHERE ${where.sql} AND payments.method = 'bank_transfer' AND payments.verification_status = 'verified'
                   AND payments.verified_at >= ? AND payments.verified_at < ?`,
            ).bind(...params, `${from}T00:00:00.000Z`, `${to}T23:59:59.999Z`).first(),
            env.DB.prepare(
                `SELECT printf('%.2f', coalesce(sum(refund_transactions.amount), 0)) AS amount
                 FROM refund_transactions
                 JOIN refunds ON refunds.id = refund_transactions.refund_id
                 JOIN orders ON orders.id = refunds.order_id
                 WHERE ${where.sql} AND refund_transactions.refunded_at >= ? AND refund_transactions.refunded_at < ?`,
            ).bind(...params, `${from}T00:00:00.000Z`, `${to}T23:59:59.999Z`).first(),
            env.DB.prepare(
                `SELECT printf('%.2f', coalesce(sum(expenses.amount), 0)) AS amount
                 FROM expenses WHERE expenses.spent_on >= ? AND expenses.spent_on <= ?`,
            ).bind(from, to).first(),
            env.DB.prepare(
                `SELECT CAST(ROUND(coalesce(sum(max(
                            0,
                            ROUND(orders.approved_total * 100)
                            - coalesce((SELECT ROUND(cancellations.cancellation_discount * 100)
                                        FROM cancellations WHERE cancellations.order_id = orders.id
                                        ORDER BY cancellations.id DESC LIMIT 1), 0)
                            - coalesce((SELECT sum(ROUND(CASE
                                        WHEN payments.method = 'cash' OR payments.verification_status = 'verified'
                                        THEN payments.amount ELSE 0 END * 100))
                                        FROM payments WHERE payments.order_id = orders.id), 0)
                            + coalesce((SELECT sum(ROUND(refunds.refunded_amount * 100))
                                        FROM refunds WHERE refunds.order_id = orders.id), 0)
                        )), 0)) AS INTEGER) AS debt_minor
                 FROM orders WHERE ${currentWhere.sql} AND orders.operational_status != 'cancelled'`,
                ).bind(...currentWhere.parameters).first(),
        ]);
        financial = { sales: sales?.amount ?? "0.00", orders_count: sales?.orders_count ?? 0,
            cash_collections: cash?.amount ?? "0.00", verified_bank_collections: transfers?.amount ?? "0.00",
            refunds: refunds?.amount ?? "0.00", expenses: expenses?.amount ?? "0.00" };
        financial.sales_by_school = await env.DB.prepare(
            `SELECT coalesce(schools.name, 'طلبات فردية أو بلا مدرسة') AS school_name,
                    count(*) AS orders_count, printf('%.2f', sum(orders.approved_total)) AS sales
             FROM orders
             LEFT JOIN school_contracts ON school_contracts.order_id = orders.id
             LEFT JOIN schools ON schools.id = school_contracts.school_id
             WHERE ${where.sql} AND orders.created_on >= ? AND orders.created_on <= ?
             GROUP BY schools.name ORDER BY sum(orders.approved_total) DESC`,
        ).bind(...params, from, to).all().then((result) => result.results ?? []);
        const debtMinor = BigInt(debts?.debt_minor ?? 0);
        financial.debts = `${debtMinor / 100n}.${String(debtMinor % 100n).padStart(2, "0")}`;
    }

    return tools.json({
        from,
        to,
        financial,
        orders: orders.results ?? [],
        order_states: states.results ?? [],
        production_jobs: jobs.results ?? [],
        inventory: inventory.results ?? [],
        inventory_movements: movements.results ?? [],
    });
}

async function auditLog(request, env, url, tools) {
    const allowed = await access(request, env, tools, ["audit.view"]);
    if (allowed.response) return allowed.response;
    const action = url.searchParams.get("action")?.slice(0, 120) ?? "";
    const limit = Math.min(100, Math.max(1, Number(url.searchParams.get("limit") ?? 50) || 50));
    const result = await env.DB.prepare(
        `SELECT audit_logs.id, audit_logs.user_id, users.name AS user_name, audit_logs.action,
                audit_logs.subject_type, audit_logs.subject_id, audit_logs.before_values,
                audit_logs.after_values, audit_logs.reason, audit_logs.request_id, audit_logs.occurred_at
         FROM audit_logs LEFT JOIN users ON users.id = audit_logs.user_id
         WHERE (? = '' OR audit_logs.action = ?)
         ORDER BY audit_logs.occurred_at DESC, audit_logs.id DESC LIMIT ?`,
    ).bind(action, action, limit).all();
    return tools.json({ data: result.results ?? [] });
}

export async function handleReportsApi(request, env, url, tools) {
    if (url.pathname === "/api/reports") {
        if (request.method !== "GET") return tools.json({ error: "method_not_allowed" }, 405);
        return getReport(request, env, url, tools);
    }
    if (url.pathname === "/api/audit") {
        if (request.method !== "GET") return tools.json({ error: "method_not_allowed" }, 405);
        return auditLog(request, env, url, tools);
    }
    return null;
}
