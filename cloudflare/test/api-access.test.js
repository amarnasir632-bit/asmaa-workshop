import assert from "node:assert/strict";
import test from "node:test";

import worker from "../src/index.js";

const protectedRoutes = [
    ["/api/inventory", "GET"],
    ["/api/inventory/opening-batches", "POST"],
    ["/api/inventory/adjustments", "POST"],
    ["/api/production", "GET"],
    ["/api/production/jobs", "POST"],
    ["/api/expenses", "GET"],
    ["/api/expenses", "POST"],
    ["/api/orders/1/cancel", "POST"],
    ["/api/orders/1/refunds", "POST"],
    ["/api/reports", "GET"],
    ["/api/audit", "GET"],
    ["/api/history", "GET"],
    ["/api/history", "POST"],
    ["/api/history/1", "GET"],
];

for (const [path, method] of protectedRoutes) {
    test(`${method} ${path} requires an authenticated session`, async () => {
        const response = await worker.fetch(new Request(`https://example.test${path}`, { method }), {});

        assert.equal(response.status, 401);
        assert.deepEqual(await response.json(), { error: "unauthenticated" });
    });
}
