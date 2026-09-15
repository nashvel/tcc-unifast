# SIS OpenID Connect SSO Readiness

Status: Proposed for approval  
Date: 2026-09-06

## Objective

Make UniFAST TES ready to connect to one organization-operated Student
Information System (SIS) as an external identity provider. Existing students
can sign in through the SIS after the connection is configured, verified, and
enabled by a UniFAST administrator.

The integration uses OpenID Connect (OIDC) Discovery on top of OAuth 2.0 with
Authorization Code Flow and PKCE. It does not use GitHub-style personal access
tokens for browser login. Personal access tokens authorize scripts and API
clients; OIDC is the interoperable identity protocol for SSO.

## Confirmed requirements

- UniFAST consumes identity from a separate custom SIS SSO; UniFAST does not
  become an OAuth authorization server.
- Support one configured SIS identity provider at a time.
- Only student accounts can use SIS SSO.
- SIS SSO connects to an existing UniFAST student. It never creates a student
  or staff account automatically.
- Initial matching uses student ID and verified student email. Student name is
  corroborating/display information, not a unique identifier.
- After the first approved match, UniFAST binds the local student to the
  provider's immutable `issuer + sub` identity.
- If SIS student ID and email point to different UniFAST accounts, login is
  blocked and an administrator review is created.
- If the student ID identifies one account but name or email differs, the
  student enters a restricted **Under verification** state. The student sees a
  warning and administrators receive a review item.
- Password login remains available to students as a fallback.
- The existing **Continue with Google** login remains available. Staff continue
  using the existing password/Google options and can never authenticate through
  the SIS connection.
- Rollout modes are `disabled`, `pilot`, and `all_students`.
- Pilot access is limited to administrator-selected existing student accounts.
  Selected students use administrator-generated private pilot links. The
  normal public **Sign in with SIS** button appears only in `all_students`
  mode.
- **Remember me** creates a rotating UniFAST session lasting up to 30 days.
  Without it, the normal browser/session lifetime applies.
- UniFAST logout ends the UniFAST session only. It does not globally log the
  user out of the SIS.
- SIS claim changes do not silently overwrite UniFAST identity data. They
  create a visible verification/review workflow.

## Standards profile

The SIS provider must support:

- an HTTPS issuer and `/.well-known/openid-configuration` discovery document;
- Authorization Code Flow using response type `code`;
- PKCE with `S256`;
- exact pre-registered redirect URIs;
- signed ID tokens and a discoverable `jwks_uri`;
- the scopes `openid`, `profile`, and `email`, plus an agreed student identity
  claim;
- stable, never-reassigned `sub` values;
- a verified email claim or equivalent documented assurance;
- standard issuer, audience, expiry, issued-at, and nonce claims.

The initial provider profile does not support implicit flow, password grant,
tokens in URL fragments, unsigned ID tokens, wildcard callback URLs, or a
custom token format that cannot be validated using standard OIDC metadata.

## Identity contract

### Required claims

| Claim | Purpose | Rule |
| --- | --- | --- |
| `iss` | Provider identity | Must exactly match the configured issuer |
| `sub` | Stable SIS user identity | Required, non-empty, never used alone for first match |
| `aud` / `azp` | Intended UniFAST client | Must contain the configured client ID |
| `exp`, `iat` | Token lifetime | Enforce with small bounded clock skew |
| `nonce` | Bind ID token to login transaction | Must match and be single-use |
| `student_id` | Existing UniFAST student match | Required, exact normalized match |
| `email` | Corroborating account match | Required for first binding |
| `email_verified` | Email assurance | Must be true for first binding |
| `name` or standard name claims | Display and mismatch review | Never used as the unique key |

The production claim name for student ID can be mapped in administrator
settings because custom SIS providers may use a namespaced claim such as
`https://sis.example.edu/claims/student_id`. The configured claim must contain
a scalar string and is normalized using the existing UniFAST student-number
rules.

The provider does not control UniFAST roles, permissions, eligibility,
onboarding, account status, or approval state. OIDC establishes identity only;
UniFAST remains authoritative for authorization.

