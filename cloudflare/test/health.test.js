import assert from "node:assert/strict";
import test from "node:test";

import worker from "../src/index.js";

function request(path, method = "GET") {
    return new Request(`https://example.test${path}`, { method });
}

test("health reports the D1 binding is reachable", async () => {
    const response = await worker.fetch(request("/api/health"), {
        DB: {
            prepare(query) {
                assert.equal(query, "SELECT 1 AS healthy");
                return { first: async () => ({ healthy: 1 }) };
            },
        },
    });

    assert.equal(response.status, 200);
    assert.deepEqual(await response.json(), { status: "ok", database: "ok" });
    assert.equal(response.headers.get("cache-control"), "no-store");
});

test("health reports D1 failures without exposing details", async () => {
    const response = await worker.fetch(request("/api/health"), {
        DB: {
            prepare() {
                return { first: async () => { throw new Error("private connection details"); } };
            },
        },
    });

    assert.equal(response.status, 503);
    const body = await response.text();

    assert.deepEqual(JSON.parse(body), {
        status: "unavailable",
        database: "unavailable",
    });
    assert.equal(body.includes("private connection details"), false);
});

test("health rejects non-GET requests", async () => {
    const response = await worker.fetch(request("/api/health", "POST"), {});

    assert.equal(response.status, 405);
    assert.deepEqual(await response.json(), { error: "method_not_allowed" });
});

test("unknown paths return a JSON 404", async () => {
    const response = await worker.fetch(request("/"), {});

    assert.equal(response.status, 404);
    assert.deepEqual(await response.json(), { error: "not_found" });
});

test("static assets receive security headers through the worker", async () => {
    const response = await worker.fetch(request("/"), {
        ASSETS: {
            fetch: async () => new Response("<html></html>", {
                headers: { "content-type": "text/html" },
            }),
        },
    });

    assert.equal(response.status, 200);
    assert.match(response.headers.get("content-security-policy"), /frame-ancestors 'none'/);
    assert.equal(response.headers.get("x-content-type-options"), "nosniff");
    assert.equal(response.headers.get("x-frame-options"), "DENY");
});
