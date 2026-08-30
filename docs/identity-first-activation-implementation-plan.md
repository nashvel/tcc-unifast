# Implementation Plan — Identity-First Activation (Option A) + Security Remediation

**Status:** Proposed
**Owner:** TBD
**Created:** 2026-08-30
**Scope:** Make biometric verification the gate for account ownership, then close the remaining findings from the security scan.

---

## 1. Problem Statement

### 1.1 The ordering flaw

`ActivationController::activate` (`backend/app/Http/Controllers/ActivationController.php:31-67`) accepts an activation token plus a password and immediately grants full account ownership:

```php
$user->forceFill([
    'password' => Hash::make($validated['password']),
    'account_status' => 'pending_kyc',
    'activated_at' => now(),
    'email_verified_at' => now(),
])->save();

$activation->update(['used_at' => now()]);
$tokens->revokeAll($user);
$tokens->issuePair($user->fresh(), $request);   // full ['*'] session issued here
```

The in-code comment states the intent plainly: *"Token is the sole proof of invitation."*

Face verification happens three steps later (`pending_kyc` → `pending_identity` → id_scan → liveness) and gates only the document vault (`RequirementVaultController.php:260`). It does not gate account ownership.

### 1.2 Consequences

| # | Issue | Evidence |
|---|---|---|
| 1 | Link holder becomes the permanent account owner | `ActivationController.php:44-52` |
| 2 | Public endpoint leaks PII before any auth | `ActivationController.php:16-29` returns `email`, `name`, `student_id`, `program` |
| 3 | Long takeover window | 7-day TTL (`OnboardingCenterController.php:191`, `BatchActivationNotificationController.php:48`), 14-day (`ActivationSeederController.php:121,307`) |
| 4 | No knowledge factor | Only validation is `password => required\|string\|min:8\|confirmed` |
| 5 | Impostor rejection punishes the victim | `FaceReviewController.php:130` sets the **real student's** `account_status => 'blocked'` |
| 6 | Masking defeated | `StudentKycController.php:38-41` masks `student_id_last4` "never a full masterlist cheat sheet" — but 1.2#2 already gave out the full ID |
| 7 | Dormant second credential | `BatchActivationNotificationController.php:38-56` still emails a `TCC-XXXX-XXXX` temporary password contradicting the "No temporary password" comment |

Net effect: the 128-D Euclidean face match presented as the core technical contribution (DFD Level 2, Process 3.0) protects *document upload*, not *account ownership*.

### 1.3 Constraint that shapes the design

Every identity endpoint sits behind `auth:sanctum` + `role:student` (`backend/routes/api.php:97-123`). ID scan and liveness therefore need an authenticated session, which today only exists after a password is set. The order cannot simply be swapped — a pre-credential session type is required.

---

## 2. Target Design (Option A)

Introduce a **token-scoped onboarding session**: a short-lived, ability-restricted Sanctum token that authorizes *only* the identity funnel and carries no password.

```
Activation link
      ↓
POST /api/activation/{token}/begin        ← no password
      ↓  issues onboarding session, abilities: ['onboarding:identity']
KYC  →  ID scan  →  Liveness
      ↓
   ┌──┴──────────────────┐
Green zone           Yellow/Red zone
(auto-pass)          → staff face review → approved
   └──┬──────────────────┘
      ↓
POST /api/onboarding/credentials          ← password set HERE, first time
      ↓  upgrade to full ['*'] session, account_status = active
Vault PIN → document vault
```

### 2.1 Invariants

- **I1** — `users.password` never holds a user-chosen value until identity is verified (auto-pass or staff approval).
- **I2** — An onboarding-scoped token can reach *only* the whitelisted identity routes. Everything else returns 403.
- **I3** — Refresh rotation preserves scope. An onboarding session can never rotate into a full session.
- **I4** — `email_verified_at` is set at credential creation, not at link click.
- **I5** — A rejected face match is recoverable by the legitimate grantee and never leaves a permanent `blocked` state caused by a third party.
- **I6** — Existing full sessions (`abilities = ['*']`) keep working unchanged. `tokenCan('*')` satisfies every ability check, so staff/admin/developer flows are unaffected.
- **I7** — Expiry is never a dead end. Every abandoned, expired, or waiting state has a self-service or event-driven path back in (§2.3).

### 2.2 Accepted tradeoff

Because the password moves to the end, the activation token must stay valid across the whole funnel instead of being consumed on first click. Mitigations:

- Token TTL cut from 7/14 days to **24 hours** — shortened rather than lengthened, made safe by the recovery paths in §2.3.
- `POST /begin` stamps `first_used_at` but does **not** consume the token, so the link is reusable for its whole TTL.
- Onboarding session TTL **30 minutes**, no long-lived refresh; resuming re-hits `/begin` with the same link.
- One active onboarding session per token — issuing a new one revokes the prior.
- Token is consumed (`used_at`) only at credential creation.
- `POST /begin` throttled at 10/min; `GET /activation/{token}` reduced to a non-identifying payload.

### 2.3 Abandonment, resume, and recovery

Two independent clocks. Confusing them is the main source of "is my link dead?" support load.

| Clock | Lifetime | Expiry behaviour |
|---|---|---|
| Onboarding session (cookie) | 30 min | Silent and expected. Re-click the same link. |
| Activation token (the link) | 24 h | Link is dead. Needs a resend. |

**Resume needs no new code.** `StudentOnboardingNavigator` derives the next step from `account_status` plus `GranteeIdentityProfile.status`, both persisted. A student who finishes KYC and walks away returns to `id_scan`, not to the start. Completed steps stay completed. Each `/begin` mints a fresh 30-minute session and revokes the previous one.

| Scenario | Outcome |
|---|---|
| Closes tab after KYC, returns in 2 h | Same link → resumes at ID scan |
| Session expires mid-liveness | Same link → back to liveness, ID scan still done |
| Doesn't have the physical school ID yet | Same link within 24 h, or self-service resend |
| No return within 24 h | Link dead → `POST /activation/resend` |
| **Stuck in `pending_face_review`** | **Approval email carries a fresh link — see below** |

**The `pending_face_review` gap.** This is the one state that time-based TTL cannot solve. The student is waiting on staff, review can take days, and they have **no password**, so once the token expires there is nothing to log in with. Under the old flow they would simply log in; under password-later that door does not exist.

Fix is event-driven, not time-based: `FaceReviewController::approve` issues a fresh activation token and emails a "your identity was approved — set your password" link (Phase 4). Phase 5 already does exactly this for the reject path; approve needs symmetric treatment. An indefinite wait becomes a notification delivered when it is actually actionable.

**Self-service resend.** `POST /activation/resend` (Phase 3) takes an email and always returns the same generic response whether or not the address exists — no account enumeration. If it matches a grantee who has not completed onboarding, a fresh token is mailed. Throttled 3/hour per IP **and** per email.

Together these make the 24-hour TTL safe: abandonment and expiry both resolve to "request a new link", matching how magic-link flows already behave. Short-lived tokens plus easy recovery beats long-lived tokens, and the leak window shrinks from 72 h to 24 h.

### 2.4 Staff and admin accounts keep password-first — by design

Staff, admin, and developer accounts do not pass through the biometric funnel, so there is no verification to wait for. They set a password directly from their activation link. That is password-first, and it is correct for them.

So two activation paths exist deliberately:

| Account type | Flow | Gate on ownership |
|---|---|---|
| Student / grantee | `/begin` → KYC → ID scan → liveness → credentials | Biometric match |
| Staff / admin / developer | Activation link → set password | Invite-only + admin authorization |

Phase 7 routes `CollaboratorController` invites onto the token flow (replacing `bcrypt('password')`). State this split explicitly in the thesis documentation so it does not read as an inconsistency.

---

## 3. Phase Plan

Ten phases. Phases 1–6 deliver Option A. Phase 7 removes dormant credential paths. Phase 8 closes unrelated findings. Phases 9–10 cover tests and documentation.

Phases 1–2 are additive and safe to merge alone. The behavioural cutover lands in Phase 3.

---

### Phase 0 — Preparation and guardrails — ✅ DONE

**Baseline recorded:** `13 failed, 154 passed (939 assertions)`.

All 13 are pre-existing and unrelated to this work (verified by stashing all changes and re-running):

