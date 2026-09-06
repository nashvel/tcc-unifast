# Facebook Page Connection and Social Posts RBAC

Status: Proposed for approval  
Date: 2026-09-06

## Objective

Replace the Facebook Page ID and Page access token environment configuration
with an administrator-managed OAuth connection inside the Social Posts module.
The application supports exactly one official institutional Facebook Page. Its
credentials are encrypted in MySQL, never returned to the browser, and never
sent to n8n.

Add granular module authorization so an administrator can grant a user either
read-only or read-write access through the existing RBAC role assignment
workflow.

## Confirmed product requirements

- The system has one active Facebook Page connection at a time.
- A developer or administrator can connect, replace, validate, or disconnect
  the Page from a Settings area inside Social Posts.
- Meta App ID and App Secret remain server environment secrets because they
  identify the application itself. Page and user access tokens do not remain
  in environment files.
- A user with `social_posts.read` can view the Page profile, posts, comments,
  reactions, connection health, and synchronization state.
- A user with `social_posts.write` can synchronize, create, schedule, publish,
  react, and comment. Write permission implies read permission in authorization
  checks and in the administrative RBAC UI.
- Only developers and administrators can manage the Facebook connection,
  regardless of the user's module write permission.
- Existing custom roles remain the mechanism by which administrators grant
  module permissions to users. Users keep an appropriate base staff role so
  existing full-session and staff route boundaries continue to apply.
- The backend validates the connection automatically. It refreshes or
  re-derives a Page token only when the Meta token flow permits it. If recovery
  is not possible, the connection becomes `reconnect_required` and
  administrators receive one notification when that state is entered.
- The browser never receives either a Page token, user token, App Secret, token
  debug payload, or a URL containing a token.
- The automatic post-sync watcher on initial Page load is removed. Profile and
  posts use explicit operations plus targeted query invalidation, preventing
  successful refreshes from also showing a stale configuration error toast.

## User experience

### Administrator connection flow

1. An administrator opens Social Posts and selects **Settings**.
2. The Facebook connection card shows one of: not configured, connecting,
   connected, reconnect required, or unavailable.
3. **Connect Facebook** requests a short-lived, single-use authorization URL
   from Laravel and redirects the browser to Meta.
4. Laravel validates the OAuth state and exchanges the authorization code on
   the server. It queries Pages managed by the authenticated Meta account.
5. If there is one eligible Page, the administrator confirms it. If there are
   several, the administrator selects one; selection does not mean the product
   supports several simultaneous connections.
6. Laravel persists only the selected Page and securely discards pending Page
   candidates after the short selection window.
7. The callback redirects to `/app/social-posts` with only a non-sensitive
   result code. The frontend invalidates the connection/profile/post queries
   and presents an accessible success or error message.

Replacing a connection follows the same flow and atomically replaces the one
stored connection after confirmation. A failed replacement leaves the current
valid connection intact.

### Access behavior

| Capability | No permission | Read | Write | Admin/developer |
| --- | --- | --- | --- | --- |
| See Social Posts navigation/page | No | Yes | Yes | Yes |
| View profile, posts, comments, reactions, health | No | Yes | Yes | Yes |
| Synchronize and mutate Page content | No | No | Yes | Yes |
| Connect, replace, validate, disconnect Page | No | No | No | Yes |

The backend is authoritative. Hiding frontend controls is only a usability
measure and does not replace API authorization.

## Architecture

### Token ownership

Laravel is the only component allowed to decrypt and use Facebook access
tokens. The existing Page ID/token reads from `config('services.facebook_page')`
are replaced by a `FacebookPageConnection` repository/model and a focused
`FacebookGraphService`.

n8n may continue to coordinate approval or scheduling events, but payloads sent
to n8n contain only internal post IDs, non-secret content, timestamps, and
signed webhook metadata. When a workflow requests publication, it calls a
protected Laravel endpoint; Laravel loads the encrypted Page token and calls
Meta. This prevents credentials from appearing in n8n workflow definitions,
environment variables, webhook bodies, or execution history.

The current n8n workflow and documentation are migrated as part of this change.
There is no runtime fallback to legacy `FACEBOOK_PAGE_ID` or
`FACEBOOK_PAGE_ACCESS_TOKEN` values after migration.

### Backend responsibilities

- `FacebookOAuthController`: start/callback/select/disconnect HTTP mapping and
  authorization context.
- `FacebookConnectionController`: safe status and manual validation endpoints.
- `FacebookOAuthService`: OAuth state, code exchange, managed Page discovery,
  and short-lived candidate storage.
- `FacebookGraphService`: versioned Graph HTTP client, Page profile/content
  operations, normalized errors, timeouts, and retries only for safe transient
  failures.
- `FacebookConnectionService`: transactional replacement, health transitions,
  token recovery, audit records, and administrator notifications.
- Existing social-post controllers delegate Facebook operations to these
  services and no longer read credentials from configuration.