### First binding decision

```text
Valid OIDC response
    |
    +-- no existing UniFAST student ID ----------> reject; no auto-provisioning
    |
    +-- student ID maps to non-student account ---> block + security review
    |
    +-- student ID and email map to two users ----> block + identity review
    |
    +-- student ID matches; email/name differ ----> Under verification
    |
    `-- student ID + verified email agree --------> bind issuer + sub; sign in
```

A binding is unique on both `(issuer, subject)` and `(connection, user)`. One
SIS identity cannot bind to multiple UniFAST users, and one UniFAST student
cannot have multiple identities from the singleton SIS connection.

### Subsequent login decision

After binding, `issuer + sub` is the primary external identity key. Every login
still checks that:

- the local account exists and has the student role;
- the local account is active and not blocked/suspended;
- the configured provider and binding are enabled;
- the returned student ID still belongs to that student;
- relevant name/email claims have not entered an unresolved mismatch state.

A critical student-ID change or cross-account collision blocks access and
creates a review. A name/email change places the account Under verification
until an administrator accepts the updated identity data or rejects/unlinks the
binding.

## Under-verification behavior

SSO authentication can succeed while business access remains restricted. An
Under-verification student may access only:

- the verification status page;
- safe instructions/contact-support actions;
- their logout action;
- any existing narrowly scoped identity-correction route explicitly approved
  by the onboarding workflow.

They cannot access student records, documents, forms, academic data, or other
normal portal features until review succeeds. This uses the existing onboarding
navigator and route guards where possible rather than creating a competing
authorization system.

The administrator review shows local and SIS student ID, name, and email side
by side, the match reason, timestamps, provider identity, and prior decisions.
It never displays authorization codes, access tokens, ID tokens, client secret,
or raw token payloads. Approval records the accepted mapping/data changes;
rejection preserves the local record and disables/unlinks the attempted
binding.

## Rollout lifecycle

### Connection states

```text
draft -> discovery_verified -> pilot -> all_students
   |              |              |          |
   `-----------> disabled <-------+----------+
                      |
               reconnect_required
```

- `draft`: configuration saved but no login is available.
- `discovery_verified`: issuer metadata and client configuration pass safe
  validation; administrator can select pilot students.
- `pilot`: only unexpired signed pilot links for selected students can begin SIS
  login.
- `all_students`: the standard login page shows **Sign in with {SIS name}**.
- `disabled`: new SIS login attempts are rejected; existing local sessions are
  handled by the chosen disable action.
- `reconnect_required`: discovery, key, or client authentication failure pauses
  new SSO and alerts administrators.

Changing to `all_students` requires an explicit administrator confirmation
showing discovery health, successful pilot count, unresolved review count, and
the rollback action.

### Private pilot links

An administrator selects an existing student and creates a time-limited,
single-use pilot invitation. The stored invitation contains only a hash of the
random token, the selected user ID, creator, expiry, and consumed timestamp.
The link does not contain student data.

Opening the link begins an OIDC transaction bound to that selected account.
After callback, the returned SIS identity must independently match that same
student. A forwarded link therefore cannot bind a different SIS user.

## Administrator configuration

Add a developer/administrator-only SIS SSO card under Integration Settings.
The non-technical workflow is:

1. Enter the connection display name, HTTPS issuer URL, client ID, client
   secret, and student-ID claim name.
2. The SIS operator registers the exact callback URI shown by UniFAST.
3. Select **Validate configuration**. UniFAST fetches discovery/JWKS metadata,
   checks the supported secure flow, and returns a human-readable result.
4. Save as draft, select pilot students, and generate pilot links.
5. Review pilot login health and identity mismatches.
6. Explicitly enable **All students** when ready.

The card shows status, issuer host, last validation, JWKS refresh health, pilot
success/failure counts, pending identity reviews, and safe error codes. The
client secret is write-only: the API and UI never return its stored value.

Replacing an active provider follows a new draft/test process and does not
silently rebind existing identities. Provider replacement or issuer change is a
high-impact audited operation requiring confirmation.