| Test | Assertion |
|---|---|
| `AcademicGradeParserTest` ×4 | `failed_count` type/value drift |
| `ArchitectureTest > database viewer routes` | missing `permission:view_database` |
| `ArchitectureTest > masterlist import controller` | 321 lines > 240 budget |
| `ArchitectureTest > submission risk scoring` | 183 lines > 150 budget |
| `SubmissionRiskScoringServiceTest` | `ErrorException` |
| `OnboardingFlowTest > confirm import creates…` | `mail.sent` null ≠ 1 (see finding below) |
| `OnboardingFlowTest > kyc …` ×3 | `kyc_profiles` row mismatch |
| `SocialMediaPostIntegrationStatusTest` | 404 ≠ 200 |

**Task 2 — test tagged.** `OnboardingFlowTest::test_activation_with_token_only_moves_student_to_kyc` now carries a `PHASE-3: rewritten` docblock naming its replacement. It asserts exactly what Option A removes (password + full `['*']` session at link click) and **will** fail when Phase 3 lands.

**Task 3 — token-minting inventory (corrected).** Only **three** live sites, not four:

| Site | TTL | Notes |
|---|---|---|
| `OnboardingCenterController:189` | 7 days | blast/resend invites — the real intake path |
| `BatchActivationNotificationController:46` | 7 days | also emails a `TCC-XXXX-XXXX` temporary password |
| `ActivationSeederController:119, 301` | 14 days | dev seeding (2 call sites) |

**`MasterlistImportController` no longer mints tokens at all.** `confirm()` creates the `User` + `Grantee` then stops:
```php
// Emails and activation tokens are no longer generated here.
// They will be generated when the staff uses the Onboarding Center to blast invites.
```
Its `temporaryPassword()` and `sendActivationEmail()` are now **dead code** — and that is the root cause of the pre-existing `OnboardingFlowTest > confirm import` failure asserting `mail.sent === 1`. The test still expects the old inline-invite behaviour. Phase 7 deletes those methods; Phase 9 should update or drop that assertion. Corrects the four-site estimate in the original plan.

**Task 4 — `users.password` is `NOT NULL`** (`0001_01_01_000000_create_users_table.php:19`, no later migration alters it). Confirms the plan's approach: write an **unusable random hash**, never `NULL`. No nullability migration required in Phase 1.

<details>
<summary>Original Phase 0 task list</summary>

**Goal:** Freeze the current behaviour in tests so the cutover is provably scoped.

**Files**
- `backend/tests/Feature/OnboardingFlowTest.php`

**Tasks**
1. Run the suite and record the baseline: `php artisan test --testsuite=Feature`.
2. Tag `test_activation_with_token_only_moves_student_to_kyc` (`:119-142`) with a `// PHASE-3: rewritten` marker. This test asserts the exact behaviour being removed and **will** fail in Phase 3 by design.
3. Inventory every caller that mints activation tokens, so Phase 7 catches all of them:
   - `MasterlistImportController.php:193-197`
   - `OnboardingCenterController.php:187-193`
   - `BatchActivationNotificationController.php:38-50`
   - `ActivationSeederController.php:117-123, 303-309`
4. Confirm `users.password` column nullability. Newly created accounts get an **unusable random hash** rather than `NULL`, so no nullability migration should be needed — verify and note. No live-data audit is required (§4.3: pre-production, zero real grantees).

**Acceptance:** baseline green; the four token-minting sites are listed in the PR description.

</details>

---

### Phase 1 — Schema and model changes — ✅ DONE

**Shipped:**
- `2026_08_24_000100_add_scope_to_refresh_tokens.php` — `scope` string(20), default `'full'`, indexed, after `family_id`.
- `2026_08_24_000200_add_lifecycle_to_activation_tokens.php` — `first_used_at` nullable timestamp; `onboarding_session_id` nullable FK → `personal_access_tokens`, `nullOnDelete`.
- `RefreshToken` — `SCOPE_FULL` / `SCOPE_ONBOARDING` constants + `isFullScope()`, which defaults to full when `scope` is null so pre-existing rows behave unchanged.
- `ActivationToken` — casts `first_used_at`; `isUsable()` (unspent **and** unexpired), the reusable-link predicate Phase 3 needs.

**Verified:**
- `migrate` → both DONE. `migrate:rollback --step=2` → both DONE. Re-`migrate` → both DONE. The FK drop uses `dropConstrainedForeignId`, which is the part that usually breaks rollback on MySQL.
- Columns confirmed present via `Schema::hasColumn`; `refresh_tokens.scope` default reads back as `'full'`.
- FK type matches: `personal_access_tokens` uses `$table->id()` (unsigned bigint).
- Full suite: **13 failed / 154 passed — identical to the Phase 0 baseline.** Behaviour-neutral, as intended.

<details>
<summary>Original Phase 1 detail</summary>

**Goal:** Persist onboarding-session scope and token lifecycle without touching behaviour.

**Files**
- `backend/database/migrations/2026_08_24_000100_add_scope_to_refresh_tokens.php` *(new)*
- `backend/database/migrations/2026_08_24_000200_add_lifecycle_to_activation_tokens.php` *(new)*
- `backend/app/Models/RefreshToken.php`
- `backend/app/Models/ActivationToken.php`

**Changes**

`refresh_tokens`:
- `scope` — string, default `'full'`, indexed. Values: `full` | `onboarding`. Backs invariant **I3**.

`activation_tokens`:
- `first_used_at` — nullable timestamp. Stamped by `/begin`; distinct from `used_at`.
- `onboarding_session_id` — nullable unsigned bigint FK to `personal_access_tokens.id`, `nullOnDelete`. Enforces one live onboarding session per token.

Cast both new timestamps in `ActivationToken::casts()`. `ActivationToken` uses `protected $guarded = []` so no `$fillable` edit is required.

**Acceptance:** `php artisan migrate` and `migrate:rollback` both clean; existing rows default to `scope = 'full'`.

**Rollback:** drop the two migrations. No behavioural coupling yet.

</details>

---

### Phase 2 — Scoped session plumbing — ✅ DONE

**Shipped:**

`AuthTokenService`
- `ONBOARDING_ABILITY = 'onboarding:identity'` constant; `abilitiesFor(string $scope)` maps scope → abilities (`['*']` vs `['onboarding:identity']`).
- `issuePair()` gained a `string $scope = SCOPE_FULL` parameter and now persists `scope` on the `RefreshToken` row. Default keeps every existing caller on full scope.
- `issueOnboardingSession()` — 30-minute PAT, **no refresh row**, forgets any stale refresh cookie, returns the PAT id for `activation_tokens.onboarding_session_id`.
- `upgradeToFullSession()` — revoke all, then re-issue at full scope.
- `rotate()` now carries the stored scope forward instead of re-deriving it.

`EnsureFullSession` middleware — `abort_unless($token && $request->user()->tokenCan('*'), 403)`. Fail-closed on a missing token, so adding it to a group can never widen access.

`bootstrap/app.php` — registered `'full-session'` and `'ability'` aliases.

`config/services.php` — `onboarding_session_ttl_minutes` (30), `activation_token_ttl_hours` (24), `activation_resend_throttle_per_hour` (3).

**Resolved the plan's open question on `ability`:** Laravel 13.22 / Sanctum does **not** pre-register it (verified against `SanctumServiceProvider`, no `aliasMiddleware` call). `CheckForAnyAbility` exists and delegates to `tokenCan()`, which returns true for `['*']` — so aliasing it directly works and no custom wrapper is needed. This also confirms **I6** at the source: full sessions satisfy every ability check.

**Two design notes worth keeping:**
1. **I3 is structural, not a check.** `issueOnboardingSession` creates no `refresh_tokens` row, so `rotate()` has nothing to find and `/auth/refresh` cannot widen scope. The `scope` column is defence in depth for the full-session path.
2. `issueOnboardingSession` actively forgets the refresh cookie, otherwise a leftover full-scope refresh token could resurrect a full session mid-funnel.

**Verified:** new `tests/Feature/OnboardingSessionScopeTest.php` — **11 passed**, covering I2/I3/I6, fail-closed behaviour, alias registration, and the null-scope-means-full default. Full suite: **13 failed / 165 passed** — same 13 pre-existing failures as baseline, passing count up from 154 by exactly the 11 new tests.

<details>
<summary>Original Phase 2 detail</summary>

**Goal:** Teach `AuthTokenService` to issue scoped sessions, and add the middleware that enforces **I2**.

