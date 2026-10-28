const recordTypes = [
    "customer",
    "child_measurements",
    "order",
    "payment",
    "refund",
    "expense",
    "inventory_observation",
    "financial_balance",
];
const confidenceLevels = ["confirmed", "incomplete", "conflicting", "unverified"];
const reviewStatuses = ["reviewed", "needs_correction", "rejected"];
const issueTypes = ["missing", "conflicting", "unverified", "duplicate_candidate"];

async function authorize(request, env, tools, write) {
    const user = await tools.authenticatedUser(request, env);
    if (!user) return { response: tools.json({ error: "unauthenticated" }, 401) };
    if (!user.permissions.includes("history.manage")) return { response: tools.json({ error: "forbidden" }, 403) };
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

function validDate(value, allowNull = false) {
    if (allowNull && (value === undefined || value === null || value === "")) return null;
    if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return undefined;
    const parsed = new Date(`${value}T00:00:00.000Z`);
    return !Number.isNaN(parsed.valueOf()) && parsed.toISOString().slice(0, 10) === value ? value : undefined;
}

function audit(env, request, userId, action, type, subjectId, after, reason, now) {
    return env.DB.prepare(
        `INSERT INTO audit_logs
            (user_id, action, subject_type, subject_id, after_values, reason, request_id, occurred_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    ).bind(userId, action, type, subjectId, JSON.stringify(after), reason, request.headers.get("cf-ray"), now);
}

async function listBatches(env, tools) {
    const result = await env.DB.prepare(
        `SELECT batches.id, batches.source_description, batches.imported_on, batches.records_seen,
                batches.records_imported, batches.records_flagged, batches.notes,
                users.name AS imported_by_name,
                count(DISTINCT records.id) AS record_count,
                count(DISTINCT issues.id) AS issue_count
         FROM historical_import_batches AS batches
         JOIN users ON users.id = batches.imported_by
         LEFT JOIN historical_import_records AS records ON records.historical_import_batch_id = batches.id
         LEFT JOIN historical_record_issues AS issues ON issues.historical_import_batch_id = batches.id
         GROUP BY batches.id ORDER BY batches.id DESC LIMIT 100`,
    ).all();
    return tools.json({ data: result.results ?? [], record_types: recordTypes });
}

async function createBatch(request, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const source = parsed.data.source_description;
    const importedOn = validDate(parsed.data.imported_on);
    const notes = optionalText(parsed.data.notes, 5000);
    if (typeof source !== "string" || !source.trim() || source.trim().length > 180 || !importedOn || notes === undefined) {
        return tools.json({ error: "invalid_history_batch" }, 422);
    }
    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(
            `INSERT INTO historical_import_batches
                (source_description, imported_on, imported_by, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)`,
        ).bind(source.trim(), importedOn, user.id, notes, now, now),
        env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             VALUES (?, 'history.batch_created', 'App\\\\Models\\\\HistoricalImportBatch', last_insert_rowid(), ?, ?, ?)`,
        ).bind(user.id, JSON.stringify({ source_description: source.trim(), imported_on: importedOn }), request.headers.get("cf-ray"), now),
    ]);
    return tools.json({ id: results[0].meta.last_row_id, source_description: source.trim(), imported_on: importedOn }, 201);
}

async function showBatch(id, env, tools) {
    const batch = await env.DB.prepare(
        `SELECT batches.id, batches.source_description, batches.imported_on, batches.records_seen,
                batches.records_imported, batches.records_flagged, batches.notes, users.name AS imported_by_name
         FROM historical_import_batches AS batches JOIN users ON users.id = batches.imported_by
         WHERE batches.id = ?`,
    ).bind(id).first();
    if (!batch) return tools.json({ error: "not_found" }, 404);
    const [records, issues] = await Promise.all([
        env.DB.prepare(
            `SELECT records.id, records.record_type, records.source_reference, records.original_date,
                    records.transcription, records.confidence, records.review_status, records.reviewed_by,
                    records.reviewed_at, records.review_notes, records.created_at,
                    entered.name AS entered_by_name, reviewed.name AS reviewed_by_name
             FROM historical_import_records AS records
             JOIN users AS entered ON entered.id = records.entered_by
             LEFT JOIN users AS reviewed ON reviewed.id = records.reviewed_by
             WHERE records.historical_import_batch_id = ? ORDER BY records.id DESC LIMIT 500`,
        ).bind(id).all(),
        env.DB.prepare(
            `SELECT issues.id, issues.record_type, issues.source_reference, issues.issue_type,
                    issues.description, issues.resolution, issues.resolved_at, users.name AS resolved_by_name
             FROM historical_record_issues AS issues LEFT JOIN users ON users.id = issues.resolved_by
             WHERE issues.historical_import_batch_id = ? ORDER BY issues.id DESC LIMIT 500`,
        ).bind(id).all(),
    ]);
    return tools.json({ ...batch, records: records.results ?? [], issues: issues.results ?? [] });
}

