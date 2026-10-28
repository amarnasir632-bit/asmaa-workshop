async function access(request, env, tools, write = false) {
    const user = await tools.authenticatedUser(request, env);
    if (!user) return { response: tools.json({ error: "unauthenticated" }, 401) };
    if (!user.permissions.includes("users.manage")) {
        return { response: tools.json({ error: "forbidden" }, 403) };
    }
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

function normalizedStaff(data, creating) {
    const { name, username, email = null, role, is_active: isActive = true } = data;
    const normalizedUsername = typeof username === "string" ? username.trim().toLowerCase() : "";
    const normalizedEmail = typeof email === "string" && email.trim() !== "" ? email.trim().toLowerCase() : null;
    if (
        typeof name !== "string" || !name.trim() || name.trim().length > 160
        || !/^[a-z0-9._-]{3,80}$/.test(normalizedUsername)
        || (normalizedEmail !== null && (
            normalizedEmail.length > 190 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)
        ))
        || !["manager", "employee"].includes(role)
        || typeof isActive !== "boolean"
        || (!creating && typeof data.is_active !== "boolean")
    ) {
        return null;
    }

    if (creating && (!validPassword(data.password) || data.password !== data.password_confirmation)) {
        return null;
    }

    return {
        name: name.trim(),
        username: normalizedUsername,
        email: normalizedEmail,
        role,
        isActive,
        password: data.password,
    };
}

function validPassword(value) {
    const length = typeof value === "string" ? new TextEncoder().encode(value).length : 0;
    return length >= 12 && length <= 200;
}

async function listUsers(env, tools) {
    const result = await env.DB.prepare(
        `SELECT users.id, users.name, users.username, users.email, users.is_active,
                users.last_login_at, roles.name AS role
         FROM users
         LEFT JOIN user_roles ON user_roles.user_id = users.id
         LEFT JOIN roles ON roles.id = user_roles.role_id
         ORDER BY users.name, users.id`,
    ).all();

    return tools.json({ data: result.results ?? [] });
}