**Files**
- `backend/app/Services/AuthTokenService.php`
- `backend/app/Http/Middleware/EnsureFullSession.php` *(new)*
- `backend/bootstrap/app.php`
- `backend/config/services.php`

**Changes**

`AuthTokenService`:
- `issuePair()` gains a `string $scope = 'full'` parameter. Abilities become `['*']` for `full`, `['onboarding:identity']` for `onboarding`. Currently hardcoded at `:45`:
  ```php
  $access = $user->createToken('access', ['*'], now()->addMinutes($accessMinutes));
  ```
- Persist `scope` on the `RefreshToken` row (`:48-55`).
- Add `issueOnboardingSession(User $user, Request $request): string` — 30-minute access token, abilities `['onboarding:identity']`, **no refresh token row**. Returns the PAT id so Phase 3 can store `onboarding_session_id`.
- `rotate()` must read `scope` off the `RefreshToken` row and pass it through to `issuePair()`. Since `issueOnboardingSession` creates no refresh row, onboarding sessions cannot rotate at all — belt and braces for **I3**.
- Add `upgradeToFullSession(User $user, Request $request): void` — revoke all tokens, then `issuePair($user, $request, scope: 'full')`.

`EnsureFullSession` middleware — fail-closed guard for **I2**:
```php
$token = $request->user()?->currentAccessToken();
abort_unless($token && $token->can('*'), 403, 'Complete identity verification first.');
```
Because full sessions hold `['*']`, this is a no-op for every existing user.

`bootstrap/app.php` (`:31-33`) — register aliases:
- `'full-session' => EnsureFullSession::class`
- `'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class` (not registered by Laravel 11 by default — verify before relying on it)

`config/services.php` under the existing `auth` block (`:91-113`) — add:
- `onboarding_session_ttl_minutes` (default 30)
- `activation_token_ttl_hours` (default 24 — see §2.2)
- `activation_resend_throttle_per_hour` (default 3)

**Acceptance:** full suite still green — this phase changes no route behaviour. Unit test: an `onboarding:identity` token fails `EnsureFullSession`; a `['*']` token passes.

**Rollback:** remove the alias registrations; the service additions are inert without callers.

</details>

---

### Phases 3–5 — ✅ DONE (shipped together)

**Merged as one cutover.** Phase 3 alone leaves the app unusable: the activation link stops setting a password while nothing else can create one, so no student could ever finish onboarding. Phases 4 (credential creation) and 5 (recoverable rejection) are the other half of the same atomic change.

**Backend shipped:**
- `ActivationController` rewritten — `show()` (PII stripped: no `name`/`student_id`/`program`, masked email only), `begin()` (scoped session, no credential, stamps `first_used_at` without spending the token), `resend()` (generic response + per-IP *and* per-email limiter).
- `OnboardingCredentialController::store` — the only place a student-chosen password is ever written. Requires `identity_verified`, uses `Password::defaults()`, spends the token, upgrades to a full session, audit-logs.
- `ActivationTokenIssuer` — replaces the mint-and-hash block duplicated across 3 controllers; enforces the 24h TTL in one place.
- `IdentityOnboardingController` liveness auto-pass and `FaceReviewController::approve` now set **`identity_verified`**, not `active`. `active` means verified **and** credentialed.
- `FaceReviewController::reject` — `identity_rejected` (recoverable) instead of `blocked`; revokes the attacker's session, resets the profile to `pending_id_scan`, and emails a fresh link to the address of record.
- Approve also emails a fresh link (`IdentityApprovedSetPasswordMail`) — without it every yellow/red-zone student is permanently locked out, holding neither password nor live token.
- `GranteeActivationInviteMail` no longer carries a temporary password; both Blade templates rewritten.
- Route split: identity funnel under `ability:onboarding:identity`, everything else under `full-session`.

**Routes:** `POST /activation/{token}` → `POST /activation/{token}/begin`; added `POST /activation/resend`, `POST /onboarding/credentials`.

**One real bug caught by the tests.** `EnsureFullSession` originally failed closed on a missing token, which broke **14 tests**: Sanctum's session/stateful path yields a `TransientToken` or no token at all, so session-authenticated requests were being rejected. Only a real PAT can be scope-limited, so the check now inspects the token and passes through when there isn't one. That was a genuine defect, not a test artefact.

**Behaviour change worth recording:** a mid-onboarding student can no longer sign in with a password to resume — no password exists yet. Resume happens by re-opening the activation link, which restores the funnel from persisted state. `test_login_is_refused_mid_onboarding_and_resume_happens_via_the_link` documents this.

**Verified:** `IdentityFirstActivationTest` — **19 passed**, covering I1/I2/I4/I7, token reuse-and-resume, PII non-disclosure, enumeration resistance. Full suite **13 failed / 184 passed**: the same 13 pre-existing failures as the Phase 0 baseline, with 30 more passing tests. Zero regressions.

**Still outstanding:** Phase 6 (frontend) has not landed, so the SPA still calls the removed `POST /api/activation/{token}` and has no set-password screen. **The student funnel is not usable end-to-end until Phase 6 ships.**

<details>
<summary>Original Phase 3 detail</summary>

### Phase 3 — Rewire activation (behavioural cutover)

**Goal:** The activation link no longer sets a password or issues a full session.

**Files**
- `backend/app/Http/Controllers/ActivationController.php`
- `backend/routes/api.php`
- `backend/app/Services/StudentOnboardingNavigator.php`
- `frontend/src/auth/ActivationResend.vue` *(new — "request a new link" form)*

**Changes**

`ActivationController::show` (`:16-29`) — strip PII per finding 1.2#2. Return only what the landing page needs to render:
```php
'data' => [
    'valid' => true,
    'expires_at' => $activation->expires_at,
    'masked_email' => $this->maskEmail($user->email),   // j••••@tcc.edu.ph
]
```
Drop `name`, `student_id`, `program` entirely. This also restores the masking intent at `StudentKycController.php:38-41`.

`ActivationController::resend` — **new**, implements the self-service recovery path from §2.3:
- Validate `email => ['required', 'email']`.
- Look up a student user whose `account_status` is not `active` and not `blocked`.
- If found, issue a fresh token via `ActivationTokenIssuer` (Phase 5) and mail the link.
- **Always** return the same generic 200 (`"If that email is on file, a new activation link is on its way."`) regardless of whether the account exists — no enumeration oracle. This matters more here than on login, because `GET /activation/{token}` previously leaked `student_id` and `program`.
- Rate-limit per IP **and** per email so a known address cannot be mail-bombed: `throttle:3,60` on the route plus a `RateLimiter::attempt` keyed on the normalized email.

`ActivationController::activate` → **replaced** by `begin()`:
- No `password` in the validation rules.
- Do **not** set `password`, `account_status = pending_kyc`, `activated_at`, or `email_verified_at`.
- Do **not** stamp `used_at`.
- Stamp `first_used_at`; revoke any prior onboarding session for this token; call `issueOnboardingSession()`; store the returned PAT id in `onboarding_session_id`.
- Advance `account_status` from `unverified` → `pending_kyc` only. That status now means "identity funnel in progress, no credential yet".
- Response shape stays compatible with the frontend: `user` payload plus `onboarding_next_step` / `onboarding_path` from the navigator.

`routes/api.php`:
- Replace `POST /activation/{token}` with `POST /activation/{token}/begin`, `throttle:10,1` (`:70`).
- Add `throttle:30,1` to `GET /activation/{token}` (`:69`).
- Add `POST /activation/resend` — public, `throttle:3,60`, inside the same `StartSession` group (§2.3).
- **Split the student group** (`:96-124`). Identity-funnel routes move into a sibling group that accepts either session type:
  ```php
  Route::middleware(['auth:sanctum', 'ability:onboarding:identity', 'role:student'])->group(function (): void {
      // /student/kyc (show, store)
      // /student/identity-onboarding/* (show, ocr-health, ocr-front, id-scan, liveness, references, photos)
      // POST /onboarding/credentials   ← Phase 4
  });
  ```
- Every remaining student route (`submission-window`, `requirement-vault/*`, `notifications`, `submissions/ocr`, `identity/face-verify`, `settings/pin`) gets `full-session` appended to the group middleware.
- Append `full-session` to the three privileged groups at `:129`, `:207`, `:222` as well. Defence in depth: an onboarding token could never satisfy `role:` anyway, but this keeps new routes fail-closed.