async function addRecord(request, batchId, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const data = parsed.data;
    const sourceReference = optionalText(data.source_reference, 120);
    const originalDate = validDate(data.original_date, true);
    const transcription = data.transcription;
    if (!recordTypes.includes(data.record_type)
        || !confidenceLevels.includes(data.confidence)
        || sourceReference === undefined || originalDate === undefined
        || typeof transcription !== "string" || !transcription.trim() || transcription.length > 50_000) {
        return tools.json({ error: "invalid_history_record" }, 422);
    }
    const batch = await env.DB.prepare("SELECT id FROM historical_import_batches WHERE id = ?").bind(batchId).first();
    if (!batch) return tools.json({ error: "not_found" }, 404);
    const cleanText = transcription.trim();
    const fingerprint = await tools.sha256Hex([
        data.record_type,
        sourceReference ?? "",
        originalDate ?? "",
        cleanText.replace(/\s+/gu, " "),
    ].join("|"));
    const duplicate = await env.DB.prepare(
        "SELECT id FROM historical_import_records WHERE fingerprint = ?",
    ).bind(fingerprint).first();
    const now = new Date().toISOString();
    const statements = [
        env.DB.prepare(
            "UPDATE historical_import_batches SET records_seen = records_seen + 1, updated_at = ? WHERE id = ?",
        ).bind(now, batchId),
    ];
    if (duplicate) {
        statements.push(env.DB.prepare(
            `UPDATE historical_import_batches SET records_flagged = records_flagged + 1, updated_at = ? WHERE id = ?`,
        ).bind(now, batchId));
        statements.push(env.DB.prepare(
            `INSERT INTO historical_record_issues
                (historical_import_batch_id, record_type, source_reference, issue_type, description, created_at, updated_at)
             VALUES (?, ?, ?, 'duplicate_candidate', ?, ?, ?)`,
        ).bind(batchId, data.record_type, sourceReference, "تطابق نص السجل وبيانات مصدره مع سجل مرحّل سابقاً؛ راجع التشابه قبل إضافة نسخة أخرى.", now, now));
    } else {
        statements.push(env.DB.prepare(
            `INSERT INTO historical_import_records
                (historical_import_batch_id, record_type, source_reference, original_date, transcription,
                 fingerprint, confidence, review_status, entered_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)`,
        ).bind(batchId, data.record_type, sourceReference, originalDate, cleanText, fingerprint, data.confidence, user.id, now, now));
        statements.push(env.DB.prepare(
            `UPDATE historical_import_batches
             SET records_imported = records_imported + 1, updated_at = ? WHERE id = ?`,
        ).bind(now, batchId));
        if (data.confidence !== "confirmed") {
            const issueType = data.confidence === "conflicting" ? "conflicting"
                : data.confidence === "incomplete" ? "missing" : "unverified";
            statements.push(env.DB.prepare(
                `UPDATE historical_import_batches SET records_flagged = records_flagged + 1, updated_at = ? WHERE id = ?`,
            ).bind(now, batchId));
            statements.push(env.DB.prepare(
                `INSERT INTO historical_record_issues
                    (historical_import_batch_id, record_type, source_reference, issue_type, description, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)`,
            ).bind(batchId, data.record_type, sourceReference, issueType, "السجل يحتاج مراجعة المدير قبل اعتماده في الأرشيف التاريخي.", now, now));
        }
    }
    statements.push(duplicate
        ? env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, 'history.duplicate_flagged', 'App\\\\Models\\\\HistoricalRecordIssue', id, ?, ?, ?
             FROM historical_record_issues
             WHERE historical_import_batch_id = ? AND issue_type = 'duplicate_candidate'
               AND source_reference IS ? ORDER BY id DESC LIMIT 1`,
        ).bind(user.id, JSON.stringify({ batch_id: batchId, record_type: data.record_type, fingerprint }), request.headers.get("cf-ray"), now, batchId, sourceReference)
        : env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             SELECT ?, 'history.record_added', 'App\\\\Models\\\\HistoricalImportRecord', id, ?, ?, ?
             FROM historical_import_records WHERE historical_import_records.fingerprint = ?`,
        ).bind(user.id, JSON.stringify({ batch_id: batchId, record_type: data.record_type, source_reference: sourceReference, fingerprint }), request.headers.get("cf-ray"), now, fingerprint));
    try {
        const results = await env.DB.batch(statements);
        return tools.json(duplicate
            ? { status: "duplicate_flagged", issue_id: results.at(-2)?.meta?.last_row_id }
            : { status: "record_added", id: results[1].meta.last_row_id, fingerprint }, duplicate ? 200 : 201);
    } catch (error) {
        console.error("D1 historical record transaction failed", error instanceof Error ? error.message : "unknown");
        return tools.json({ error: "history_record_transaction_failed" }, 409);
    }
}