async function createUser(request, env, actor, tools) {
    const parsed = await parseBody(request, tools);
    if (parsed.response) return parsed.response;
    const staff = normalizedStaff(parsed.data, true);
    if (!staff) return tools.json({ error: "invalid_staff_data" }, 422);

    const passwordHash = await tools.hashPassword(staff.password);
    const now = new Date().toISOString();
    const result = await env.DB.batch([
        env.DB.prepare(
            `INSERT INTO users (name, username, email, password, is_active, created_at, updated_at)
             SELECT ?, ?, ?, ?, 1, ?, ?
             WHERE EXISTS (SELECT 1 FROM roles WHERE name = ?)
               AND NOT EXISTS (SELECT 1 FROM users WHERE username = ?)
               AND (? IS NULL OR NOT EXISTS (SELECT 1 FROM users WHERE email = ?))`,
        ).bind(staff.name, staff.username, staff.email, passwordHash, now, now, staff.role, staff.username, staff.email, staff.email),
        env.DB.prepare(
            `INSERT INTO user_roles (user_id, role_id, created_at, updated_at)
             SELECT users.id, roles.id, ?, ?
             FROM users CROSS JOIN roles
             WHERE users.username = ? AND roles.name = ? AND changes() > 0`,
        ).bind(now, now, staff.username, staff.role),
        env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, 'staff_user_created', 'App\\\\Models\\\\User',
                    (SELECT id FROM users WHERE username = ?), ?, ?, ?
             WHERE changes() > 0`,
        ).bind(actor.id, staff.username, JSON.stringify({
            name: staff.name,
            username: staff.username,
            email: staff.email,
            role: staff.role,
        }), request.headers.get("cf-ray"), now),
    ]);
    if ((result[0]?.meta?.changes ?? 0) !== 1) {
        return tools.json({ error: "username_or_email_in_use" }, 409);
    }

    return tools.json({ id: result[0].meta.last_row_id, ...staff, password: undefined }, 201);
}

async function updateUser(request, userId, env, actor, tools) {
    const parsed = await parseBody(request, tools);
    if (parsed.response) return parsed.response;
    const staff = normalizedStaff(parsed.data, false);
    if (!staff) return tools.json({ error: "invalid_staff_data" }, 422);
    if (Number(userId) === actor.id && (staff.role !== "manager" || !staff.isActive)) {
        return tools.json({ error: "cannot_disable_or_demote_self" }, 422);
    }

    const current = await env.DB.prepare(
        `SELECT users.id, users.username, users.email, users.is_active, roles.name AS role
         FROM users
         LEFT JOIN user_roles ON user_roles.user_id = users.id
         LEFT JOIN roles ON roles.id = user_roles.role_id
         WHERE users.id = ?`,
    ).bind(userId).first();
    if (!current) return tools.json({ error: "not_found" }, 404);
    const demotesActiveManager = current.role === "manager"
        && current.is_active
        && (staff.role !== "manager" || !staff.isActive);
    if (demotesActiveManager) {
        const managers = await env.DB.prepare(
            `SELECT count(*) AS total
             FROM user_roles
             JOIN roles ON roles.id = user_roles.role_id
             JOIN users ON users.id = user_roles.user_id
             WHERE roles.name = 'manager' AND users.is_active = 1 AND users.id != ?`,
        ).bind(userId).first();
        if ((managers?.total ?? 0) === 0) return tools.json({ error: "last_active_manager" }, 409);
    }

    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(
            `UPDATE users
             SET name = ?, username = ?, email = ?, is_active = ?, updated_at = ?
             WHERE id = ?
               AND NOT EXISTS (SELECT 1 FROM users WHERE username = ? AND id != ?)
               AND (? IS NULL OR NOT EXISTS (SELECT 1 FROM users WHERE email = ? AND id != ?))
               AND (
                   NOT EXISTS (
                       SELECT 1 FROM user_roles
                       JOIN roles ON roles.id = user_roles.role_id
                       WHERE user_roles.user_id = users.id AND roles.name = 'manager'
                   )
                   OR ? = 'manager' AND ? = 1
                   OR EXISTS (
                       SELECT 1 FROM users AS other
                       JOIN user_roles ON user_roles.user_id = other.id
                       JOIN roles ON roles.id = user_roles.role_id
                       WHERE other.id != users.id AND other.is_active = 1 AND roles.name = 'manager'
                   )
               )`,
        ).bind(
            staff.name,
            staff.username,
            staff.email,
            Number(staff.isActive),
            now,
            userId,
            staff.username,
            userId,
            staff.email,
            staff.email,
            userId,
            staff.role,
            Number(staff.isActive),
        ),
        env.DB.prepare(
            `INSERT INTO audit_logs (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, 'staff_user_updated', 'App\\\\Models\\\\User', ?, ?, ?, ?
             WHERE changes() > 0`,
        ).bind(actor.id, userId, JSON.stringify({
            name: staff.name,
            username: staff.username,
            email: staff.email,
            role: staff.role,
            is_active: staff.isActive,
        }), request.headers.get("cf-ray"), now),
        env.DB.prepare("DELETE FROM user_roles WHERE user_id = ? AND changes() > 0").bind(userId),
        env.DB.prepare(
            `INSERT INTO user_roles (user_id, role_id, created_at, updated_at)
             SELECT ?, roles.id, ?, ? FROM roles
             WHERE roles.name = ? AND changes() > 0`,
        ).bind(userId, now, now, staff.role),
        env.DB.prepare(
            "DELETE FROM sessions WHERE user_id = ? AND ? = 0 AND changes() > 0",
        ).bind(userId, Number(staff.isActive)),
    ]);

    if ((results[0]?.meta?.changes ?? 0) !== 1) {
        return tools.json({ error: "username_or_email_in_use_or_manager_protection" }, 409);
    }

    return tools.json({
        id: Number(userId),
        name: staff.name,
        username: staff.username,
        email: staff.email,
        role: staff.role,
        is_active: staff.isActive,
    });
}

export async function handleAdminApi(request, env, url, tools) {
    if (url.pathname === "/api/users") {
        const authorization = await access(request, env, tools, request.method !== "GET");
        if (authorization.response) return authorization.response;
        if (request.method === "GET") return listUsers(env, tools);
        if (request.method === "POST") return createUser(request, env, authorization.user, tools);
        return tools.json({ error: "method_not_allowed" }, 405);
    }

    const match = url.pathname.match(/^\/api\/users\/(\d+)$/);
    if (match) {
        if (request.method !== "PATCH") return tools.json({ error: "method_not_allowed" }, 405);
        const authorization = await access(request, env, tools, true);
        return authorization.response ?? updateUser(request, Number(match[1]), env, authorization.user, tools);
    }

    return null;
}
