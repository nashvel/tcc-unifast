# Continuity environment and n8n setup

The environment, importable scheduler, encrypted Google OAuth connection,
signed sync endpoint, queued module sync, revision/review records, and initial
administrator page are implemented. This is **not yet credentials-only ready**:
primary-storage integration, intake, complete module coverage, recovery and
end-to-end verification remain unfinished. Do not activate the workflow or
switch production storage yet.

## Laravel

In `backend/.env`, complete `GOOGLE_WORKSPACE_CLIENT_ID` and
`GOOGLE_WORKSPACE_CLIENT_SECRET` using a Google Cloud Web application OAuth
client dedicated to the continuity integration. Keep the student Google login
client unchanged. Register this exact local redirect URI:

`http://127.0.0.1:8000/api/integrations/google-workspace/callback`

Enable Drive, Sheets and Forms APIs in that Google Cloud project. Use the
school's dedicated Workspace account through `/app/integrations/workspace`.
Production needs an HTTPS redirect matching its registered OAuth client.

`GOOGLE_WORKSPACE_HTTP_TIMEOUT=20` is prepared. Leave
`CONTINUITY_ENABLED=false` until the complete integration is verified.
OAuth refresh/access tokens and selected resource IDs will be stored in MySQL;
do not put them into n8n or the environment files.

The local `CONTINUITY_SYNC_SECRET` has been generated and matched in
`backend/.env` and `n8n/.env`. It signs scheduler requests; it is not a Google
credential. Keep it private. The examples intentionally contain no value.

## n8n import

Import `docs/n8n-google-workspace-continuity-workflow.json` using n8n's
**Import from File** option. Leave it inactive until the backend is ready.

The workflow contains an hourly Schedule Trigger and a Manual Trigger, followed
by an HMAC signing node, a Laravel HTTP request and a run acknowledgement.
The schedule uses Asia/Manila; edit the Hourly Sync node to change frequency
and publish the workflow when ready. Manual execution is for n8n operators;
the application's administrator Sync now button is a separate backend action.

`n8n/.env` contains `LARAVEL_API_URL=http://host.docker.internal:8000` and
`NODE_FUNCTION_ALLOW_BUILTIN=crypto`. The root Compose file already loads this
env file. The alternate Compose file explicitly maps these settings.
If using external task runners, configure the crypto module allowance in the
runner environment too. Do not enable arbitrary modules.

After backend readiness, reload container environment with:

```bash
docker compose --env-file n8n/.env -f compose.yml up -d --no-deps --force-recreate n8n
```

No container restart, workflow import or publication is performed merely by
creating these files.

## Implemented backend trigger contract

Endpoint: `POST /api/internal/n8n/continuity-sync`.

Request JSON contains `request_id` (UUID) and `source` (`n8n`). Headers:

- `X-Continuity-Timestamp`: Unix seconds.
- `X-Continuity-Signature`: hex HMAC-SHA256 signature.
- `Idempotency-Key`: same UUID as `request_id`.

Sign the exact bytes:

```text
timestamp + "\nPOST\n/api/internal/n8n/continuity-sync\n" + raw_request_body
```

Laravel compares signatures in constant time, rejects missing secrets and
timestamps outside 300 seconds, validates the UUID/body, and persists the run
before queueing work. Identical retries return the same run; reusing an ID
with different content fails. Signatures are checked before trusting the body.
The response is `{ "data": { "run_id": "uuid", "status": "queued" } }` or
the existing run status. Disabled integration returns a non-success response.
Queue-dispatch failure recovery still needs implementation; a persisted queued
run alone does not prove a job reached the worker.

The HTTP node retries up to three times using the identical signed body. It
does not follow redirects. Use HTTPS outside the documented local Docker-host
connection. An acknowledged queue request is not evidence that data sync has
completed; the administrator page shows persisted run outcomes.

Automatic and manual execution payload persistence is disabled to avoid
retaining signed request details. n8n editors can still see live execution
data; restrict workflow editor access to authorized operators.

## Access and token safeguards

Only active administrators/developers can manage the connection and reviews.
Staff grants require an active eligible account with a verified linked Google
email. Before export, workbook permissions are checked across all result pages:
unapproved users, groups, public/domain sharing and stronger-than-approved
roles stop synchronization. The integration account is the sole implicit
principal. A read-only grant does not authorize an inherited writer/manager role.

Use a dedicated Shared Drive with restricted membership. File-level grants do
not neutralize inherited Shared Drive access. The application stops new exports
when it detects excessive access; it does not retroactively conceal data already
shared or automatically remove organizational Drive members. See Google's
[sharing rules](https://developers.google.com/workspace/drive/api/guides/manage-sharing).

Expired access tokens refresh automatically. Provider-confirmed `invalid_grant`
marks the connection as requiring reconnection and disables sync. Temporary
network, rate-limit and server failures leave the connection intact for retry;
client-configuration errors also do not revoke the stored user connection.
Rotated refresh tokens are encrypted when returned. Raw provider error bodies
are not reflected to users. See Google's
[OAuth guide](https://developers.google.com/identity/protocols/oauth2/web-server).

## Current spreadsheet editing contract

Each module gets its own workbook. `Records` and `Instructions` are system-owned;
staff copy a record and its revision into `Changes` and edit business fields
there. Sync never clears `Changes`. Captured revisions and review payloads are
encrypted in Laravel. Independent low-impact changes can merge automatically;
conflicts and sensitive decisions are retained for review. High-impact decisions
must still use the existing live module workflow.

This does not capture every intermediate edit between scheduled scans. Durable
intake history and complete outage/recovery tests remain release requirements.

## Pending student intake decision

Google Forms upload questions cannot be used in a Shared Drive and cannot be
created through the Forms API. Permission to use a dedicated school-owned
account's My Drive for temporary intake is still pending. Preparing these
environment files and the scheduler does not enable that storage exception.

Sources:

- https://developers.google.com/identity/protocols/oauth2/web-server
- https://support.google.com/docs/answer/7322334
- https://developers.google.com/workspace/forms/api/reference/rest/v1/forms
- https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.scheduletrigger/
- https://docs.n8n.io/hosting/configuration/configuration-examples/modules-in-code-node/