- A scheduled command/job validates the connection daily. Manual validation
  uses the same service and is throttled.

Controllers remain responsible for validation, authorization, and response
mapping; services own orchestration and Graph behavior.

### Frontend responsibilities

- Add a focused `FacebookConnectionSettings` component rather than expanding
  the already-large Social Posts page.
- Use TanStack Vue Query for connection, profile, and post remote state with
  shared query keys and targeted invalidation after connect/validate/disconnect.
- Add permissions to the authenticated-user payload and use them in navigation,
  route guards, and control visibility.
- Provide skeleton, empty, error, offline, reconnect-required, and success
  states. Buttons expose loading state and remain keyboard accessible.
- Do not poll continuously or trigger a post synchronization merely because a
  profile query succeeded.
- Keep all user-facing copy in Vue I18n resources.

## Data model

Create an additive `facebook_page_connections` migration. The table permits a
single row and contains:

| Column | Purpose |
| --- | --- |
| `id` | Primary key; application enforces the singleton transactionally |
| `page_id` | Selected Meta Page identifier; unique and stored as a string |
| `page_name` | Last verified display name |
| `page_url` | Last verified public URL, nullable |
| `picture_url` / `cover_url` | Last verified remote image URLs, nullable |
| `page_access_token` | Encrypted Laravel cast backed by `TEXT` |
| `user_access_token` | Encrypted Laravel cast backed by `TEXT`, nullable |
| `status` | `connected`, `reconnect_required`, or `disconnected` |
| `token_expires_at` | Known token expiry, nullable |
| `data_access_expires_at` | Known data-access expiry, nullable |
| `last_validated_at` | Last successful validation |
| `last_error_at` | Last failed validation, nullable |
| `last_error_code` | Sanitized stable code, nullable |
| `connected_by` | Nullable FK to the administrator who selected the Page |
| timestamps | Creation/update audit context |

The model hides both token attributes from arrays and JSON. It uses encrypted
casts and datetime casts. Raw Graph error messages are not persisted because
they can contain request details; a stable internal error code is stored.

Add permissions through a new migration/seeder update:

- `social_posts.read`
- `social_posts.write`

Developer and administrator system roles receive both. Other users receive
neither by default; administrators grant them through custom roles. The RBAC
service prevents a write grant without its read dependency.

## API contract

Responses follow the project's existing snake_case JSON conventions. No
endpoint serializes token fields.

| Method and path | Authorization | Result |
| --- | --- | --- |
| `GET /api/social-media/facebook/connection` | `social_posts.read` | Safe status, Page metadata, validation timestamps, capabilities |
| `POST /api/social-media/facebook/oauth` | admin/developer | One-time authorization URL and expiry |
| `GET /api/integrations/facebook/callback` | valid OAuth state | Server-side exchange then safe redirect to frontend |
| `GET /api/social-media/facebook/pages` | admin/developer + pending state | Eligible pending Pages without tokens |
| `PUT /api/social-media/facebook/connection` | admin/developer | Atomically select/replace the singleton Page |
| `POST /api/social-media/facebook/connection/validate` | admin/developer | Validate/recover and return safe status |
| `DELETE /api/social-media/facebook/connection` | admin/developer | Remove stored connection and encrypted credentials |

Existing Social Posts read endpoints require `social_posts.read`. Existing
sync/create/dispatch/reaction/comment mutation endpoints require
`social_posts.write`. The external n8n callback remains protected by its
matching webhook secret and receives no Facebook credential.

HTTP behavior:

- `401`: unauthenticated or expired full session.
- `403`: authenticated but insufficient role/permission.
- `409`: connection operation conflicts with current singleton/pending state.
- `422`: invalid selection or callback parameters.
- `429`: connection/validation operation throttled.
- `503`: Meta or n8n dependency unavailable; response contains a safe error
  code and retryability flag, never a credential-bearing upstream message.

## OAuth and security controls

- App ID, App Secret, redirect URI, configurable Graph API version, and HTTP
  timeout remain in environment-backed Laravel configuration.
- OAuth state is cryptographically random, bound to the initiating user and
  intended redirect, single-use, and expires after ten minutes.
- Callback parameters are validated before any exchange. Tokens are exchanged
  server-side over HTTPS.
- Pending Page candidates are encrypted in server-side cache, bound to the
  initiating administrator, and expire after ten minutes.
- Connection management endpoints use administrator/developer authorization,
  full-session middleware, CSRF/Sanctum boundaries, throttling, transactions,
  and audit logging.
- Logs, exceptions, notifications, API resources, queued payloads, n8n
  payloads, and audit metadata redact tokens and authorization codes.
- Application backups inherit the operational database encryption/key
  protections. Losing or rotating `APP_KEY` requires the project's documented
  encrypted-data migration procedure.
- Disconnect deletes local credentials. External Meta permission revocation is
  not implied unless a separately confirmed Meta endpoint is implemented.
- The requested Page permissions are limited to those required for Page
  discovery, profile/content reads, publishing, comments, and reactions.

