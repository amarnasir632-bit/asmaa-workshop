const measurementFields = [
    "shoulder_cm",
    "chest_cm",
    "sleeve_cm",
    "waist_circumference_cm",
    "length_cm",
];

function hasPermission(user, permission) {
    return user.permissions.includes(permission);
}

async function requireAccess(request, env, permission, tools, write = false) {
    const user = await tools.authenticatedUser(request, env);
    if (!user) return { response: tools.json({ error: "unauthenticated" }, 401) };
    if (!hasPermission(user, permission)) {
        return { response: tools.json({ error: "forbidden" }, 403) };
    }
    if (write && !tools.csrfValid(request, user)) {
        return { response: tools.json({ error: "csrf_failed" }, 419) };
    }

    return { user };
}

async function dataForWrite(request, tools) {
    const parsed = await tools.requestData(request);
    return parsed.error
        ? { response: tools.json({ error: parsed.error }, parsed.status) }
        : { data: parsed.data };
}

function validateGuardian(data) {
    const { name, phone, alternate_phone: alternatePhone = null, notes = null } = data;
    if (
        typeof name !== "string" || !name.trim() || name.trim().length > 160
        || typeof phone !== "string" || !phone.trim() || phone.trim().length > 40
        || (alternatePhone !== null && (typeof alternatePhone !== "string" || alternatePhone.length > 40))
        || (notes !== null && (typeof notes !== "string" || notes.length > 2000))
    ) {
        return null;
    }

    return {
        name: name.trim(),
        phone: phone.trim(),
        alternate_phone: alternatePhone?.trim() || null,
        notes: notes?.trim() || null,
    };
}

function validateChild(data) {
    const { name, school_id: schoolId = null, grade_id: gradeId = null, notes = null } = data;
    const school = schoolId === null || schoolId === "" ? null : Number(schoolId);
    const grade = gradeId === null || gradeId === "" ? null : Number(gradeId);
    if (
        typeof name !== "string" || !name.trim() || name.trim().length > 160
        || (school !== null && (!Number.isSafeInteger(school) || school < 1))
        || (grade !== null && (!Number.isSafeInteger(grade) || grade < 1))
        || (grade !== null && school === null)
        || (notes !== null && (typeof notes !== "string" || notes.length > 2000))
    ) {
        return null;
    }

    return { name: name.trim(), schoolId: school, gradeId: grade, notes: notes?.trim() || null };
}

function validateMeasurements(data) {
    const keys = Object.keys(data);
    if (keys.length === 0 || keys.some((key) => !measurementFields.includes(key))) return null;

    const values = {};
    for (const field of keys) {
        const value = data[field];
        if (value === null || value === "") {
            values[field] = null;
            continue;
        }
        const numeric = Number(value);
        if (!Number.isFinite(numeric) || numeric <= 0 || numeric > 500) return null;
        values[field] = numeric;
    }

    return values;
}

async function listGuardians(request, env, url, user, tools) {
    const search = (url.searchParams.get("q") ?? "").trim().slice(0, 160);
    const page = Math.max(1, Math.min(10_000, Number(url.searchParams.get("page") ?? 1) || 1));
    const limit = Math.max(1, Math.min(100, Number(url.searchParams.get("limit") ?? 20) || 20));
    const offset = (page - 1) * limit;
    const pattern = `%${search.replaceAll("\\", "\\\\").replaceAll("%", "\\%").replaceAll("_", "\\_")}%`;
    const result = await env.DB.prepare(
        `SELECT guardians.id, guardians.name, guardians.phone, guardians.alternate_phone,
                guardians.notes, guardians.created_at, guardians.updated_at,
                (SELECT count(*) FROM children
                 WHERE children.guardian_id = guardians.id AND children.deleted_at IS NULL) AS children_count
         FROM guardians
         WHERE guardians.deleted_at IS NULL
           AND (? = '' OR guardians.name LIKE ? ESCAPE '\\'
                OR guardians.phone LIKE ? ESCAPE '\\'
                OR EXISTS (
                    SELECT 1 FROM children
                    WHERE children.guardian_id = guardians.id
                      AND children.deleted_at IS NULL
                      AND children.name LIKE ? ESCAPE '\\'
                ))
         ORDER BY guardians.name, guardians.id
         LIMIT ? OFFSET ?`,
    ).bind(search, pattern, pattern, pattern, limit, offset).all();
    const total = await env.DB.prepare(
        `SELECT count(*) AS total
         FROM guardians
         WHERE deleted_at IS NULL
           AND (? = '' OR name LIKE ? ESCAPE '\\' OR phone LIKE ? ESCAPE '\\'
                OR EXISTS (
                    SELECT 1 FROM children
                    WHERE children.guardian_id = guardians.id AND children.deleted_at IS NULL
                      AND children.name LIKE ? ESCAPE '\\'
                ))`,
    ).bind(search, pattern, pattern, pattern).first();

    return tools.json({
        data: result.results ?? [],
        pagination: { page, limit, total: total?.total ?? 0 },
        permissions: user.permissions,
    });
}

