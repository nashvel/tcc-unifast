# ADR: SIS OpenID Connect SSO

## Status

Accepted for implementation; disabled by default.

## Decision

UniFAST is an OpenID Connect relying party for one configured SIS at a time.
It uses Discovery, Authorization Code Flow, PKCE `S256`, state, nonce, and
asymmetric signed ID tokens. UniFAST remains authoritative for roles,
permissions, student status, onboarding, and eligibility.

SIS sign-in only connects an existing student after verified email and student
ID matching. It never provisions students or staff. A durable identity is the
exact issuer/subject tuple. Conflicts block access; name or email differences
enter an administrator review that restricts portal access until decided.

Connections start in draft. Administrators validate Discovery and keys, test
selected students with private one-time invitations, then explicitly enable all
students after successful pilot evidence and no pending reviews. Changing
issuer, client, secret, or student-ID claim revokes linked sessions, unlinks
old identities, and returns to draft for a fresh pilot.

## Consequences

Password and Google sign-in remain available. Local logout only clears UniFAST
sessions. Provider tokens, codes, and secrets are never returned to the browser
or stored after the transaction. Supporting multiple providers, staff SSO,
SCIM, global provider logout, and JIT provisioning require a new decision.
