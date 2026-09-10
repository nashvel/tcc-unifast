# SIS OIDC operator guide

UniFAST requires a standards-compliant OIDC issuer over HTTPS. Register this
exact callback URL from Integration Settings:

`https://<unifast-host>/api/auth/sis/callback`

The provider discovery document must be at
`<issuer>/.well-known/openid-configuration`. It must advertise Authorization
Code Flow, PKCE `S256`, `openid profile email`, a `jwks_uri`, and RS256 signed
ID tokens. The ID token must contain exact `iss`, stable `sub`, `aud` (and
`azp` for a multi-audience token), `exp`, `iat`, and `nonce` claims. It must
also contain a verified `email`, `email_verified: true`, and the agreed scalar
student-ID claim (default `student_id`; a namespaced claim is supported).

Create a confidential client and provide its client ID and secret only through
the UniFAST administrator UI. Do not send client credentials, authorization
codes, access tokens, refresh tokens, or ID tokens through email or support
tickets.

Use an SIS test tenant and fictional existing students for the pilot. The
administrator first saves a draft, selects **Validate configuration**, selects
pilot students, and sends their expiring private links. A successful pilot and
zero pending identity reviews are required before public activation.

Safe user-facing results are `sso_unavailable`, `sso_not_eligible`,
`sso_identity_review`, and `sso_account_blocked`. Investigate provider logs and
the UniFAST audit trail using timestamps; neither surface includes tokens or
client secrets.