async function createGuardian(request, env, user, tools) {
    const parsed = await dataForWrite(request, tools);
    if (parsed.response) return parsed.response;
    const guardian = validateGuardian(parsed.data);
    if (!guardian) return tools.json({ error: "invalid_guardian_data" }, 422);

    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(
            `INSERT INTO guardians (name, phone, alternate_phone, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)`,
        ).bind(guardian.name, guardian.phone, guardian.alternate_phone, guardian.notes, now, now),
        env.DB.prepare(
            `INSERT INTO audit_logs (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             VALUES (?, 'guardian_created', 'App\\\\Models\\\\Guardian', last_insert_rowid(), ?, ?, ?)`,
        ).bind(user.id, JSON.stringify(Object.keys(guardian)), request.headers.get("cf-ray"), now),
    ]);

    return tools.json({ id: results[0].meta.last_row_id, ...guardian }, 201);
}

async function showGuardian(id, env, tools) {
    const guardian = await env.DB.prepare(
        "SELECT * FROM guardians WHERE id = ? AND deleted_at IS NULL",
    ).bind(id).first();
    if (!guardian) return tools.json({ error: "not_found" }, 404);

    const children = await env.DB.prepare(
        `SELECT children.id, children.name, children.school_id, schools.name AS school_name,
                children.grade_id, grades.name AS grade_name, children.notes,
                current_measurements.shoulder_cm, current_measurements.chest_cm,
                current_measurements.sleeve_cm, current_measurements.waist_circumference_cm,
                current_measurements.length_cm
         FROM children
         LEFT JOIN schools ON schools.id = children.school_id
         LEFT JOIN grades ON grades.id = children.grade_id
         LEFT JOIN current_measurements ON current_measurements.child_id = children.id
         WHERE children.guardian_id = ? AND children.deleted_at IS NULL
         ORDER BY children.name, children.id`,
    ).bind(id).all();
    const counts = await env.DB.prepare(
        "SELECT count(*) AS orders_count FROM orders WHERE guardian_id = ? AND deleted_at IS NULL",
    ).bind(id).first();

    return tools.json({ ...guardian, children: children.results ?? [], orders_count: counts?.orders_count ?? 0 });
}

async function updateGuardian(request, id, env, user, tools) {
    const parsed = await dataForWrite(request, tools);
    if (parsed.response) return parsed.response;
    const guardian = validateGuardian(parsed.data);
    if (!guardian) return tools.json({ error: "invalid_guardian_data" }, 422);

    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(
            `UPDATE guardians
             SET name = ?, phone = ?, alternate_phone = ?, notes = ?, updated_at = ?
             WHERE id = ? AND deleted_at IS NULL`,
        ).bind(guardian.name, guardian.phone, guardian.alternate_phone, guardian.notes, now, id),
        env.DB.prepare(
            `INSERT INTO audit_logs (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, 'guardian_updated', 'App\\\\Models\\\\Guardian', ?, ?, ?, ?
             WHERE changes() > 0`,
        ).bind(user.id, id, JSON.stringify(Object.keys(guardian)), request.headers.get("cf-ray"), now),
    ]);
    if ((results[0]?.meta?.changes ?? 0) === 0) return tools.json({ error: "not_found" }, 404);

    return tools.json({ id: Number(id), ...guardian });
}

async function archiveGuardian(request, id, env, user, tools) {
    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(
            "UPDATE guardians SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL",
        ).bind(now, now, id),
        env.DB.prepare(
            `INSERT INTO audit_logs (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, 'guardian_archived', 'App\\\\Models\\\\Guardian', ?, '["deleted_at"]', ?, ?
             WHERE changes() > 0`,
        ).bind(user.id, id, request.headers.get("cf-ray"), now),
    ]);
    if ((results[0]?.meta?.changes ?? 0) === 0) return tools.json({ error: "not_found" }, 404);

    return tools.json({ status: "archived" });
}

async function createChild(request, guardianId, env, user, tools) {
    const parsed = await dataForWrite(request, tools);
    if (parsed.response) return parsed.response;
    const child = validateChild(parsed.data);
    if (!child) return tools.json({ error: "invalid_child_data" }, 422);

    const guardian = await env.DB.prepare(
        "SELECT id FROM guardians WHERE id = ? AND deleted_at IS NULL",
    ).bind(guardianId).first();
    if (!guardian) return tools.json({ error: "guardian_not_found" }, 404);

    if (child.schoolId !== null) {
        const school = await env.DB.prepare(
            "SELECT id FROM schools WHERE id = ? AND is_active = 1",
        ).bind(child.schoolId).first();
        if (!school) return tools.json({ error: "school_not_found" }, 422);
    }
    if (child.gradeId !== null) {
        const grade = await env.DB.prepare(
            "SELECT id FROM grades WHERE id = ? AND school_id = ? AND is_active = 1",
        ).bind(child.gradeId, child.schoolId).first();
        if (!grade) return tools.json({ error: "grade_school_mismatch" }, 422);
    }

    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(
            `INSERT INTO children (guardian_id, school_id, grade_id, name, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)`,
        ).bind(guardianId, child.schoolId, child.gradeId, child.name, child.notes, now, now),
        env.DB.prepare(
            `INSERT INTO audit_logs (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             VALUES (?, 'child_created', 'App\\\\Models\\\\Child', last_insert_rowid(), ?, ?, ?)`,
        ).bind(user.id, JSON.stringify({ name: child.name, school_id: child.schoolId, grade_id: child.gradeId }), request.headers.get("cf-ray"), now),
    ]);

    return tools.json({ id: results[0].meta.last_row_id, guardian_id: Number(guardianId), ...child }, 201);
}

