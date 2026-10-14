const passwordIterations = 120_000;
const passwordAlgorithm = "PBKDF2";

function encodeBase64Url(bytes) {
    let binary = "";
    for (const byte of bytes) binary += String.fromCharCode(byte);
    return btoa(binary).replaceAll("+", "-").replaceAll("/", "_").replaceAll("=", "");
}

function decodeBase64Url(value) {
    const base64 = value.replaceAll("-", "+").replaceAll("_", "/");
    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, "=");
    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
}

async function derivePassword(password, salt, iterations) {
    const key = await crypto.subtle.importKey(
        "raw",
        new TextEncoder().encode(password),
        passwordAlgorithm,
        false,
        ["deriveBits"],
    );
    const result = await crypto.subtle.deriveBits(
        { name: passwordAlgorithm, hash: "SHA-256", salt, iterations },
        key,
        256,
    );

    return new Uint8Array(result);
}

export async function hashPassword(password) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const hash = await derivePassword(password, salt, passwordIterations);

    return `pbkdf2-sha256$${passwordIterations}$${encodeBase64Url(salt)}$${encodeBase64Url(hash)}`;
}

export async function verifyPassword(password, encoded) {
    const [algorithm, iterationsText, saltText, expectedText, ...extra] = encoded.split("$");
    const iterations = Number(iterationsText);

    if (
        algorithm !== "pbkdf2-sha256"
        || extra.length !== 0
        || !Number.isSafeInteger(iterations)
        || iterations < passwordIterations
        || iterations > 1_000_000
        || !/^[A-Za-z0-9_-]{22}$/.test(saltText)
        || !/^[A-Za-z0-9_-]{43}$/.test(expectedText)
    ) {
        return false;
    }

    const expected = decodeBase64Url(expectedText);
    const actual = await derivePassword(password, decodeBase64Url(saltText), iterations);

    return constantTimeEqual(actual, expected);
}

export function randomToken() {
    return encodeBase64Url(crypto.getRandomValues(new Uint8Array(32)));
}

export async function sha256(value) {
    const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));

    return encodeBase64Url(new Uint8Array(digest));
}

export async function sha256Hex(value) {
    const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));

    return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

export function constantTimeEqual(left, right) {
    if (left.length !== right.length) return false;

    let difference = 0;
    for (let index = 0; index < left.length; index += 1) {
        const leftByte = typeof left === "string" ? left.charCodeAt(index) : left[index];
        const rightByte = typeof right === "string" ? right.charCodeAt(index) : right[index];
        difference |= leftByte ^ rightByte;
    }

    return difference === 0;
}

export function sessionCookie(token, secure, maxAge = 28_800) {
    return `asmaa_session=${token}; Path=/; HttpOnly; SameSite=Strict; Max-Age=${maxAge}${secure ? "; Secure" : ""}`;
}