async function reviewRecord(request, id, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const status = parsed.data.review_status;
    const notes = parsed.data.review_notes;
    if (!reviewStatuses.includes(status) || typeof notes !== "string" || !notes.trim() || notes.length > 5000) {
        return tools.json({ error: "invalid_history_review" }, 422);
    }
    const now = new Date().toISOString();
    const results = await env.DB.batch([
        env.DB.prepare(
            `UPDATE historical_import_records
             SET review_status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ?
             WHERE id = ?`,
        ).bind(status, notes.trim(), user.id, now, now, id),
        env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, reason, request_id, occurred_at)
             SELECT ?, 'history.record_reviewed', 'App\\\\Models\\\\HistoricalImportRecord', id, ?, ?, ?, ?
             FROM historical_import_records WHERE id = ? AND changes() > 0`,
        ).bind(user.id, JSON.stringify({ review_status: status, review_notes: notes.trim() }), notes.trim(), request.headers.get("cf-ray"), now, id),
    ]);
    if ((results[0]?.meta?.changes ?? 0) !== 1) return tools.json({ error: "not_found" }, 404);
    return tools.json({ id, review_status: status, reviewed_at: now });
}

async function correctRecord(request, id, env, user, tools) {
    const parsed = await body(request, tools);
    if (parsed.response) return parsed.response;
    const transcription = parsed.data.transcription;
    const reason = parsed.data.reason;
    if (typeof transcription !== "string" || !transcription.trim() || transcription.length > 50_000
        || typeof reason !== "string" || !reason.trim() || reason.length > 2000) {
        return tools.json({ error: "invalid_history_correction" }, 422);
    }
    const record = await env.DB.prepare(
        `SELECT id, record_type, source_reference, original_date, transcription
         FROM historical_import_records WHERE id = ?`,
    ).bind(id).first();
    if (!record) return tools.json({ error: "not_found" }, 404);
    const cleanText = transcription.trim();
    const fingerprint = await tools.sha256Hex(JSON.stringify([
        record.record_type,
        record.source_reference ?? "",
        record.original_date ?? "",
        cleanText.replace(/\s+/gu, " "),
    ]));
    const duplicate = await env.DB.prepare(
        "SELECT id FROM historical_import_records WHERE fingerprint = ? AND id != ?",
    ).bind(fingerprint, id).first();
    if (duplicate) return tools.json({ error: "history_duplicate_correction" }, 409);
    const now = new Date().toISOString();
    try {
        const results = await env.DB.batch([
            env.DB.prepare(
                `INSERT INTO historical_record_revisions
                    (historical_import_record_id, previous_transcription, corrected_transcription,
                     reason, corrected_by, corrected_at)
                 VALUES (?, ?, ?, ?, ?, ?)`,
            ).bind(id, record.transcription, cleanText, reason.trim(), user.id, now),
            env.DB.prepare(
                `UPDATE historical_import_records
                 SET transcription = ?, fingerprint = ?, confidence = 'unverified', review_status = 'pending',
                     reviewed_by = NULL, reviewed_at = NULL, review_notes = NULL, updated_at = ?
                 WHERE id = ?`,
            ).bind(cleanText, fingerprint, now, id),
            audit(env, request, user.id, "history.record_corrected", "App\\\\Models\\\\HistoricalImportRecord",
                id, { fingerprint, review_status: "pending" }, reason.trim(), now),
        ]);
        return tools.json({ id, fingerprint, review_status: "pending", revision_id: results[0].meta.last_row_id });
    } catch (error) {
        console.error("D1 historical correction failed", error instanceof Error ? error.message : "unknown");
        return tools.json({ error: "history_correction_failed" }, 409);
    }
}

export async function handleHistoryApi(request, env, url, tools) {
    if (url.pathname === "/api/history") {
        const write = request.method !== "GET";
        const access = await authorize(request, env, tools, write);
        if (access.response) return access.response;
        if (request.method === "GET") return listBatches(env, tools);
        if (request.method === "POST") return createBatch(request, env, access.user, tools);
        return tools.json({ error: "method_not_allowed" }, 405);
    }
    const batch = url.pathname.match(/^\/api\/history\/(\d+)$/);
    if (batch) {
        if (request.method !== "GET") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, tools, false);
        return access.response ?? showBatch(Number(batch[1]), env, tools);
    }
    const record = url.pathname.match(/^\/api\/history\/(\d+)\/records$/);
    if (record) {
        if (request.method !== "POST") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, tools, true);
        return access.response ?? addRecord(request, Number(record[1]), env, access.user, tools);
    }
    const review = url.pathname.match(/^\/api\/history\/records\/(\d+)\/review$/);
    if (review) {
        if (request.method !== "PATCH") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, tools, true);
        return access.response ?? reviewRecord(request, Number(review[1]), env, access.user, tools);
    }
    const correction = url.pathname.match(/^\/api\/history\/records\/(\d+)\/correction$/);
    if (correction) {
        if (request.method !== "PATCH") return tools.json({ error: "method_not_allowed" }, 405);
        const access = await authorize(request, env, tools, true);
        return access.response ?? correctRecord(request, Number(correction[1]), env, access.user, tools);
    }
    return null;
}