## Login experience

The standard login page continues to show email/password and **Continue with
Google**. It adds **Sign in with {SIS name}** only when:

- the connection is in `all_students` mode;
- current discovery/connection health permits login; and
- the backend's public auth-capabilities response says SIS SSO is available.

The frontend never decides availability from environment variables. It reads a
safe public capability response containing display name, optional approved
logo, availability, and maintenance message—never issuer internals or client
credentials.

The login request may include `remember_me=true`. Laravel stores it in the
short-lived server-side OIDC transaction; it is not trusted from callback query
parameters. On success, the existing `AuthTokenService` issues the normal
HttpOnly cookie pair with either standard or 30-day maximum refresh lifetime.
Refresh tokens continue rotating, and account suspension, password/security
events, manual revocation, and binding removal can revoke remembered sessions.

OIDC callback redirects contain only a safe result code. Tokens are never put
in frontend URLs, local storage, or JavaScript-readable cookies.

## Compatibility with existing authentication

- Password login remains unchanged and available as the student fallback.
- Existing Google OAuth remains a separate provider and continues to use its
  current account binding.
- Staff, head, administrator, and developer roles are rejected from SIS SSO
  even if their email exists in the SIS.
- Existing 2FA remains enforced after SIS identity verification when enabled
  for the matched UniFAST account. SSO does not silently weaken local 2FA.
- Existing CAPTCHA, throttling, audit, account-status, full-session, onboarding,
  and refresh-token controls remain intact.
- SIS SSO does not bypass incomplete KYC, identity review, liveness, or other
  onboarding requirements. After login, the existing navigator chooses the
  allowed route.

## Security design

### OIDC transaction

For each login attempt Laravel generates and stores server-side:

- a cryptographically random single-use `state` value;
- a transaction-specific `nonce`;
- a PKCE verifier and `S256` challenge;
- intended provider/connection, pilot user if applicable, remember-me choice,
  safe return path, creation time, and expiry.

The transaction expires after ten minutes and is consumed once. State is bound
to the same browser session. Callback errors and provider denial consume the
transaction so it cannot be replayed.

### Provider and token validation

- Require HTTPS and exact issuer equality across configuration, discovery, and
  ID token.
- Prevent server-side request forgery when retrieving discovery/JWKS: reject
  credentials in URLs, fragments, redirects to unapproved hosts, loopback,
  link-local/private/reserved addresses, DNS rebinding, and non-HTTPS targets.
- Cache discovery and JWKS with bounded lifetimes; refresh once for an unknown
  key ID, then fail closed.
- Allow only asymmetric signing algorithms explicitly advertised and approved
  by policy; reject `none` and symmetric provider-supplied ID token signing.
- Validate signature, `iss`, `aud`, `azp` where required, `exp`, `iat`, `nonce`,
  and token type before reading identity claims.
- Exchange codes only at the discovered token endpoint using the exact callback
  URI and PKCE verifier.
- Apply short connect/read timeouts, response-size limits, TLS verification,
  content-type validation, throttling, and sanitized error mapping.
- Do not persist the provider access token, refresh token, authorization code,
  or raw ID token after the login transaction. If UserInfo is required, call it
  immediately and discard the access token.
- Never log request authorization headers, codes, tokens, secrets, PKCE
  verifiers, nonce values, or raw identity payloads.

### Administrative secrets

The singleton connection stores the client secret using a Laravel encrypted
cast backed by `TEXT` and hides it from serialization. The value is write-only
in the UI. Audit records state that a secret was replaced without recording the
secret or a reversible derivative.

Changing issuer, client ID, secret, claim mapping, rollout mode, or identity
binding requires full-session administrator/developer authorization,
throttling, validation, confirmation for high-impact changes, and an audit log.

### Session and logout

UniFAST maintains its own rotating HttpOnly access/refresh cookies. Remembered
sessions last at most 30 days and remain revocable. Local logout revokes the
UniFAST token family and clears cookies but does not call SIS end-session or
global logout in the first release. The UI explains that the SIS browser
session may remain active; a later login may therefore complete without another
password prompt.