`StudentOnboardingNavigator` — add a `credentials` step between `liveness`/`face_review` and `done`, returned when identity is complete but `password` is still unusable. Map it to `/student/onboarding/set-password` in `frontendPath()`.

**Acceptance:**
- `POST /api/activation/{t}/begin` returns a session, and `users.password` is unchanged.
- An onboarding session hitting `GET /api/student/requirement-vault` → 403.
- Same session hitting `POST /api/student/identity-onboarding/id-scan` → 200.
- `test_activation_with_token_only_moves_student_to_kyc` is rewritten (see Phase 9).

**Rollback:** revert this commit. Phases 1–2 remain harmless.

</details>

---

### Phase 4 — Credential creation after verification

**Goal:** Password is set exactly once, only after identity is proven. Delivers **I1** and **I4**.

**Files**
- `backend/app/Http/Controllers/OnboardingCredentialController.php` *(new)*
- `backend/app/Http/Controllers/IdentityOnboardingController.php`
- `backend/app/Http/Controllers/FaceReviewController.php`
- `backend/routes/api.php`

**Changes**

New `OnboardingCredentialController::store`:
1. Require an onboarding-scoped session (route-level `ability:onboarding:identity`).
2. Assert `account_status === 'identity_verified'` — the new intermediate status. Reject `pending_face_review` with a clear message.
3. Validate `password => ['required', Password::defaults(), 'confirmed']`. Prefer `Password::defaults()` over the current bare `min:8`.
4. Set `password`, `email_verified_at = now()`, `activated_at = now()`, `account_status = 'active'`.
5. Consume the activation token: `used_at = now()`, clear `onboarding_session_id`.
6. `upgradeToFullSession()` — revokes the onboarding token and issues full access + refresh cookies.
7. Write an `AuditLog` row (`action: onboarding_credentials_created`) matching the existing pattern at `IdentityOnboardingController.php:335-353`.

`IdentityOnboardingController::storeLiveness` green-zone branch (`:321-334`) — currently:
```php
$user->forceFill(['account_status' => 'active'])->save();
$grantee->update(['status' => 'verified']);
```
becomes `account_status = 'identity_verified'`, `grantee.status = 'verified'`, and the response `next_step` returns `'credentials'` instead of `'done'`.

`FaceReviewController::approve` (`:81`) — same substitution: `'active'` → `'identity_verified'`. **Plus** the fix for the `pending_face_review` gap in §2.3: staff review can take days, the student holds no password, and their original token will have expired. So `approve` must also:
1. Issue a fresh activation token via `ActivationTokenIssuer` (Phase 5).
2. Email a "your identity was approved — set your password" link, reusing the existing notification path that `:85-105` already writes to.
3. Audit-log `action: identity_approved_credentials_link_sent`.

Without this, every yellow/red-zone student is permanently locked out — they have neither a password nor a live token. Phase 5 gives the reject path the same treatment; the two are symmetric.

**Note:** `active` now strictly means "identity verified **and** credentialed". `RequirementVaultController.php:260` already keys on `account_status !== 'active'`, so the vault gate keeps working with no edit. Verify no other code treats `active` as merely "identity done" — grep `account_status` across `backend/app` (currently 12 files).

**Acceptance:**
- Liveness auto-pass leaves `password` unusable and `email_verified_at` null.
- `POST /api/onboarding/credentials` from a `pending_face_review` account → 422.
- After credential creation, the vault is reachable and the onboarding token is revoked.

---

### Phase 5 — Recoverable rejection

**Goal:** A third party's failed face match must not permanently lock the real grantee. Delivers **I5**.

**Files**
- `backend/app/Http/Controllers/FaceReviewController.php`
- `backend/app/Services/ActivationTokenIssuer.php` *(new)*
- `backend/app/Mail/GranteeActivationInviteMail.php`

**Changes**

`FaceReviewController::reject` (`:129-133`) currently:
```php
$user->forceFill(['account_status' => 'blocked'])->save();
$grantee->update(['status' => 'identity_mismatch']);
```

Replace with a recovery path:
1. `account_status = 'identity_rejected'` (recoverable) rather than `blocked` (terminal).
2. Revoke all tokens for the user and clear `onboarding_session_id`.
3. Invalidate the activation token used for the attempt.
4. Issue a **fresh** activation token via the new `ActivationTokenIssuer` and email it to the address of record.
5. Reset the identity profile to `pending_id_scan` and purge the rejected scan/selfie/challenge artifacts using the existing `VaultFileStorage::deleteIfOwned` pattern.
6. Audit-log with `previous_actor_ip` so repeated takeover attempts on one grantee are visible.

`ActivationTokenIssuer` — extract the duplicated 6-line mint-and-hash block currently copy-pasted across four controllers (`MasterlistImportController`, `OnboardingCenterController`, `BatchActivationNotificationController`, `ActivationSeederController`). Single method:
```php
public function issueFor(User $user, ?int $ttlHours = null): string   // returns plain token
```
It invalidates prior unused tokens, creates the row, and applies the 24-hour default from Phase 2. Phase 7 migrates all four call sites onto it.

`StudentOnboardingNavigator` — `identity_rejected` maps to the `kyc`/`id_scan` restart, not `/locked`. Keep `blocked` terminal for genuine administrative blocks.

**Acceptance:** rejecting a face review leaves the account reachable via a newly emailed link; the rejected artifacts are gone from the vault disk; `blocked` still routes to `/locked`.

---

### Phase 6 — Frontend — ✅ DONE

**Shipped:**
- `Activate.vue` rewritten — posts to `/begin`, no password fields, shows only the masked email plus a 4-step preview ending in "Choose your password". Offers the resend link when the token is dead.
- `OnboardingSetPassword.vue` *(new)* — terminal step, posts to `/api/onboarding/credentials`, guards against deep-links when `onboarding_next_step !== 'credentials'`.
- `ActivationResend.vue` *(new)* — renders the generic confirmation **unconditionally**; branching on the result would reintroduce the enumeration oracle the API is careful to avoid.
- `session.ts` — added `identity_verified` / `identity_rejected` statuses and the `credentials` step.
- `onboardingResume.ts` — `credentials`/`identity_verified` → `/student/onboarding/set-password`; `identity_rejected` routes back to KYC (recoverable, not `/locked`).
- `createApp.ts` — registered both routes; added `identity_verified`/`identity_rejected` to `incompleteStatuses`; two new guards so set-password is reachable only when earned, and a verified-but-uncredentialed student cannot slip past it.
- `OnboardingLiveness.vue` — auto-pass now routes to set-password, not `/student`.
- `OnboardingPendingReview.vue` — copy corrected: approval leads to choosing a password, rejection allows retry, and **an email arrives either way**, so closing the page is safe. Its existing `studentHomePath` polling needed no change.
- `client.ts` — a 401 inside the funnel now routes to `/activation/resend?reason=session_expired` instead of `/login`. Sending a passwordless student to a login form was a dead end.
- 24 i18n keys across `en`/`tl`/`ceb`.

**Verified:** `npm run build` ✓ (25.2s). Lint errors **13 → 12** (one fewer, from deleting the dead `useStudentDocuments.ts`); **zero** errors in any file added or changed here — the remainder are pre-existing issues in `Login.vue`, `AppShell.vue`, `Landing.vue`, etc.

**Two bugs caught during this phase.** I guessed a non-existent export (`resumePathForUser`) and broke the build; the real name is `studentHomePath`. Fixing it surfaced that `studentHomePath` had no `credentials` case and let `identity_rejected` fall through to `/student` — either would have stranded students mid-funnel.

<details>
<summary>Original Phase 6 detail</summary>

### Phase 6 — Frontend

**Goal:** Match the new funnel, add the set-password step, keep the existing guard architecture.

**Files**
- `frontend/src/auth/session.ts`
- `frontend/src/auth/onboardingResume.ts`
- `frontend/src/createApp.ts`
- `frontend/src/modules/identity/OnboardingSetPassword.vue` *(new)*
- `frontend/src/modules/identity/OnboardingIdScan.vue`
- `frontend/src/modules/identity/OnboardingLiveness.vue`
- `frontend/src/modules/identity/OnboardingPendingReview.vue`
- `frontend/src/modules/kyc/StudentKyc.vue`
- `frontend/src/composables/useStudentKyc.ts`
- the activation view that posts to `/api/activation/{token}`

**Changes**