async function archiveChild(request, childId, env, user, tools) {
    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(
            "UPDATE children SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL",
        ).bind(now, now, childId),
        env.DB.prepare(
            `INSERT INTO audit_logs (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, 'child_archived', 'App\\\\Models\\\\Child', ?, '["deleted_at"]', ?, ?
             WHERE changes() > 0`,
        ).bind(user.id, childId, request.headers.get("cf-ray"), now),
    ]);
    if ((results[0]?.meta?.changes ?? 0) === 0) return tools.json({ error: "not_found" }, 404);

    return tools.json({ status: "archived" });
}

async function updateMeasurements(request, childId, env, user, tools) {
    const parsed = await dataForWrite(request, tools);
    if (parsed.response) return parsed.response;
    const measurements = validateMeasurements(parsed.data);
    if (!measurements) return tools.json({ error: "invalid_measurements" }, 422);
    const child = await env.DB.prepare(
        "SELECT id FROM children WHERE id = ? AND deleted_at IS NULL",
    ).bind(childId).first();
    if (!child) return tools.json({ error: "child_not_found" }, 404);

    const now = new Date().toISOString();
    const fields = Object.keys(measurements);
    const updates = fields.map((field) => `${field} = excluded.${field}`).join(", ");
    const values = fields.map((field) => measurements[field]);
    await env.DB.batch([
        env.DB.prepare(
            `INSERT INTO current_measurements
                (child_id, ${fields.join(", ")}, updated_by, created_at, updated_at)
             VALUES (?, ${fields.map(() => "?").join(", ")}, ?, ?, ?)
             ON CONFLICT (child_id) DO UPDATE SET ${updates}, updated_by = excluded.updated_by, updated_at = excluded.updated_at`,
        ).bind(childId, ...values, user.id, now, now),
        env.DB.prepare(
            `INSERT INTO audit_logs (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             VALUES (?, 'child_measurements_updated', 'App\\\\Models\\\\CurrentMeasurement',
                     (SELECT id FROM current_measurements WHERE child_id = ?), ?, ?, ?)`,
        ).bind(user.id, childId, JSON.stringify(measurements), request.headers.get("cf-ray"), now),
    ]);

    return tools.json({ child_id: Number(childId), ...measurements });
}

export async function handleCustomerApi(request, env, url, tools) {
    let match;

    if (url.pathname === "/api/guardians" && request.method === "GET") {
        const access = await requireAccess(request, env, "customers.view", tools);
        return access.response ?? listGuardians(request, env, url, access.user, tools);
    }
    if (url.pathname === "/api/guardians" && request.method === "POST") {
        const access = await requireAccess(request, env, "customers.create", tools, true);
        return access.response ?? createGuardian(request, env, access.user, tools);
    }
    if ((match = url.pathname.match(/^\/api\/guardians\/(\d+)$/))) {
        const access = await requireAccess(
            request,
            env,
            request.method === "GET" ? "customers.view" : "customers.update",
            tools,
            request.method !== "GET",
        );
        if (access.response) return access.response;
        if (request.method === "GET") return showGuardian(Number(match[1]), env, tools);
        if (request.method === "PATCH") return updateGuardian(request, Number(match[1]), env, access.user, tools);
        return tools.json({ error: "method_not_allowed" }, 405);
    }
    if ((match = url.pathname.match(/^\/api\/guardians\/(\d+)\/archive$/)) && request.method === "POST") {
        const access = await requireAccess(request, env, "customers.update", tools, true);
        return access.response ?? archiveGuardian(request, Number(match[1]), env, access.user, tools);
    }
    if ((match = url.pathname.match(/^\/api\/guardians\/(\d+)\/children$/)) && request.method === "POST") {
        const access = await requireAccess(request, env, "customers.create", tools, true);
        return access.response ?? createChild(request, Number(match[1]), env, access.user, tools);
    }
    if ((match = url.pathname.match(/^\/api\/children\/(\d+)\/measurements$/)) && request.method === "POST") {
        const access = await requireAccess(request, env, "measurements.update", tools, true);
        return access.response ?? updateMeasurements(request, Number(match[1]), env, access.user, tools);
    }
    if ((match = url.pathname.match(/^\/api\/children\/(\d+)\/archive$/)) && request.method === "POST") {
        const access = await requireAccess(request, env, "customers.update", tools, true);
        return access.response ?? archiveChild(request, Number(match[1]), env, access.user, tools);
    }

    return null;
}