## Configuration changes

Add safe placeholders to the backend environment example:

```dotenv
FACEBOOK_APP_ID=
FACEBOOK_APP_SECRET=
FACEBOOK_REDIRECT_URI=http://127.0.0.1:8000/api/integrations/facebook/callback
FACEBOOK_GRAPH_API_VERSION=v26.0
FACEBOOK_GRAPH_TIMEOUT=15
```

Remove Page ID/token variables from Laravel and n8n examples, Compose service
configuration, workflow expressions, and integration documentation. Real
credentials are never committed. A local installation must reconnect through
the Settings UI after the migration; legacy environment credentials are not
silently imported into the database.

## Testing strategy

Implementation follows red-green-refactor in small slices.

Backend feature/unit coverage includes:

- encrypted-at-rest token fields and hidden serialization;
- safe status response with no tokens under success and error conditions;
- read, write, and connection-management authorization matrices;
- OAuth state expiry, replay, wrong-user, denial, and malformed callback cases;
- mocked code/token exchange and one/multiple/no eligible Page cases;
- atomic connection replacement and failed-replacement preservation;
- all profile/post/comment/reaction/publish paths load credentials from the
  database rather than environment configuration;
- invalid token transition, one-time administrator notification, recovery,
  and repeated scheduled validation idempotency;
- webhook signatures and absence of credentials from n8n payloads;
- regression for the contradictory success-plus-error toast behavior.

Verification commands:

```bash
cd backend && php artisan test
cd backend && ./vendor/bin/pint --test
cd frontend && npm run lint
cd frontend && npm run build
docker compose --env-file n8n/.env -f compose.yml config --quiet
```

Database-sensitive migration behavior is also verified against the host MySQL
database after the isolated SQLite test suite. A real Meta smoke test is run
only with the administrator's configured test/approved App and must not expose
credentials in command output.

## Delivery slices

1. Schema/model and permission seed changes with encryption/RBAC tests.
2. Safe connection-status API and authorization enforcement.
3. OAuth start/callback/Page-selection service with mocked Graph tests.
4. Database-backed Graph service migration for profile and post operations.
5. Credential-free n8n workflow/callback migration.
6. Settings UI, permission-aware navigation/actions, and toast regression fix.
7. Scheduled validation, audit/notification behavior, documentation, and full
   verification.

Each slice must leave the repository testable and avoid unrelated refactors.

## Documentation updates

Update the following when implementation lands:

- `SYSTEM_MAP.md`
- `docs/features-modules.md`
- `docs/database-schema-reference.md`
- `docs/n8n-facebook-batch-announcement.md`
- relevant environment examples and local setup notes
- an architecture decision record documenting Laravel as the exclusive token
  owner and the removal of token-bearing n8n execution data

## Boundaries

### Always

- Encrypt access tokens, authorize on the backend, redact sensitive values,
  use transactions for replacement, audit connection state changes, and use
  the existing secure webhook secret boundary.
- Preserve one institutional Page and existing full-session/onboarding guards.
- Use mocked Meta requests in automated tests.

### Ask before expanding

- Supporting multiple Pages, personal profiles, other social networks, Meta
  business portfolio administration, or direct external permission revocation.
- Adding a new frontend dependency or changing the existing RBAC model beyond
  the two module permissions and their dependency.

### Never

- Expose, log, commit, or send Facebook tokens/App Secret to the frontend or
  n8n.
- Fall back to Page credentials from environment variables after migration.
- Grant Social Posts access solely by hiding or showing frontend controls.
- Weaken authentication, full-session checks, webhook validation, throttling,
  audit logs, or RBAC to make the integration work.

## Acceptance criteria

The feature is complete when:

1. An administrator can connect exactly one eligible institutional Facebook
   Page from Social Posts without manually copying a Page token.
2. The selected Page remains usable after normal restarts because its token is
   encrypted in MySQL, and no Page token is configured in Laravel or n8n env.
3. Read-only users cannot perform any mutation; read-write users can perform
   content operations; only administrators/developers can change connection
   settings; direct API calls enforce the same rules.
4. Page profile and posts fetch without a false configuration toast after a
   successful response.
5. Invalid credentials produce a clear reconnect-required state and a one-time
   administrator notification without leaking upstream secrets.
6. n8n workflow definitions, payloads, and execution history contain no
   Facebook access token.
7. Relevant backend tests, formatter, frontend lint/build, Compose validation,
   and a MySQL migration check pass, with any unavailable real-Meta smoke test
   explicitly reported.

## Source notes

The implementation will follow Laravel 13's official encrypted casts, HTTP
client, scheduling, and authorization documentation. Meta's developer site was
rate-limited during specification research, so exact OAuth endpoint parameters,
token-exchange behavior, permission names, expiry semantics, and supported
Graph version must be verified against current official Meta documentation
before the OAuth slice is implemented. The Graph version remains configurable
to avoid embedding a transient version throughout application code.