## Proposed data model

Use additive Laravel migrations:

| Table | Responsibility |
| --- | --- |
| `oidc_connections` | Singleton provider, display configuration, issuer/client ID, encrypted secret, claim mapping, rollout/health state |
| `external_identities` | User binding to normalized issuer and immutable subject, last verified claim fingerprints/timestamps |
| `sso_pilot_users` | Administrator-selected students eligible during pilot |
| `sso_pilot_invitations` | Hashed single-use pilot links and expiry |
| `sso_login_transactions` | Hashed state, encrypted short-lived verifier/nonce context, expiry/consumption |
| `sso_identity_reviews` | Mismatch type, safe local/provider identity snapshot, status, reviewer decision |

Where practical, short-lived login transactions may use Redis with atomic
consume semantics instead of MySQL. The durable audit, binding, rollout, pilot,
and review records remain in MySQL.

Uniqueness and integrity constraints include:

- one active connection/singleton key;
- unique normalized `(issuer, subject)`;
- unique `(connection_id, user_id)` binding;
- pilot/review foreign keys to existing users;
- application and database checks preventing non-student bindings.

Identity claims are personal data. Store only fields/fingerprints required for
matching, review, security audit, and troubleshooting, using the project's
existing encryption/data handling where appropriate.

## API contract

Responses follow the existing snake_case conventions.

### Public authentication endpoints

| Method and path | Purpose |
| --- | --- |
| `GET /api/auth/capabilities` | Safe login options and SIS display availability |
| `POST /api/auth/sis/redirect` | Create state/nonce/PKCE transaction and return authorization URL |
| `GET /api/auth/sis/callback` | Validate callback, bind/match identity, issue session, safe redirect |
| `POST /api/auth/sis/pilot/{token}/redirect` | Consume a selected student's private pilot invitation and start OIDC |

The redirect-start endpoint accepts only `remember_me` and an allowlisted return
intent. The callback accepts standard `code`, `state`, provider error fields,
and optional issuer response parameters required by the selected security
profile. It never accepts a local user ID or role from the browser.

### Administrator endpoints

| Method and path | Purpose |
| --- | --- |
| `GET /api/integrations/sis-sso` | Safe connection, rollout, pilot, and health summary |
| `PUT /api/integrations/sis-sso` | Create/update draft write-only configuration |
| `POST /api/integrations/sis-sso/validate` | Validate discovery/JWKS and secure capabilities |
| `PUT /api/integrations/sis-sso/rollout` | Change disabled/pilot/all-students mode |
| `GET /api/integrations/sis-sso/pilots` | List selected pilot students and outcomes |
| `POST /api/integrations/sis-sso/pilots` | Add selected existing student |
| `DELETE /api/integrations/sis-sso/pilots/{user}` | Remove pilot eligibility |
| `POST /api/integrations/sis-sso/pilots/{user}/invite` | Generate one-time private pilot link |
| `GET /api/integrations/sis-sso/reviews` | Paginated identity mismatch reviews |
| `POST /api/integrations/sis-sso/reviews/{review}/decide` | Approve/reject identity review |
| `DELETE /api/integrations/sis-sso/connection` | Disable/unlink configuration with explicit session policy |

Public errors use stable, non-sensitive codes such as `sso_unavailable`,
`sso_not_eligible`, `sso_identity_review`, or `sso_account_blocked`. Provider
responses, secrets, and raw validation failures are not reflected to users.

## Service boundaries

- `OidcDiscoveryService`: safe discovery/JWKS retrieval, validation, caching,
  and key rotation.
- `OidcAuthorizationService`: state/nonce/PKCE transaction creation and atomic
  callback consumption.
- `OidcTokenValidator`: code exchange and strict ID-token validation.
- `SsoIdentityMatcher`: student-only matching and durable binding decisions.
- `SsoIdentityReviewService`: Under-verification transitions, administrator
  review, notifications, and audit records.
- `SsoRolloutService`: singleton lifecycle, pilots, invitations, and activation
  gates.
