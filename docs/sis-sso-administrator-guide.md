# SIS SSO administrator guide

Open **Integration Settings → SIS single sign-on** as an active administrator
or developer. Enter the display name, HTTPS issuer, client ID, write-only
secret, and student-ID claim. Copy the callback URL to the SIS operator, then
validate the configuration. Validation failing leaves sign-in unavailable.

Enable **Selected pilot students**, search for an existing student, select them,
and create a private invitation. The link is one-time and expires in 24 hours;
give it only to the selected student. A forwarded link cannot connect a second
student. Inspect pilot successes, failures, and identity reviews before moving
to **All existing students**. That action requires an explicit confirmation.

Review records compare local and SIS student ID, name, and email. Approve only
name/email discrepancies after verifying the person; approval accepts the SIS
mapping and never overwrites the UniFAST master record. Reject collisions,
student-ID changes, or role/status attempts; it disables that external identity.

To pause new logins, select **Disabled**. Choose whether already-issued local
SIS sessions are retained or revoked. Replacing provider configuration always
revokes linked sessions, unlinks prior identities, and returns the connection
to draft; validate and pilot the new provider before enabling it.
