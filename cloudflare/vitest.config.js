import path from "node:path";
import { cloudflareTest, readD1Migrations } from "@cloudflare/vitest-plugin";
import { defineConfig } from "vitest/config";

const migrations = await readD1Migrations(path.join(import.meta.dirname, "migrations"));

export default defineConfig({
    plugins: [
        cloudflareTest({
            wrangler: { configPath: "./wrangler.jsonc" },
            miniflare: {
                bindings: {
                    BOOTSTRAP_TOKEN: "test-bootstrap-token-0123456789abcdef",
                    TEST_MIGRATIONS: migrations,
                },
            },
        }),
    ],
    test: {
        include: ["integration-tests/**/*.test.js"],
        setupFiles: ["./integration-tests/apply-migrations.js"],
        testTimeout: 20_000,
    },
});
