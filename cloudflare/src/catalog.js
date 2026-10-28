function validText(value, maximum, required = false) {
    return typeof value === "string" && (!required || value.trim().length > 0) && value.length <= maximum;
}

function optionalText(value, maximum) {
    if (value === undefined || value === null || value === "") return null;
    return validText(value, maximum) ? value.trim() : undefined;
}

function optionalId(value) {
    if (value === undefined || value === null || value === "") return null;
    const id = Number(value);
    return Number.isSafeInteger(id) && id > 0 ? id : undefined;
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

async function catalogData(env) {
    const [schools, grades, garmentTypes, variants, expenseCategories] = await Promise.all([
        env.DB.prepare("SELECT id, name, phone, address, is_active FROM schools ORDER BY name, id").all(),
        env.DB.prepare("SELECT id, school_id, name, is_active FROM grades ORDER BY name, id").all(),
        env.DB.prepare("SELECT id, name, requires_size, requires_color, is_active FROM garment_types ORDER BY name, id").all(),
        env.DB.prepare(
            `SELECT inventory_variants.id, inventory_variants.school_id, schools.name AS school_name,
                    inventory_variants.grade_id, grades.name AS grade_name,
                    inventory_variants.garment_type_id, garment_types.name AS garment_type_name,
                    inventory_variants.size, inventory_variants.color, inventory_variants.sku,
                    inventory_variants.description, inventory_variants.is_active,
                    coalesce(inventory_balances.quantity_available, 0) AS quantity_available
             FROM inventory_variants
             JOIN garment_types ON garment_types.id = inventory_variants.garment_type_id
             LEFT JOIN schools ON schools.id = inventory_variants.school_id
             LEFT JOIN grades ON grades.id = inventory_variants.grade_id
             LEFT JOIN inventory_balances ON inventory_balances.inventory_variant_id = inventory_variants.id
             ORDER BY inventory_variants.id DESC LIMIT 500`,
        ).all(),
        env.DB.prepare("SELECT id, name, is_active FROM expense_categories ORDER BY name, id").all(),
    ]);

    return {
        schools: schools.results ?? [],
        grades: grades.results ?? [],
        garment_types: garmentTypes.results ?? [],
        variants: variants.results ?? [],
        expense_categories: expenseCategories.results ?? [],
    };
}

async function auditedCreate(env, user, request, insert, values, action, subjectType, afterValues) {
    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(insert).bind(...values, now, now),
        env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             VALUES (?, ?, ?, last_insert_rowid(), ?, ?, ?)`,
        ).bind(
            user.id,
            action,
            subjectType,
            JSON.stringify(afterValues),
            request.headers.get("cf-ray"),
            now,
        ),
    ]);

    return { id: results[0].meta.last_row_id, created_at: now, updated_at: now };
}

async function createSchool(request, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const phone = optionalText(data.phone, 40);
    const address = optionalText(data.address, 1000);
    if (
        !validText(data.name, 180, true)
        || phone === undefined
        || address === undefined
    ) return tools.json({ error: "invalid_school_data" }, 422);

    const name = data.name.trim();
    const duplicate = await env.DB.prepare("SELECT id FROM schools WHERE name = ?").bind(name).first();
    if (duplicate) return tools.json({ error: "school_name_in_use" }, 409);

    const created = await auditedCreate(
        env,
        user,
        request,
        `INSERT INTO schools (name, phone, address, is_active, created_at, updated_at)
         VALUES (?, ?, ?, 1, ?, ?)`,
        [name, phone, address],
        "school_created",
        "App\\\\Models\\\\School",
        ["name", "phone", "address"],
    );
    return tools.json({ ...created, name, phone, address, is_active: 1 }, 201);
}

async function createGrade(request, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const schoolId = optionalId(parsed.data.school_id);
    const name = parsed.data.name;
    if (!schoolId || !validText(name, 100, true)) return tools.json({ error: "invalid_grade_data" }, 422);

    const school = await env.DB.prepare("SELECT id FROM schools WHERE id = ? AND is_active = 1").bind(schoolId).first();
    if (!school) return tools.json({ error: "school_not_found" }, 422);
    const duplicate = await env.DB.prepare("SELECT id FROM grades WHERE school_id = ? AND name = ?")
        .bind(schoolId, name.trim()).first();
    if (duplicate) return tools.json({ error: "grade_already_exists" }, 409);

    const created = await auditedCreate(
        env,
        user,
        request,
        `INSERT INTO grades (school_id, name, is_active, created_at, updated_at)
         VALUES (?, ?, 1, ?, ?)`,
        [schoolId, name.trim()],
        "grade_created",
        "App\\\\Models\\\\Grade",
        ["school_id", "name"],
    );
    return tools.json({ ...created, school_id: schoolId, name: name.trim(), is_active: 1 }, 201);
}

async function createGarmentType(request, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const { name } = parsed.data;
    const requiresSize = parsed.data.requires_size ?? false;
    const requiresColor = parsed.data.requires_color ?? false;
    if (
        !validText(name, 120, true)
        || typeof requiresSize !== "boolean"
        || typeof requiresColor !== "boolean"
    ) return tools.json({ error: "invalid_garment_type_data" }, 422);

    const normalizedName = name.trim();
    const duplicate = await env.DB.prepare("SELECT id FROM garment_types WHERE name = ?")
        .bind(normalizedName).first();
    if (duplicate) return tools.json({ error: "garment_type_name_in_use" }, 409);
    const created = await auditedCreate(
        env,
        user,
        request,
        `INSERT INTO garment_types (name, requires_size, requires_color, is_active, created_at, updated_at)
         VALUES (?, ?, ?, 1, ?, ?)`,
        [normalizedName, Number(requiresSize), Number(requiresColor)],
        "garment_type_created",
        "App\\\\Models\\\\GarmentType",
        ["name", "requires_size", "requires_color"],
    );
    return tools.json({
        ...created,
        name: normalizedName,
        requires_size: Number(requiresSize),
        requires_color: Number(requiresColor),
        is_active: 1,
    }, 201);
}

async function createVariant(request, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const schoolId = optionalId(data.school_id);
    const gradeId = optionalId(data.grade_id);
    const typeId = optionalId(data.garment_type_id);
    const size = optionalText(data.size, 60);
    const color = optionalText(data.color, 80);
    const sku = optionalText(data.sku, 80);
    const description = optionalText(data.description, 180);
    if (
        schoolId === undefined || gradeId === undefined || !typeId
        || size === undefined || color === undefined || sku === undefined || description === undefined
    ) return tools.json({ error: "invalid_variant_data" }, 422);
    if (gradeId !== null && schoolId === null) return tools.json({ error: "school_required_for_grade" }, 422);

    const garmentType = await env.DB.prepare(
        "SELECT id, requires_size, requires_color FROM garment_types WHERE id = ? AND is_active = 1",
    ).bind(typeId).first();
    if (!garmentType) return tools.json({ error: "garment_type_not_found" }, 422);
    if ((garmentType.requires_size && !size) || (garmentType.requires_color && !color)) {
        return tools.json({ error: "required_variant_attribute_missing" }, 422);
    }
    if (schoolId !== null) {
        const school = await env.DB.prepare("SELECT id FROM schools WHERE id = ? AND is_active = 1")
            .bind(schoolId).first();
        if (!school) return tools.json({ error: "school_not_found" }, 422);
    }
    if (gradeId !== null) {
        const grade = await env.DB.prepare(
            "SELECT id FROM grades WHERE id = ? AND school_id = ? AND is_active = 1",
        ).bind(gradeId, schoolId).first();
        if (!grade) return tools.json({ error: "grade_school_mismatch" }, 422);
    }
    if (sku) {
        const duplicateSku = await env.DB.prepare("SELECT id FROM inventory_variants WHERE sku = ?")
            .bind(sku).first();
        if (duplicateSku) return tools.json({ error: "sku_in_use" }, 409);
    }

    const attributes = {
        school_id: schoolId,
        garment_type_id: typeId,
        grade_id: gradeId,
        size: size ?? "",
        color: color ?? "",
    };
    const attributesKey = await tools.sha256Hex(JSON.stringify(attributes));
    const duplicate = await env.DB.prepare(
        "SELECT id FROM inventory_variants WHERE attributes_key = ?",
    ).bind(attributesKey).first();
    if (duplicate) return tools.json({ error: "variant_already_exists" }, 409);

    const created = await auditedCreate(
        env,
        user,
        request,
        `INSERT INTO inventory_variants
            (school_id, garment_type_id, grade_id, size, color, sku, attributes_key, description, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)`,
        [schoolId, typeId, gradeId, size, color, sku, attributesKey, description],
        "inventory_variant_created",
        "App\\\\Models\\\\InventoryVariant",
        ["school_id", "garment_type_id", "grade_id", "size", "color", "sku", "description"],
    );
    return tools.json({ ...created, ...attributes, sku, description, is_active: 1 }, 201);
}

async function deactivate(request, env, user, table, id, tools) {
    const target = {
        schools: { action: "school_deactivated", subject: "App\\\\Models\\\\School" },
        grades: { action: "grade_deactivated", subject: "App\\\\Models\\\\Grade" },
        garment_types: { action: "garment_type_deactivated", subject: "App\\\\Models\\\\GarmentType" },
        inventory_variants: { action: "inventory_variant_deactivated", subject: "App\\\\Models\\\\InventoryVariant" },
    }[table];
    if (!target) return tools.json({ error: "not_found" }, 404);
    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(`UPDATE ${table} SET is_active = 0, updated_at = ? WHERE id = ? AND is_active = 1`)
            .bind(now, id),
        env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, ?, ?, ?, '["is_active"]', ?, ? WHERE changes() > 0`,
        ).bind(user.id, target.action, target.subject, id, request.headers.get("cf-ray"), now),
    ]);
    if ((results[0]?.meta?.changes ?? 0) === 0) return tools.json({ error: "not_found_or_inactive" }, 404);
    return tools.json({ status: "deactivated" });
}

export async function handleCatalogApi(request, env, url, tools) {
    if (url.pathname === "/api/catalog") {
        if (request.method !== "GET") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "catalog.view", tools, false);
        return access.response ?? tools.json(await catalogData(env));
    }

    const createRoutes = {
        "/api/catalog/schools": createSchool,
        "/api/catalog/grades": createGrade,
        "/api/catalog/garment-types": createGarmentType,
        "/api/catalog/variants": createVariant,
    };
    if (createRoutes[url.pathname]) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "catalog.manage", tools, true);
        return access.response ?? createRoutes[url.pathname](request, env, access.user, tools);
    }

    const match = url.pathname.match(/^\/api\/catalog\/(schools|grades|garment-types|variants)\/(\d+)\/deactivate$/);
    if (match) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, "catalog.manage", tools, true);
        if (access.response) return access.response;
        const table = {
            schools: "schools",
            grades: "grades",
            "garment-types": "garment_types",
            variants: "inventory_variants",
        }[match[1]];
        return deactivate(request, env, access.user, table, Number(match[2]), tools);
    }

    return null;
}