- Existing `AuthTokenService`, `TwoFactorAuthService`, and
  `StudentOnboardingNavigator` retain session, 2FA, and route ownership.

Controllers validate requests, establish authorization context, delegate to
services, and map safe responses. They do not implement OIDC cryptography or
identity matching inline.

## User interfaces

### Integration Settings

- Configuration form with issuer/client fields and write-only client secret.
- Discovery and security capability validation with accessible loading,
  success, and safe error states.
- Rollout badge, health, last verified time, callback URI copy action, and
  reconnect/disable controls.
- Searchable pilot-student selection, expiring invitation generation, and pilot
  outcome summary.
- Identity review queue with side-by-side local/SIS data and explicit decisions.
- Confirmation screen before all-student rollout or provider replacement.

### Student authentication

- Existing password and Google actions remain.
- Public SIS button appears only in all-student mode.
- Pilot students use the private invitation URL.
- The start action exposes loading/error states and prevents duplicate clicks.
- Callback uses a full-page navigation but returns to a token-free frontend URL.
- Under-verification students receive plain-language mismatch guidance without
  seeing technical claims or internal identifiers.
- All copy is translatable with Vue I18n, and focus/error behavior is accessible.

Remote configuration/review state uses TanStack Vue Query with shared keys and
targeted invalidation. No continuous polling is added; existing reactive event
patterns may update review and rollout status.

## Failure behavior

| Condition | Result |
| --- | --- |
| Provider not configured/disabled | SIS button hidden; direct starts rejected |
| Discovery/JWKS unavailable | Password/Google remain available; SIS reports temporary unavailability |
| Invalid state/nonce/PKCE | Fail closed, consume transaction, audit safe reason |
| Invalid signature/issuer/audience | Block login and create security audit event |
| No existing student ID | Reject without account creation |
| Student ID/email cross-account collision | Block and create admin review |
| Name/email mismatch for same student | Restricted Under-verification state and review |
| Non-student local account | Reject and audit role-boundary attempt |
| Suspended/blocked local account | Reject regardless of valid SIS authentication |
| Unknown signing key after one refresh | Fail closed and mark provider unhealthy |
| Pilot user without valid invitation | Reject without revealing eligibility details |

Provider failure never disables password or Google fallback login.

## Testing strategy

Implementation follows red-green-refactor and uses a local fake OIDC provider or
HTTP fakes in automated tests. Production SIS credentials are never used in the
test suite.

Backend coverage includes:

- singleton connection and encrypted/write-only client secret;
- discovery validation, issuer mismatch, HTTPS/SSRF/redirect rejection, cache,
  and JWKS rotation;
- state, nonce, PKCE, expiry, replay, wrong-browser, denial, and malformed
  callback cases;
- ID-token signature, algorithm, issuer, audience/authorized-party, expiry,
  issued-at, and nonce validation;
- required/mapped claims and verified-email requirements;
- exact existing-student match, no auto-provisioning, non-student rejection,
  collision, mismatch, binding uniqueness, and subsequent login;
- disabled/pilot/all-students rollout matrix and private invitation forwarding,
  reuse, and expiry;
- Under-verification restrictions, administrator approval/rejection, and
  notifications;
- password/Google compatibility, local 2FA, account suspension, onboarding
  navigation, normal session, and 30-day rotating remembered session;
- token/secret/code/verifier redaction from API, logs, audit, jobs, and URLs;
- administrator authorization, throttling, validation, and audit events.

Frontend verification covers login capability rendering, pilot/public
visibility, loading/error/accessibility states, token-free callback handling,
Under-verification routing, and Integration Settings behavior.

Repository checks include:

```bash
cd backend && php artisan test
cd backend && ./vendor/bin/pint --test
cd frontend && npm run lint
cd frontend && npm run build
```

A staging smoke test uses fictional selected students and the organization's
non-production SIS OIDC client before all-student rollout.

## Delivery sequence

1. Architecture decision record, threat model, provider metadata/claim contract,
   and fake OIDC test provider.
