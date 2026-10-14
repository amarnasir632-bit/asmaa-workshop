import assert from "node:assert/strict";
import test from "node:test";

import {
    constantTimeEqual,
    hashPassword,
    randomToken,
    sessionCookie,
    sha256,
    verifyPassword,
} from "../src/security.js";

test("password hashes are salted, encoded, and verified", async () => {
    const hash = await hashPassword("correct horse battery");

    assert.match(hash, /^pbkdf2-sha256\$120000\$[A-Za-z0-9_-]{22}\$[A-Za-z0-9_-]{43}$/);
    assert.equal(await verifyPassword("correct horse battery", hash), true);
    assert.equal(await verifyPassword("incorrect password", hash), false);
});

test("invalid or weakened password hash formats are rejected", async () => {
    assert.equal(await verifyPassword("some password", "bcrypt$1$salt$hash"), false);
    assert.equal(await verifyPassword("some password", "pbkdf2-sha256$1$c2FsdA$aGFzaA"), false);
});

test("session secrets are unpredictable URL-safe values and are hashed", async () => {
    const first = randomToken();
    const second = randomToken();

    assert.match(first, /^[A-Za-z0-9_-]{43}$/);
    assert.notEqual(first, second);
    assert.match(await sha256(first), /^[A-Za-z0-9_-]{43}$/);
    assert.notEqual(await sha256(first), first);
});

test("constant-time comparison and session cookies enforce browser protections", () => {
    assert.equal(constantTimeEqual("same", "same"), true);
    assert.equal(constantTimeEqual("same", "different"), false);
    assert.match(sessionCookie("token", true), /HttpOnly; SameSite=Strict/);
    assert.match(sessionCookie("token", true), /; Secure/);
    assert.doesNotMatch(sessionCookie("token", false), /; Secure/);
});
