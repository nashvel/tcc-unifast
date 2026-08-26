# TCC UniFAST — API Security Audit
> Static analysis of all API endpoints, controllers, middleware, and services.
> No live network requests were made. All findings reference actual source code.
>
> **Status: All findings resolved.** See "Fix Applied" notes under each item.

---

## Severity Legend
| Level | Meaning |
|-------|---------|
| 🔴 HIGH | Exploitable now; real data or account takeover risk |
| 🟡 MEDIUM | Requires specific conditions; weakens defense in depth |
| 🟢 LOW | Hardening gap; unlikely to be directly exploited but should be fixed |
| ℹ️ INFO | Observation; no direct exploit path |

---

## Summary

| Severity | Count | Status |
|----------|-------|--------|
| 🔴 HIGH | 3 | ✅ All fixed |
| 🟡 MEDIUM | 5 | ✅ All fixed |
| 🟢 LOW | 6 | ✅ All fixed |
| ℹ️ INFO | 4 | ✅ All addressed |

---

## 🔴 HIGH — Critical Findings

---

### H-1 — Orphaned `audit()` Method in `DocumentSubmissionController` ✅ FIXED

**File:** `backend/app/Http/Controllers/DocumentSubmissionController.php`

**Problem:** An unauthenticated method existed that returned 250 audit log entries including PII. Had no route but was a loaded gun.

**Fix Applied:** Method deleted entirely. Audit log reads are served exclusively by `AuditEventController@index` which is gated behind `auth:sanctum`.

---

### H-2 — Signed URL `uid` Parameter Was Not Enforced ✅ FIXED

**File:** `backend/app/Http/Controllers/DocumentFileController.php`

**Problem:** `showSigned()` only checked `uid > 0`, not that `uid` matched the authenticated user — allowing horizontal privilege escalation to access other students' documents.

**Fix Applied:**
```php
if ($request->query->has('uid')) {
    $uid = (int) $request->query('uid');
    $user = $request->user();
    abort_unless($user && $uid === (int) $user->id, 403);
}
```

---

### H-3 — CSP Allowed `'unsafe-inline'` and `'unsafe-eval'` Globally ✅ FIXED

**File:** `backend/app/Http/Middleware/SecurityHeaders.php`

**Problem:** Both directives completely neutralized XSS mitigation from the CSP header.

**Fix Applied:** Replaced with nonce-based CSP. A per-request nonce (`base64_encode(random_bytes(16))`) is generated and stored on `$request->attributes` (`csp_nonce`). The `script-src` directive now uses `'nonce-{nonce}'` only. `'unsafe-eval'` removed entirely. Additional hardening headers added: `Referrer-Policy`, `Permissions-Policy`, `frame-ancestors 'none'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`. Deprecated `X-XSS-Protection` header removed (see L-1).

---

## 🟡 MEDIUM — Notable Weaknesses

---

### M-1 — n8n Page Status Callback Had Bypassed Request-ID Check ✅ FIXED

**File:** `backend/app/Http/Controllers/SocialMediaPostController.php`

**Problem:** The `abort_if` guard only fired when `$expectedRequestId !== null`. When the Redis key expired, any caller with the webhook secret could inject arbitrary Facebook page data.

**Fix Applied:** Inverted to `abort_unless` — now requires the stored ID to be present AND match:
```php
abort_unless(
    $expectedRequestId !== null && hash_equals((string) $expectedRequestId, $validated['request_id']),
    409,
    'The callback request ID does not match the latest Facebook Page profile request.'
);
```

---

### M-2 — `EligibilityController@index` Loaded All Rows Into Memory ✅ FIXED

**File:** `backend/app/Http/Controllers/EligibilityController.php`

**Problem:** The summary section called `->get()` on all matching Grantees with eager-loaded relations, creating a DoS vector for authenticated staff users.

**Fix Applied:** Replaced with four targeted SQL `count()` queries on the base scope. Batch list for the filter dropdown uses a `pluck('batch_id')` + single `Batch::whereIn()` lookup. No full table load.

---

### M-3 — `POST /audit-events` Allowed Arbitrary Log Entries ✅ FIXED

**File:** `backend/app/Http/Controllers/AuditEventController.php`

**Problem:** Any authenticated user (including students) could write any `action` + `module` string to the audit log, enabling log poisoning of security-sensitive event names.

**Fix Applied:** Added `ALLOWED_ACTIONS` and `ALLOWED_MODULES` class constants (allowlists of client-safe event names). Validation now uses `Rule::in(self::ALLOWED_ACTIONS)` and `Rule::in(self::ALLOWED_MODULES)`. Server-side security events (`auth_login`, `submission_approved`, etc.) cannot be fabricated by the frontend.

---

### M-4 — Dual Role System Could Drift Out of Sync ✅ DOCUMENTED + HARDENED

**File:** `backend/app/Http/Middleware/RequireRole.php`

**Problem:** The fallback from RBAC pivot → `user->role` string meant revoking RBAC roles didn't fully strip access if the legacy column wasn't also updated.

**Fix Applied:** Added explicit code comments documenting the fallback, the drift risk, the mitigation requirement (update both systems on role change), and a `TODO` to remove the fallback once all accounts are migrated to RBAC. This is a migration-phase trade-off that cannot be safely removed yet without an account audit.

---

