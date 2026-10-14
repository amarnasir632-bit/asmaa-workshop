import {
    constantTimeEqual,
    hashPassword,
    randomToken,
    sessionCookie,
    sha256,
    sha256Hex,
    verifyPassword,
} from "./security.js";
import { handleCustomerApi } from "./customers.js";
import { handleAdminApi } from "./admin.js";
import { handleCatalogApi } from "./catalog.js";
import { handleOrderApi } from "./orders.js";
import { handleFinanceApi } from "./finance.js";
import { handleInventoryApi } from "./inventory.js";
import { handleProductionApi } from "./production.js";
import { handleFinanceControlsApi } from "./finance_controls.js";
import { handleReportsApi } from "./reports.js";
import { handleHistoryApi } from "./history.js";

const jsonHeaders = {
    "content-type": "application/json; charset=utf-8",
    "cache-control": "no-store",
    "x-content-type-options": "nosniff",
    "x-frame-options": "DENY",
    "referrer-policy": "no-referrer",
};
const sessionLifetimeSeconds = 8 * 60 * 60;
const maximumRequestBytes = 65_536;
const dummyPasswordHash = "pbkdf2-sha256$120000$AAAAAAAAAAAAAAAAAAAAAA$AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA";

function json(body, status = 200, headers = {}) {
    return new Response(JSON.stringify(body), {
        status,
        headers: { ...jsonHeaders, ...headers },
    });
}

function cookieValue(request, name) {
    const cookieHeader = request.headers.get("cookie") ?? "";

    for (const part of cookieHeader.split(";")) {
        const separator = part.indexOf("=");
        if (separator < 0 || part.slice(0, separator).trim() !== name) continue;

        return part.slice(separator + 1).trim();
    }

    return null;
}

async function requestData(request) {
    const contentLength = Number(request.headers.get("content-length") ?? 0);
    if (contentLength > maximumRequestBytes) return { error: "request_too_large", status: 413 };
    if (!/^application\/json(?:\s*;|$)/i.test(request.headers.get("content-type") ?? "")) {
        return { error: "json_required", status: 415 };
    }

    try {
        const reader = request.body?.getReader();
        if (!reader) return { error: "invalid_json", status: 400 };
        const chunks = [];
        let length = 0;
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            length += value.length;
            if (length > maximumRequestBytes) {
                await reader.cancel();
                return { error: "request_too_large", status: 413 };
            }
            chunks.push(value);
        }
        const bodyText = new TextDecoder("utf-8", { fatal: true }).decode(
            Uint8Array.from(chunks.flatMap((chunk) => Array.from(chunk))),
        );
        const body = JSON.parse(bodyText);
        if (!body || typeof body !== "object" || Array.isArray(body)) {
            return { error: "invalid_request", status: 400 };
        }

        return { data: body };
    } catch {
        return { error: "invalid_json", status: 400 };
    }
}

function validUsername(value) {
    return typeof value === "string" && /^[a-z0-9._-]{3,80}$/.test(value);
}

function validEmail(value) {
    return value === null
        || value === ""
        || (typeof value === "string" && value.length <= 190 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value));
}

function validName(value) {
    return typeof value === "string" && value.trim().length > 0 && value.trim().length <= 160;
}

function validPassword(value) {
    const length = typeof value === "string" ? new TextEncoder().encode(value).length : 0;
    return length >= 12 && length <= 200;
}

function secureCookie(request) {
    return new URL(request.url).protocol === "https:";
}