`session.ts:19` — extend the union:
```ts
onboarding_next_step?: "blocked" | "kyc" | "id_scan" | "liveness"
  | "face_review" | "credentials" | "done";
```
Add `"identity_verified" | "identity_rejected"` to `AuthAccountStatus`.

Activation view — drop the password fields, post to `/begin`, then route via `onboardingResume`. Remove any rendering of `name` / `student_id` / `program`, which the API no longer returns.

`OnboardingSetPassword.vue` — new terminal step. Posts to `/api/onboarding/credentials`, then routes to `/student`. Per `AGENTS.md`, follow `.codex/skills/frontend-performance/SKILL.md` for the loading and error states rather than inventing new patterns.

**Expired-link UX (§2.3).** The two clocks must read differently to the student, or every session timeout looks like a broken account:
- Expired *session* (403 / 401 mid-funnel) → "Your session timed out. Open your activation link again to pick up where you left off." Reassure that progress is saved, because it is.
- Expired *token* (422 from `/begin`) → route to `ActivationResend.vue`.

`ActivationResend.vue` — email field posting to `/api/activation/resend`. Render the generic success message unconditionally; never branch on whether the account exists, or the UI reintroduces the enumeration oracle the API is careful to avoid.

`createApp.ts:172-245` — add a `credentials` case to the `beforeEach` guard alongside the existing per-path checks at `:203-241`. Guard `/student/onboarding/set-password` so it is reachable only when `onboarding_next_step === 'credentials'`.

`onboardingResume.ts:45` — add `case "credentials": return "/student/onboarding/set-password";`.

`OnboardingLiveness.vue:505-519` — the green-zone handler currently hardcodes `account_status: payload.data.account_status || "active"` and maps `next_step === "done"` → `/student`. Update to honour `credentials`.

`api/client.ts` needs no change: it already sends `credentials: "include"` and never attaches a Bearer from storage (`:158-160`), so the onboarding cookie flows automatically.

**Acceptance:** `npm run build` and `npm run lint` clean; a full manual walkthrough from link click to vault unlock; a 403 from a vault call mid-onboarding surfaces as a redirect, not a crash.

</details>

---

### Phase 7 — Remove dormant credential paths — ✅ DONE

**The last shared-literal credential is gone.** `CollaboratorController::invite` no longer writes `bcrypt('password')` — invitees get `Hash::make(Str::random(64))` (unusable) plus an emailed invite link.

**Staff need their own activation path (§2.4).** Staff/admin/developer accounts never enter the biometric funnel, so there is no verification to wait for and the password *is* set from the link. New `StaffActivationController` + `StaffInviteMail` handle this, kept deliberately separate from `ActivationController` so a **student** token cannot be pointed at a staff endpoint to skip verification — the role is asserted in `findStaffToken()`, and a test covers it.

**Seeder guards.** New `Concerns\RestrictedToLocalEnvironment` trait applied to all six seeders that plant known credentials (`DemoUserSeeder`, `ReadyToSubmitStudentSeeder`, `BrandonStudentSeeder`, `MobileActivationSeeder`, `RafaelBalacuitTestSeeder`, `ActivationTestGranteesSeeder`). Verified both directions: seeds normally on `local`, and with `APP_ENV=production` it aborts with
`DemoUserSeeder seeds known test credentials and is restricted to the local/testing environment (current: production).`

`DemoUserSeeder` stays in `DatabaseSeeder` — the guard is the control, and removing it would break local setup.

**Stale `school_id` seeding found and fixed.** Running `db:seed` surfaced two seeders still creating the removed vault slot: `BrandonStudentSeeder::seedApprovedSchoolId()` (deleted) and `ReadyToSubmitStudentSeeder::PACKAGE_SLOTS` (trimmed to 3). Missed during the vault change because seeders were out of scope; they would have produced orphaned `school_id` rows that no code reads.

**Verified:** new `CollaboratorInviteTest` — **4 passed** (no usable password for staff or developer invites, staff can set their own, student token rejected by the staff endpoint). `db:seed` runs clean and reports "Vault slots (3)". Full suite **13 failed / 188 passed** — same 13 pre-existing failures, +4 passing.

**Note:** `MasterlistImportController`'s `temporaryPassword()` / `sendActivationEmail()` were already removed in Phases 3–5 when the mailable signature changed. Remaining `Hash::make('password')` hits are `DemoUserSeeder` (guarded) and `UserFactory` (test-only) — both correct.

<details>
<summary>Original Phase 7 detail</summary>

### Phase 7 — Remove dormant credential paths

**Goal:** Eliminate every remaining way to obtain a working password without identity verification. Covers findings **1** and **6** from the scan.

**Files**
- `backend/app/Http/Controllers/CollaboratorController.php`
- `backend/app/Http/Controllers/BatchActivationNotificationController.php`
- `backend/app/Http/Controllers/MasterlistImportController.php`
- `backend/app/Http/Controllers/OnboardingCenterController.php`
- `backend/app/Http/Controllers/ActivationSeederController.php`
- `backend/database/seeders/DemoUserSeeder.php`, `DatabaseSeeder.php`
- `backend/database/seeders/{ReadyToSubmitStudent,BrandonStudent,Rafael,Mobile,ActivationTestGrantees}Seeder.php`
- `backend/app/Mail/GranteeActivationInviteMail.php`

**Changes**

**Finding 1 — `CollaboratorController::invite` (`:50-58`)** is the highest-severity item after the ordering flaw:
```php
'role' => 'required|string|in:developer,admin,staff',
...
'password' => bcrypt('password'),
```
Anyone who can invite can mint a **developer** with a publicly guessable credential. Replace with `Hash::make(Str::random(64))` (unusable) plus an `ActivationTokenIssuer` token emailed to the invitee. Staff accounts do not go through the biometric funnel, so they set a password directly from their activation link — the Phase 3 `/begin` split applies to students only.

**Temporary passwords** — delete `temporaryPassword()` from all three controllers (`BatchActivationNotificationController.php:75-78`, `MasterlistImportController.php:240-243`, `OnboardingCenterController.php:202-205`) and stop writing `'password' => Hash::make($temporaryPassword)` at `BatchActivationNotificationController.php:41-44` and `MasterlistImportController.php:196`. Use `Hash::make(Str::random(64))`. Remove the `$temporaryPassword` argument from `GranteeActivationInviteMail` and its Blade template.

**Route all four token-minting sites through `ActivationTokenIssuer`** so the 24-hour TTL is enforced in one place.

**Finding 6 — `DemoUserSeeder`** creates `admin@unifast.gov.ph` / `password` with role `developer` and runs from `DatabaseSeeder::run()`. Either remove it from `DatabaseSeeder` or guard it:
```php
if (! app()->environment(['local', 'testing'])) {
    throw new RuntimeException('DemoUserSeeder is restricted to local/testing.');
}
```
Apply the same guard to the five test-data seeders using `'password'` or `'TCC-TEST-ACT1'`.

**Finally, reset the database** — `php artisan migrate:fresh --seed`. Per §4.3 there are no real grantees, so reseeding is the whole cutover. Do this last, after the temporary-password paths are gone, so reseeded accounts land on the identity-first flow.

**Acceptance:** grep for `bcrypt('password')`, `Hash::make('password')`, and `temporaryPassword` across `backend/app` and `backend/database` returns zero hits outside guarded local seeders. Running `db:seed` in a non-local env fails loudly. A reseeded student walks the full funnel with no password until `/onboarding/credentials`.

</details>

---

### Phase 8 — Remaining security findings

**Goal:** Close the findings unrelated to activation ordering. Independent of Phases 1–7 and parallelizable.

#### 8a — Login availability bug — ✅ DONE

**Shipped.** `config/services.php` gained a `recaptcha.secret` key and `AuthController::captchaIsValid` now reads `config('services.recaptcha.secret')`.

Verified against the exact production condition. With `php artisan config:cache` active:

```
env():    NULL                       ← old code path; failed closed, rejected every login
config(): 'test-secret-verify-123'   ← new code path
```

**Scope creep, deliberate:** grepping for other `env()` calls in `backend/app` found the same bug class in all four `activationUrl()` implementations (`MasterlistImportController:254`, `OnboardingCenterController:207`, `BatchActivationNotificationController:80`, plus `ActivationSeederController` parsing `.env` off disk with a regex at `:124,175`). Under `config:cache` every one silently fell back to `http://localhost:5173`, meaning **activation emails would ship unusable links** — the same root cause with a different symptom.