2. Singleton encrypted connection model, discovery validation, safe admin API,
   and settings UI.
3. State/nonce/PKCE authorization flow and strict ID-token validation.
4. Existing-student matcher, durable bindings, mismatch reviews, and restricted
   Under-verification route.
5. Pilot selection and private single-use invitation links.
6. Compatibility with AuthTokenService, remembered sessions, 2FA, Google,
   password, and onboarding navigator.
7. All-student activation gate, public login capability/button, notifications,
   audit/observability, documentation, and staging rollout verification.

Every slice remains feature-flagged or disabled until its security tests pass.
The all-student mode cannot be enabled before successful pilot evidence exists.

## Boundaries

### Always

- Use OIDC Discovery, Authorization Code Flow, PKCE `S256`, state, nonce,
  strict token verification, existing-student matching, server-owned sessions,
  audit logs, and a reversible pilot rollout.
- Keep UniFAST authoritative for roles, permissions, account state, and
  onboarding.
- Preserve password/Google fallback and current security gates.

### Ask before expanding

- Supporting multiple SIS providers, just-in-time student creation, staff SSO,
  SIS-driven roles/groups, global/provider logout, dynamic client registration,
  SCIM provisioning, or API access for the SIS.
- Issuing GitHub-style personal access tokens or making UniFAST an OAuth server.

### Never

- Match by name alone, create accounts from untrusted claims, accept roles from
  the SIS, or bind one external identity to multiple local users.
- Use implicit/password flows, unsigned tokens, wildcard callbacks, tokens in
  URLs/local storage, or raw tokens in logs.
- Let a successful external login bypass local suspension, 2FA, full-session,
  onboarding, identity review, or RBAC controls.

## Acceptance criteria

The integration is complete when:

1. An administrator can configure and validate one standards-compliant SIS
   issuer without exposing the client secret.
2. Selected existing students can use time-limited pilot invitations; forwarded
   or reused invitations cannot bind the wrong account.
3. A valid student ID plus verified matching email binds to the immutable
   `issuer + sub`; unknown students are never auto-created.
4. Cross-account collisions are blocked, while identity-data discrepancies
   enter the restricted Under-verification workflow and appear to admins.
5. After a successful pilot, an administrator can enable the public SIS button
   for all existing students with an explicit confirmation and rollback path.
6. Password and Google login continue working, staff cannot use SIS SSO, and
   all existing account/onboarding/2FA controls remain effective.
7. Normal sessions and selected 30-day remembered sessions rotate and revoke
   correctly; frontend URLs and storage never contain provider tokens.
8. Automated security/behavior tests, formatter, frontend lint/build, and a
   fictional-user staging OIDC smoke test pass.

## Documentation deliverables

Implementation must update:

- `SYSTEM_MAP.md` and `docs/features-modules.md`;
- authentication/security and environment configuration documentation;
- an ADR for the singleton external OIDC provider, local authorization
  ownership, pilot rollout, and non-provisioning decision;
- an SIS operator integration guide listing callback URL, discovery/claim
  contract, signing requirements, test procedure, and safe error codes;
- an administrator guide for configuration, pilots, mismatch review,
  activation, disabling, and recovery.

## Authoritative source notes

- OpenID Connect defines an identity layer on OAuth 2.0 and Discovery provides
  issuer metadata, endpoint locations, and signing-key information:
  <https://openid.net/specs/openid-connect-discovery-1_0.html>.
- OAuth 2.0 Security Best Current Practice requires protection against CSRF and
  authorization-code injection, recommends PKCE for confidential clients, and
  advises clients to use Authorization Code Flow instead of implicit flow:
  <https://www.rfc-editor.org/info/rfc9700/>.
- OAuth Authorization Server Metadata standardizes publication of issuer
  capabilities and endpoint metadata:
  <https://www.rfc-editor.org/info/rfc8414/>.

The exact SIS issuer, claims, supported algorithms, scopes, client
authentication method, key rotation practice, and test environment must be
confirmed with the SIS operator before implementation. Security requirements
in this specification take precedence over provider-specific shortcuts.