async function authenticatedUser(request, env) {
    const token = cookieValue(request, "asmaa_session");
    if (!token || token.length > 100) return null;

    const sessionId = await sha256(token);
    const session = await env.DB.prepare(
        "SELECT id, user_id, payload, last_activity FROM sessions WHERE id = ? AND last_activity >= ?",
    ).bind(sessionId, Math.floor(Date.now() / 1000) - sessionLifetimeSeconds).first();
    if (!session) return null;

    let sessionData;
    try {
        sessionData = JSON.parse(session.payload);
    } catch {
        await env.DB.prepare("DELETE FROM sessions WHERE id = ?").bind(sessionId).run();
        return null;
    }

    if (typeof sessionData.csrfToken !== "string") {
        await env.DB.prepare("DELETE FROM sessions WHERE id = ?").bind(sessionId).run();
        return null;
    }

    const user = await env.DB.prepare(
        "SELECT id, name, username, email, is_active FROM users WHERE id = ?",
    ).bind(session.user_id).first();
    if (!user?.is_active) {
        await env.DB.prepare("DELETE FROM sessions WHERE id = ?").bind(sessionId).run();
        return null;
    }

    await env.DB.prepare("UPDATE sessions SET last_activity = ? WHERE id = ?")
        .bind(Math.floor(Date.now() / 1000), sessionId).run();
    const permissionResult = await env.DB.prepare(
        `SELECT DISTINCT permissions.name
         FROM permissions
         JOIN role_permissions ON role_permissions.permission_id = permissions.id
         JOIN user_roles ON user_roles.role_id = role_permissions.role_id
         WHERE user_roles.user_id = ?`,
    ).bind(user.id).all();
    const permissions = (permissionResult.results ?? []).map((permission) => permission.name);

    return {
        id: user.id,
        name: user.name,
        username: user.username,
        email: user.email,
        csrfToken: sessionData.csrfToken,
        permissions,
        sessionId,
    };
}

function csrfValid(request, user) {
    const supplied = request.headers.get("x-csrf-token") ?? "";
    return supplied.length > 0 && constantTimeEqual(supplied, user.csrfToken);
}