Fixed by adding `config/app.php` → `activation_frontend_url` and centralizing into `app/Support/ActivationLink.php` (`base()` / `for()`). It also handles a latent bug: `FRONTEND_URL` may hold a comma-separated CORS list (see `config/cors.php` and the local `.env`), which the old code would have embedded whole into a URL. `ActivationLink` takes the first origin.

`DeveloperServicesController::updateEnv` now also calls `config([...])` after `putenv()`, since `putenv` alone would not be observed by the config-backed read.

Remaining `env()` calls in `ChangelogController:13-14` (`GITHUB_REPO`, `GITHUB_TOKEN`) are the same latent bug — low impact (a developer-only changelog view), left for a follow-up.

<details>
<summary>Original 8a diagnosis (kept for reference)</summary>

`AuthController.php:405` reads `env('RECAPTCHA_SECRET_KEY')` directly. There is no `recaptcha` key in `config/services.php` (grep confirms zero matches). `backend/docker/entrypoint.sh` runs `php artisan config:cache`, after which `env()` returns `null`, and the failsafe at `:408-411` then fails closed → **every login rejected in production**.

Add to `config/services.php`:
```php
'recaptcha' => ['secret' => env('RECAPTCHA_SECRET_KEY')],
```
and read `config('services.recaptcha.secret')`. Then grep `backend/app` for any other `env(` outside config files.

</details>

#### 8b — Committed secrets removed — ✅ DONE

**Approach: remove the hazard, don't rotate into it.** Committing a *fresh* key would repeat the original mistake with a different value — still shared by every checkout, still in history forever. So all four committed secrets were replaced with placeholders plus generation instructions.

Confirmed the burned `APP_KEY` was **not** even the one in use: `backend/.env` holds a different key, so nothing depended on the committed value and the change is behaviour-neutral locally.