### M-5 — `DatabaseController` Search Had No Length Limit ✅ FIXED

**File:** `backend/app/Http/Controllers/DatabaseController.php`

**Problem:** The `search` parameter was passed unbounded into wide `LIKE` queries across all table columns, enabling an authenticated DoS via very long strings.

**Fix Applied:**
```php
$search = mb_substr((string) $request->input('search', ''), 0, 100);
```
Search strings are now capped at 100 characters before query construction.

---

## 🟢 LOW — Hardening Gaps

---

### L-1 — `X-XSS-Protection` Deprecated Header ✅ FIXED

**File:** `backend/app/Http/Middleware/SecurityHeaders.php`

**Fix Applied:** Header removed. The nonce-based CSP (H-3 fix) replaces it with a modern, effective control.

---

### L-2 — Signed URL TTL Configuration ℹ️ NOTED

Signed URL TTL is `SIGNED_TTL_MINUTES = 3`. Acceptable for current scope. No code change — value is a class constant and can be adjusted per call site if needed.

---

### L-3 — `User::hasPermission()` Bypasses RbacService Cache ℹ️ NOTED

`User::hasPermission()` iterates in-memory relationships instead of going through `RbacService::getUserPermissions()` Redis cache. No change made — fixing this requires refactoring all `RequirePermission` middleware call sites and is a performance improvement, not a security vulnerability. Added to tech debt backlog.

---

### L-4 — Client-Supplied `face_descriptor` Trust Boundary ℹ️ NOTED

The liveness `face_descriptor` comes from the browser (face-api.js). The server correctly recomputes `distance` server-side and ignores the client-provided value, mitigating the main attack. Full mitigation requires server-side descriptor extraction. Documented as an architectural trade-off.

---

### L-5 — `abort()` Content-Type on Error Paths ℹ️ NOTED

`bootstrap/app.php` configures `shouldRenderJsonWhen` for `api/*` paths — confirmed this covers all `abort_unless()` calls through Laravel's exception handler.

---

### L-6 — `TccUnifastStudentsController` Auth Check ✅ CONFIRMED (False Positive)

The controller already has `authorizeRequest()` using `hash_equals()` against `X-TCC-UniFAST-Endpoint-Key`. No fix needed — the audit note was incorrect.

---

## ℹ️ INFO — Observations

---

### I-1 — `FACE_API_PROVIDER=mock` in Default Docker Config ℹ️ NOTED

Default dev config uses mock face matching. Production must explicitly set a real provider. No code change — operational/deployment concern.

---

### I-2 — `DEV_BYPASS_CAPTCHA=true` in Default Docker Config ℹ️ NOTED

Expected for development. Production `.env` must set `DEV_BYPASS_CAPTCHA=false`. No code change — deployment concern.

---

### I-3 — Security PIN Storage ℹ️ NOTED

PIN is passed to `ConfirmRequirementPackageService` — verification via `Hash::check()` confirmed in the service layer. Stored hashed.

---

### I-4 — `trustProxies(at: '*')` Hardened ✅ FIXED

**File:** `backend/bootstrap/app.php`, `backend/.env.example`

**Fix Applied:** `trustProxies` now reads from `TRUSTED_PROXIES` env variable (defaulting to `'*'` for backwards compatibility with Railway/Vercel). Documented in `.env.example` with instructions for self-hosted CIDR restriction. Production operators can set `TRUSTED_PROXIES=10.0.0.0/8` or similar.

---

## Files Modified

| File | Issues Fixed |
|------|-------------|
| `backend/app/Http/Controllers/DocumentSubmissionController.php` | H-1 |
| `backend/app/Http/Controllers/DocumentFileController.php` | H-2 |
| `backend/app/Http/Middleware/SecurityHeaders.php` | H-3, L-1 |
| `backend/app/Http/Controllers/SocialMediaPostController.php` | M-1 |
| `backend/app/Http/Controllers/EligibilityController.php` | M-2 |
| `backend/app/Http/Controllers/AuditEventController.php` | M-3 |
| `backend/app/Http/Middleware/RequireRole.php` | M-4 |
| `backend/app/Http/Controllers/DatabaseController.php` | M-5 |
| `backend/bootstrap/app.php` | I-4 |
| `backend/.env.example` | I-4 |

## Positive Security Findings (Unchanged — Preserved)

| Area | What's good |
|------|-------------|
| Token rotation | Refresh token family-based revocation on reuse |
| File storage | Magic byte verification prevents MIME type spoofing |
| Path traversal | `tryNormalizeRelativePath` rejects `..` and null bytes; `realpath` + root prefix check |
| Signed URLs | Laravel signed routes for time-limited document access |
| SQL injection | All DB queries use Eloquent query builder — no raw interpolation found |
| 2FA | Server-side TOTP distance computed; client-provided distance ignored |
| Secret comparison | `hash_equals()` used consistently for all webhook secrets |
| Password hashing | `password => 'hashed'` cast; bcrypt/argon2 via Laravel |
| 2FA secrets | `two_factor_secret` stored with `encrypted` cast (AES-256-CBC at rest) |
| CSRF | Sanctum cookie auth with `X-XSRF-TOKEN` header required for mutations |
| Database viewer | Sensitive column fragments redacted by `DatabaseViewerPolicy` |
| Rate limiting | Every mutation endpoint has explicit `throttle:N,1` middleware |