async function createBootstrapManager(request, env) {
    if (typeof env.BOOTSTRAP_TOKEN !== "string" || env.BOOTSTRAP_TOKEN.length < 32) {
        return json({ error: "bootstrap_not_configured" }, 503);
    }

    const suppliedToken = request.headers.get("x-bootstrap-token") ?? "";
    if (!constantTimeEqual(suppliedToken, env.BOOTSTRAP_TOKEN)) {
        return json({ error: "not_found" }, 404);
    }

    const parsed = await requestData(request);
    if (parsed.error) return json({ error: parsed.error }, parsed.status);

    const { name, username, email = null, password } = parsed.data;
    const normalizedUsername = typeof username === "string" ? username.trim().toLowerCase() : "";
    const normalizedEmail = typeof email === "string" && email.trim() !== ""
        ? email.trim().toLowerCase()
        : null;
    if (!validName(name) || !validUsername(normalizedUsername) || !validEmail(normalizedEmail) || !validPassword(password)) {
        return json({ error: "invalid_setup_data" }, 422);
    }

    const passwordHash = await hashPassword(password);
    const now = new Date().toISOString();
    const result = await env.DB.batch([
        env.DB.prepare(
            `INSERT INTO users (name, username, email, password, is_active, created_at, updated_at)
             SELECT ?, ?, ?, ?, 1, ?, ?
             WHERE NOT EXISTS (
                 SELECT 1 FROM user_roles
                 JOIN roles ON roles.id = user_roles.role_id
                 WHERE roles.name = 'manager'
             )
             AND EXISTS (SELECT 1 FROM roles WHERE name = 'manager')
             AND NOT EXISTS (SELECT 1 FROM users WHERE username = ?)
             AND (? IS NULL OR NOT EXISTS (SELECT 1 FROM users WHERE email = ?))`,
        ).bind(name.trim(), normalizedUsername, normalizedEmail, passwordHash, now, now, normalizedUsername, normalizedEmail, normalizedEmail),
        env.DB.prepare(
            `INSERT INTO user_roles (user_id, role_id, created_at, updated_at)
             SELECT users.id, roles.id, ?, ?
             FROM users CROSS JOIN roles
             WHERE users.username = ? AND roles.name = 'manager' AND changes() > 0`,
        ).bind(now, now, normalizedUsername),
        env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, reason, occurred_at)
             SELECT NULL, 'bootstrap_manager_created', 'App\\Models\\User', users.id, ?, ?, ?
             FROM users WHERE users.username = ? AND changes() > 0`,
        ).bind(JSON.stringify({ username: normalizedUsername }), "إنشاء حساب المدير الأول", now, normalizedUsername),
    ]);

    if ((result[0]?.meta?.changes ?? 0) !== 1) {
        return json({ error: "setup_unavailable" }, 409);
    }

    return json({ status: "manager_created" }, 201);
}

async function login(request, env) {
    const parsed = await requestData(request);
    if (parsed.error) return json({ error: parsed.error }, parsed.status);

    const loginValue = typeof parsed.data.login === "string" ? parsed.data.login.trim().toLowerCase() : "";
    const password = parsed.data.password;
    if (!loginValue || loginValue.length > 190 || typeof password !== "string" || password.length > 200) {
        return json({ error: "invalid_credentials" }, 422);
    }

    const ipAddress = request.headers.get("cf-connecting-ip") ?? "unknown";
    const ipHash = await sha256(`ip:${ipAddress}`);
    const identityHash = await sha256(`login:${loginValue}|${ipAddress}`);
    const now = Math.floor(Date.now() / 1000);
    await env.DB.prepare("DELETE FROM auth_login_attempts WHERE window_started_at < ?")
        .bind(now - 86_400).run();
    const upsertAttempt = (key) => env.DB.prepare(
        `INSERT INTO auth_login_attempts (identity_hash, attempts, window_started_at)
         VALUES (?, 1, ?)
         ON CONFLICT (identity_hash) DO UPDATE SET
             attempts = CASE WHEN window_started_at < ? THEN 1 ELSE attempts + 1 END,
             window_started_at = CASE WHEN window_started_at < ? THEN ? ELSE window_started_at END`,
    ).bind(key, now, now - 60, now - 60, now);
    await env.DB.batch([upsertAttempt(ipHash), upsertAttempt(identityHash)]);
    const attempt = await env.DB.prepare(
        "SELECT max(attempts) AS attempts FROM auth_login_attempts WHERE identity_hash IN (?, ?)",
    ).bind(ipHash, identityHash).first();
    if ((attempt?.attempts ?? 0) > 5) {
        return json({ error: "login_rate_limited" }, 429, { "retry-after": "60" });
    }

    const user = await env.DB.prepare(
        `SELECT id, name, username, email, password, is_active
         FROM users
         WHERE (lower(username) = ? OR lower(email) = ?) AND is_active = 1
         LIMIT 1`,
    ).bind(loginValue, loginValue).first();
    if (!user) {
        await verifyPassword(password, dummyPasswordHash);
        return json({ error: "invalid_credentials" }, 401);
    }
    if (!(await verifyPassword(password, user.password))) {
        return json({ error: "invalid_credentials" }, 401);
    }

    const token = randomToken();
    const sessionId = await sha256(token);
    const csrfToken = randomToken();
    const occurredAt = new Date().toISOString();
    const lastActivity = Math.floor(Date.now() / 1000);
    const requestId = request.headers.get("cf-ray");
    await env.DB.batch([
        env.DB.prepare(
            `INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity)
             VALUES (?, ?, ?, ?, ?, ?)`,
        ).bind(
            sessionId,
            user.id,
            await sha256(ipAddress),
            (request.headers.get("user-agent") ?? "").slice(0, 500),
            JSON.stringify({ csrfToken }),
            lastActivity,
        ),
        env.DB.prepare("UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?")
            .bind(occurredAt, occurredAt, user.id),
        env.DB.prepare(
            `INSERT INTO audit_logs
                (user_id, action, subject_type, subject_id, after_values, request_id, occurred_at)
             VALUES (?, 'staff_login', 'App\\Models\\User', ?, ?, ?, ?)`,
        ).bind(user.id, user.id, JSON.stringify({ username: user.username }), requestId, occurredAt),
        env.DB.prepare("DELETE FROM auth_login_attempts WHERE identity_hash IN (?, ?)")
            .bind(ipHash, identityHash),
    ]);

    return json(
        { status: "authenticated" },
        200,
        { "set-cookie": sessionCookie(token, secureCookie(request), sessionLifetimeSeconds) },
    );
}

async function logout(request, env, user) {
    if (!csrfValid(request, user)) return json({ error: "csrf_failed" }, 419);

    await env.DB.prepare("DELETE FROM sessions WHERE id = ?").bind(user.sessionId).run();
    return json(
        { status: "logged_out" },
        200,
        { "set-cookie": sessionCookie("", secureCookie(request), 0) },
    );
}

async function handleApi(request, env, url) {
    if (url.pathname === "/api/health") {
        if (request.method !== "GET") return json({ error: "method_not_allowed" }, 405);

        try {
            await env.DB.prepare("SELECT 1 AS healthy").first();
            return json({ status: "ok", database: "ok" });
        } catch (error) {
            const errorType = error instanceof Error ? error.name : "unknown";
            console.error("D1 health check failed", errorType);
            return json({ status: "unavailable", database: "unavailable" }, 503);
        }
    }

    if (url.pathname === "/api/setup" && request.method === "POST") {
        return createBootstrapManager(request, env);
    }

    if (url.pathname === "/api/login" && request.method === "POST") {
        return login(request, env);
    }

    if (url.pathname === "/api/logout" && request.method === "POST") {
        const user = await authenticatedUser(request, env);
        return user ? logout(request, env, user) : json({ error: "unauthenticated" }, 401);
    }

    if (url.pathname === "/api/me" && request.method === "GET") {
        const user = await authenticatedUser(request, env);
        if (!user) return json({ error: "unauthenticated" }, 401);

        return json({
            user: { id: user.id, name: user.name, username: user.username, email: user.email },
            permissions: user.permissions,
            csrfToken: user.csrfToken,
        });
    }

    const sharedTools = {
        authenticatedUser,
        csrfValid,
        json,
        requestData,
        hashPassword,
        sha256Hex,
        randomToken,
    };
    const financeResponse = await handleFinanceApi(request, env, url, sharedTools);
    if (financeResponse) return financeResponse;

    const financeControlsResponse = await handleFinanceControlsApi(request, env, url, sharedTools);
    if (financeControlsResponse) return financeControlsResponse;

    const reportsResponse = await handleReportsApi(request, env, url, sharedTools);
    if (reportsResponse) return reportsResponse;

    const historyResponse = await handleHistoryApi(request, env, url, sharedTools);
    if (historyResponse) return historyResponse;

    const inventoryResponse = await handleInventoryApi(request, env, url, sharedTools);
    if (inventoryResponse) return inventoryResponse;

    const productionResponse = await handleProductionApi(request, env, url, sharedTools);
    if (productionResponse) return productionResponse;

    const orderResponse = await handleOrderApi(request, env, url, sharedTools);
    if (orderResponse) return orderResponse;

    const catalogResponse = await handleCatalogApi(request, env, url, sharedTools);
    if (catalogResponse) return catalogResponse;

    const adminResponse = await handleAdminApi(request, env, url, sharedTools);
    if (adminResponse) return adminResponse;

    const customerResponse = await handleCustomerApi(request, env, url, sharedTools);
    if (customerResponse) return customerResponse;

    if (url.pathname.startsWith("/api/")) return json({ error: "not_found" }, 404);
    return null;
}

export default {
    async fetch(request, env) {
        const url = new URL(request.url);

        try {
            const apiResponse = await handleApi(request, env, url);
            if (apiResponse) return apiResponse;
            if (env.ASSETS) {
                const assetResponse = await env.ASSETS.fetch(request);
                const headers = new Headers(assetResponse.headers);
                headers.set(
                    "content-security-policy",
                    "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'",
                );
                headers.set("x-content-type-options", "nosniff");
                headers.set("x-frame-options", "DENY");
                headers.set("referrer-policy", "no-referrer");
                if (secureCookie(request)) {
                    headers.set("strict-transport-security", "max-age=31536000; includeSubDomains");
                }

                return new Response(assetResponse.body, {
                    status: assetResponse.status,
                    statusText: assetResponse.statusText,
                    headers,
                });
            }

            return json({ error: "not_found" }, 404);
        } catch (error) {
            const errorType = error instanceof Error ? error.name : "unknown";
            const diagnostic = error instanceof Error ? error.message.slice(0, 200) : "unknown";
            console.error("Cloudflare Worker request failed", errorType, diagnostic);

            return json({ error: "internal_error" }, 500);
        }
    },
};