| File | Change |
|---|---|
| `.env.example` | `APP_KEY` blanked with generation commands; notes the old key is burned and must not be reused |
| `compose.yml` | Fallback removed → `"${APP_KEY:?set APP_KEY in .env - ...}"`, so compose **fails loudly** instead of silently booting on a shared key |
| `k8s/overlays/local/secret.local.yaml` | Now a documented template: `REPLACE_ME_*` placeholders for `APP_KEY`, `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `N8N_ENCRYPTION_KEY`; added `OCR_API_KEY`; points at a secret manager for deployed envs |
| `kustomization.yaml` | Documents the copy-to-`*.generated.yaml` workflow |
| `.gitignore` | `k8s/**/secret.*.generated.yaml` + `k8s/**/*.secret.yaml`. **`*.local` does not match `*.local.yaml`**, so filled-in copies were previously committable — verified with `git check-ignore -v`. |

**Verified:** `git grep` for both burned values returns **zero** hits across the tree. All three YAML files parse. Backend suite **13 failed / 191 passed** (baseline unchanged).

**Caught during verification:** the first `?err` message contained a colon, which is invalid unquoted YAML — `compose.yml` failed to parse. Quoting fixed it. Worth noting because a broken compose file would only have surfaced at `docker compose up`.

**Not verified (no Docker on this machine):** that `docker compose config` actually errors without `APP_KEY`. The `${VAR:?message}` form is standard Compose interpolation and the file parses, but the runtime behaviour is unconfirmed — worth one `docker compose config` run before relying on it.

**Still required at deploy:** git history retains both secrets. Since the repo is Lovable-connected, history rewriting is off the table, so treat them as permanently disclosed and never reuse them.

<details>
<summary>Original 8b detail</summary>

#### 8b — Rotate the committed `APP_KEY`

`k8s/overlays/local/secret.local.yaml:7` and `compose.yml:6` share a real `APP_KEY`, alongside `APP_DEBUG: true`. That key signs the `/api/signed/document-files/*` and `/api/signed/identity-photos/*` routes, whose *only* auth is the HMAC signature. Generate fresh keys per environment, source them from a secret store, and treat the git-history copy as burned. `N8N_ENCRYPTION_KEY` there is 64 `1`s — rotate too.

</details>

#### 8c — Gate `DeveloperServicesController` — ✅ DONE

**Shipped.** All three actions (`status`, `startCloudflare`, `startOcr`) now call a private `assertLocalEnvironment()` → `abort_unless(app()->environment('local'), 404)`. Returns 404 rather than 403 so the endpoints are indistinguishable from nonexistent routes off a dev machine.

Also in this pass:
- `escapeshellcmd` → `escapeshellarg` at the tunnel invocation (correct primitive for quoting an argument; `escapeshellcmd` does not neutralise quotes).
- The `.env` regex read in `status()` replaced with `ActivationLink::base()`.
- Class docblock records why the `role:developer,admin` route guard is insufficient on its own: an admin account on a deployed instance could otherwise expose the app publicly and read secrets.

The `.env` **write** path (`updateEnv`) is retained — it is the mechanism the local Cloudflare tunnel uses to publish its hostname — but is now unreachable outside `local`.

#### 8d — Column allowlist on the n8n student export — ✅ DONE

`TccUnifastStudentsController` now declares `ALLOWED_COLUMNS` (11 non-sensitive fields) and applies `->select($columns)` instead of `SELECT *`. The allowlist is intersected with `Schema::hasColumn`, so a future schema change can only narrow the export, never widen it. Aborts 503 if nothing exportable remains.

Contact details, birthdate, GWA, and `stored_path` are no longer reachable through this endpoint even with a valid shared secret.

#### 8e — Harden the OCR service — ✅ DONE

- `require_api_key` dependency on `/ocr/image` and `/ocr/pdf`, comparing `X-OCR-Key` with `hmac.compare_digest`. `/health` stays open so container probes keep working.
- Unset `OCR_API_KEY` leaves the service open **and logs a warning** — usable locally, loud enough to notice.
- `read_bounded_upload()` streams in 1 MB chunks and rejects at 20 MB with 413, so an oversized body is refused before being fully buffered. Per-type limits in `service.py` still apply.
- Laravel side: new `App\Support\OcrServiceRequest::headers()` centralises the header across all three call sites (`IdCardOcrService`, `PdfDocumentService`, `StudentDocumentOcrController`) — an unset key sends no header rather than an empty one.
- `compose.yml`: `ports:` → `expose:`, so 8001 is no longer published to the host. Documented in both `.env.example` files.

**Verified:** new `tests/test_api_key.py` — 4 passed (missing key 401, wrong key 401, correct key passes auth, `/health` open). Full OCR suite **19 passed**.

#### 8f — Lower-severity items — ✅ MOSTLY DONE

| Item | Status |
|---|---|
| CSP shipped dev directives to prod | ✅ `SecurityHeaders::contentSecurityPolicy()` is now environment-aware: `unsafe-eval` and localhost `connect-src` only outside prod, where `connect-src` narrows to the configured `FRONTEND_URL` origins plus their ws/wss forms. Added `frame-ancestors`, `base-uri`, `form-action`. `unsafe-inline` on `script-src` retained and documented — removing it needs a nonce/hash pass. |
| Two public routes unthrottled | ✅ `throttle:60,1` on `/terms/active` and `/faqs` |
| Any user could write arbitrary audit rows | ✅ `action`/`module` restricted to enums via `Rule::in`; every client row tagged `context.source = 'client'` so operator-written and client-reported entries stay distinguishable |
| Upload on the **public** disk skipping `SecureUpload` | ✅ `AdminStudentIdSampleController` rewritten: private disk, magic-byte check, server-generated filename derived from the *detected* MIME (client extension can no longer decide what lands on disk). No frontend consumer, so the disk move is safe. |
| `MasterlistImportController` stores before parsing | ⏳ deferred — needs care around the PhpSpreadsheet/Python parser handoff |
| `session.php` Secure cookie default | ⏳ env-only change, set `SESSION_SECURE_COOKIE=true` at deploy |
| `trustProxies(at: '*')` | ⏳ needs the real edge CIDRs |
| `DatabaseController` LIKE-across-all-columns | ⏳ deferred |
| `SystemHealthController` fake telemetry | ⏳ deferred (not a vulnerability) |

<details>
<summary>Original 8d detail</summary>

#### 8d — Column allowlist on the n8n student export

`TccUnifastStudentsController.php:31-41` runs `DB::table($table)->get()` — `SELECT *`, up to 1000 rows, cursor-paginated over `grantees` — behind a single static `X-TCC-UniFAST-Endpoint-Key`. The table name is regex-validated (no injection) and it correctly fails closed on an empty secret, but there is no column allowlist and no redaction, unlike `DatabaseViewerPolicy::redactRow`.

Add an explicit `select([...])` allowlist and reuse the redaction policy.

</details>

#### 8e — Harden the OCR service

`backend/ocr-service/app/main.py:57-96` has no auth and no rate limit on `/ocr/image` and `/ocr/pdf`. Size caps *are* enforced (`service.py:15,32`, `pdf_parser.py:45`) — but `await file.read()` buffers the whole body before that check, so a large POST still consumes memory. It binds `0.0.0.0` (`Dockerfile:24`) and `compose.yml:178` publishes `8001` to the host.

Add a shared-secret header dependency, check `Content-Length` before reading, stream to a bounded buffer, and stop publishing `8001` outside the compose network.

#### 8f — Lower severity

| Item | Location | Fix |
|---|---|---|
| CSP ships `unsafe-inline`/`unsafe-eval` + hardcoded `localhost` `connect-src` to prod | `SecurityHeaders.php:22` | environment-aware policy; model it on the stricter `FormSecurityHeaders.php:17` |
| Session cookie not `Secure` by default | `config/session.php:172` (`env('SESSION_SECURE_COOKIE')` → null) | set explicitly in prod env |
| `trustProxies(at: '*')` makes `$request->ip()` spoofable — it keys every throttle and audit log | `bootstrap/app.php:29` | narrow to known edge CIDRs |
| Two public routes with no throttle | `routes/api.php:73-74` | add `throttle:60,1` |
| Any authenticated user can write arbitrary audit rows at 240/min | `AuditEventController::store`, `routes/api.php:281` | restrict fields to an enum; drop the rate limit |
| Only upload landing on the **public** disk, skips `SecureUpload`, uses `getClientOriginalExtension()` | `AdminStudentIdSampleController.php:26-27` | private disk + `SecureUpload::assertAllowedMime` |
| Stores spreadsheet before parsing, no magic-byte check | `MasterlistImportController.php:78` | `SecureUpload` before persisting |
| `orWhere ... LIKE` across every non-redacted column — easy full-table scan | `DatabaseController.php:130-140` | require an explicit column, or cap searchable columns |
| Hardcoded fake telemetry (fabricated latency, uptime, deployment history) | `SystemHealthController.php:36-60,100-195` | wire to real metrics or label clearly as sample data — a thesis panel will ask |

---

### Phase 9 — Tests — ✅ DONE

**Backend suite: 209 passed / 1176 assertions** (baseline was 154 passed with 13 failures — all 13 fixed, see §4.6). OCR service: 19 passed. Frontend builds clean.

New suites:

| File | Covers |
|---|---|
| `OnboardingSessionScopeTest` (11) | I2/I3/I6, fail-closed behaviour, alias registration, null-scope-means-full |
| `IdentityFirstActivationTest` (19) | I1/I2/I4/I7, token reuse-and-resume, PII non-disclosure, enumeration resistance |
| `IdentityFirstJourneyTest` (3) | End-to-end link → vault, asserting the password hash is unchanged at every prior step |
| `CollaboratorInviteTest` (4) | No usable password for staff/developer invites; student token rejected by the staff endpoint |
| `VaultPinEnforcementTest` (5) | Vault PIN verified server-side, wrong-PIN lockout, audit trail, opt-in behaviour |
| `ocr-service/tests/test_api_key.py` (4) | Missing/wrong key → 401, correct key passes, `/health` open |

The journey test exists because every phase passed in isolation while the *seams*
between them were unverified — and the seams are where the real defects were
(`EnsureFullSession` failing closed on session auth, a non-existent
`resumePathForUser` export, missing `studentHomePath` cases).

**Not covered:** no manual browser walkthrough. Camera/liveness is mocked in tests,
so a real pass is still worth doing before the demo.

### Phase 9 — original plan

**Goal:** Lock in every invariant from §2.1.

**Files**
- `backend/tests/Feature/OnboardingFlowTest.php`
- `backend/tests/Feature/IdentityFirstActivationTest.php` *(new)*
- `backend/tests/Feature/OnboardingSessionScopeTest.php` *(new)*

**Rewrite:** `test_activation_with_token_only_moves_student_to_kyc` (`:119-142`) → `test_activation_begin_issues_onboarding_session_without_credential`, asserting the password hash is unchanged and `email_verified_at` is still null.

**New coverage**

| Invariant | Test |
|---|---|
| I1 | password unchanged after `/begin`, KYC, id-scan, and liveness |
| I2 | onboarding session → 403 on vault, notifications, settings/pin, and every `role:`-guarded route |
| I2 | onboarding session → 200 on each whitelisted identity route |
| I3 | `POST /auth/refresh` with an onboarding session does not yield a `['*']` token |
| I4 | `email_verified_at` null until `/onboarding/credentials` succeeds |
| I5 | face rejection ⇒ `identity_rejected`, fresh token issued, artifacts purged, account still reachable |
| I6 | staff/admin/developer login and a full student session are unaffected |
| — | `/onboarding/credentials` rejected while `pending_face_review` |
| — | `GET /activation/{token}` returns no `student_id`, `name`, or `program` |
| — | expired (>24h) token rejected at `/begin` |
| — | second `/begin` on the same token revokes the first onboarding session |
| I7 | `/begin` does **not** consume the token — a second `/begin` after session expiry still works |
| I7 | abandon after KYC → next `/begin` resumes at `id_scan`, not at `kyc` |
| I7 | abandon after ID scan → next `/begin` resumes at `liveness`, ID scan still complete |
| I7 | `FaceReviewController::approve` issues a fresh token and sends the set-password mail |
| I7 | `POST /activation/resend` returns an identical response for a known and an unknown email (no enumeration) |
| I7 | `/activation/resend` blocks the 4th attempt within an hour, per IP and per email |
| I7 | `/activation/resend` on an `active` account sends nothing but still returns the generic 200 |

**Acceptance:** `php artisan test` fully green; `npm run build` and `npm run lint` clean.

---

### Phase 10 — Documentation and diagram updates — ✅ DONE

`system_diagrams_documentation.md` updated to match the shipped code:

- **Flowchart §4.2** — `S2` is now "Issues Scoped Onboarding Session, NO password set yet"; new Phase 3B for credential creation, after the green-zone and staff-approval paths converge.
- **DFD §3.2** — `3.1` re-scoped to "Issue Scoped Onboarding Session"; `3.6` is "Create Account Credentials"; vault PIN moved to `3.7` with the correct `StudentSettingsController::updateSecurityPin` reference (the old citation pointed at a `storePin()` method that does not exist).
- **3-vs-4 slot drift** corrected in five places; store `D6` now names the three slot keys; `vault_pin_hash` removed from `D2` (that column does not exist — the PIN lives on `users.security_pin`).
- **New §3.4 "Security Design Rationale: Identity Before Credential"** — states the conventional pattern, why it was rejected, a property→mechanism table, the accepted trade-off, and the staff exception. Written for the defense question "what does the face match actually protect?", which the diagrams previously answered as "document uploads".
- **PIN enforcement note** added under `3.7` once the control became real (§4.7).

Developer-facing `ApiDocs.vue` and `FlowChart.vue` also updated — both still listed the removed vault school-ID endpoints.

### Phase 10 — original plan

**Goal:** Keep the thesis artifacts truthful — they are currently accurate about the *old* code.

**Files**
- `system_diagrams_documentation.md`
- `docs/identity-verification-flow.md`
- `thesis_chapter4_text.md`
- `docs/architecture-hardening-report.md`

**Changes**

1. **Flowchart §4.2** — `S2 [/Grantee Sets Password & Activates Account/]` currently precedes `S5`/`S7`. Move credential creation to a new node after `D3`/`D4` converge, before `S9`. This is the whole point of the change and the panel will look for it.
2. **DFD Level 2 §3.2** — subprocess `3.1` is described as "Validate Token & Initialize Account … Updated Credentials (D1)". Re-scope to "Validate Token & Issue Scoped Onboarding Session (no credential)". Add a `3.7 Create Account Credentials` subprocess writing to `D1`, fed by `3.4`/`3.5`.
3. **Fix the pre-existing 3-vs-4 slot drift** — §4.2 `S11` lists three documents (CoR, grade slip, specimen signatures) while the same flowchart's `E2` and store `D6` describe a "Complete 4-slot Document Vault". Reconcile against `RequirementVaultController` before submission.
4. **Fix the §3.2 code reference for 3.6** — it cites `IdentityOnboardingController.php (storePin)`, but no `storePin` method exists. The real implementation is `StudentSettingsController::updateSecurityPin` (`routes/api.php:123`).
5. Update the §5 cross-reference matrix and add a short "Security Design Rationale" subsection explaining why ownership is gated on biometrics — useful defense material.

---

## 4. Sequencing and Risk

### 4.1 Recommended merge order

```
Phase 8a  ─────────────────────────────►  ship immediately, standalone (live outage)
Phase 0 → 1 → 2  ──────────────────────►  additive, safe to merge alone
Phase 3 → 4 → 5 → 6 → 9  ──────────────►  single feature branch, one cutover
Phase 7  ──────────────────────────────►  after 4 (depends on ActivationTokenIssuer)
Phase 8b–8f  ──────────────────────────►  parallel, independent
Phase 10  ─────────────────────────────►  last
```

Phase 8a is a one-line config fix for a bug that rejects every production login. It should not wait behind this plan.

### 4.2 Risks

| Risk | Mitigation |
|---|---|
| ~~Students mid-onboarding at cutover hold a chosen password~~ | **Eliminated.** Pre-production, zero real grantees — `migrate:fresh --seed` is the entire cutover (§4.3). Revisit only if a pilot cohort ships before Phase 3 (§4.5). |
| Activation token valid for the whole funnel (§2.2) | 24h TTL, 30-min sessions, one session per token, `throttle:10,1`, no PII on the public GET |
| Student stuck in `pending_face_review` has no password and an expired token | `FaceReviewController::approve` mails a fresh set-password link (Phase 4); `/activation/resend` as fallback (§2.3) |
| Shorter 24h TTL increases expired-link support load | Self-service `/activation/resend` + distinct copy for session-expiry vs token-expiry (Phase 6) |
| `ability` middleware alias may be unregistered in Laravel 11 | Verified as a Phase 2 task; fall back to a thin custom middleware wrapping `tokenCan()` |
| Broadening `account_status` breaks an unaudited consumer | Phase 4 requires a full grep of `account_status` (12 files in `backend/app` today) |
| Frontend guard regressions | Phase 9 manual walkthrough plus the existing `beforeEach` tests |

### 4.3 Cutover — no migration needed

**Confirmed pre-production.** No real grantees exist yet. Corroborated by the repo: `k8s/overlays/` contains only `local`, `docs/deployment.md` states a production overlay still needs to be created, `APP_ENV: production` appears exactly once (`k8s/base/configmap.yaml:7`, unused), and `backend/.env` is `APP_ENV=local` with `APP_DEBUG=true`.

**Therefore: reset the database, write no migration code.**

```bash
php artisan migrate:fresh --seed
```

Run it after Phase 7, so reseeded accounts land on the identity-first flow with the temporary-password paths already removed.

This is the correct call, not a shortcut. Every account currently in the database is seeded test data (`DemoUserSeeder`, `ActivationSeederController`, and the five `*StudentSeeder` classes). A data-migration command would be code written to preserve fixtures — dead weight to maintain, and untestable against the real scenario it claims to handle.

**Two approaches explicitly rejected:**

*Legacy dual path* — flagging in-flight users to finish on the old flow. Passwords chosen under the old flow have **no verified owner**; that is the finding itself. A dual path keeps the vulnerable flow alive indefinitely, doubles the test surface, and invites the obvious question at defense: "so the old path still works?"

*Tiered reset command* — sorting existing users into keep/reset/review buckets. Correct design for a live cohort, unnecessary with zero real users. If a pilot cohort is onboarded **before** this work ships, that changes and the command becomes required; §4.5 records the design so it does not need re-deriving.

**Consequence for the rest of the plan:** Phase 0 no longer needs to audit `users.password` nullability against live data (the unusable-hash approach still applies to newly created accounts), and Phase 7 drops the migration command from its file list.

### 4.5 Deferred — cutover command, if a pilot cohort predates this work

Only build this if real grantees are onboarded before Phase 3 ships. Keep credentials only where provenance is verifiable:

| Bucket | Action | Rationale |
|---|---|---|
| `active` | Keep password; set `email_verified_at` if null | Already cleared the full funnel: ID OCR cross-matched against masterlist truth (`MasterlistTruthService`, subprocess 3.3) then a face match against the photo on the physical school ID (subprocess 3.4). An impostor without that ID cannot reach `active`, so the credential *is* bound to a verified identity. |
| `unverified`, `pending_kyc`, `pending_identity`, `pending_face_review` | Full reset + re-invite | Password exists with no identity proof behind it |
| `blocked` | Export for manual review; do not automate | Some rows may be victims of finding 1.2#5 (a rejected impostor blocked the real student) rather than genuine administrative blocks |

Reset per user: revoke all Sanctum tokens and `refresh_tokens` rows; overwrite `password` with `Hash::make(Str::random(64))` (unusable, not `NULL`, so no nullability migration); null `email_verified_at` and `activated_at`; set `account_status = 'unverified'`; delete unused activation tokens; issue a fresh 24-hour token via `ActivationTokenIssuer` and email it.

Ship as an idempotent artisan command with `--dry-run` (prints per-bucket counts before committing), chunked with queued mail, writing one `AuditLog` row per affected user (`action: identity_first_cutover_reset`).

### 4.4 Out of scope

- Adding a masterlist knowledge factor at `/begin` (birthdate / full student ID). Option A makes it unnecessary for ownership, but it is cheap defence in depth — track separately.
- Migrating inline `abort_unless` authorization to Laravel Policies. The codebase currently uses zero Policies or Gates; consistent, but worth a future pass.
- Replacing the fake telemetry in `SystemHealthController` with real metrics (8f flags it; the wiring is its own project).

---

## 4.7 Vault PIN made a real control — ✅ DONE

**Found while fixing an argument-order bug.** `confirm()` takes four parameters but
was called with five, so `$request->input('pin')` landed in the `$ipAddress`
position — writing the **plaintext PIN into `audit_logs.ip_address`** and dropping
the real IP. Fixing that exposed the larger issue: the PIN was never verified at all.

The UI prompted for it and disabled the submit button, so it looked enforced. But a
direct `POST /api/student/requirement-vault/confirm` with no PIN succeeded. DFD
subprocess 3.7 presented it as a security control; in practice it was UI-only.

Three options were weighed — enforce it, remove it, or relabel it as a
deliberate-action speed bump. Removal was defensible on its merits (the vault
already requires an authenticated session on an `active` account that cleared
biometric verification, so a 6-digit PIN adds little), but it would have traded a
code gap for a documentation gap. **Enforcement was chosen** so the diagram's claim
is true.

`ConfirmRequirementPackageService::assertSecurityPin()`:
- No-ops when the student has no PIN — the control stays opt-in.
- `Hash::check` against `users.security_pin`.
- Rate-limited (`VAULT_PIN_MAX_ATTEMPTS`, default 5, 15-minute lockout, keyed per
  user). Essential rather than defensive: 10⁶ combinations is trivially
  brute-forceable over an unthrottled API.
- Failures written to `audit_logs` as `vault_pin_rejected` with remaining attempts,
  so repeated guessing is visible.

Verified by `VaultPinEnforcementTest` (5 tests): missing PIN → 422, wrong PIN → 422
plus audit row, correct PIN → 200, no-PIN students unaffected, and lockout refuses
even the correct PIN once tripped.

---

## 5. Definition of Done

- [ ] `users.password` is never set before identity verification (I1)
- [ ] Onboarding-scoped tokens reach only the identity funnel (I2)
- [ ] Refresh rotation cannot escalate scope (I3)
- [ ] `email_verified_at` set only at credential creation (I4)
- [ ] Face rejection is recoverable by the legitimate grantee (I5)
- [ ] Existing staff/admin/developer and full student sessions unchanged (I6)
- [ ] No dead ends: abandonment resumes, expiry self-serves, face-review approval mails a fresh link (I7)
- [ ] No hardcoded or emailed passwords remain outside guarded local seeders
- [ ] Findings 1–7 from the security scan closed or explicitly deferred with rationale
- [ ] `php artisan test` green; `npm run build` and `npm run lint` clean
- [ ] Flowchart §4.2 and DFD §3.2 reflect identity-first ordering
- [ ] 3-vs-4 vault slot drift and the `storePin` reference corrected
