Hello {{ $user->name }},

We could not confirm that the selfie submitted for your account matches the photo
on the school ID that was scanned.
@if ($reason)

Note from the scholarship office: {{ $reason }}
@endif

Your account has NOT been closed. You can retry verification using the link below.
No password has been set on your account, so nobody can sign in as you.

{{ $activationUrl }}

You will need your physical school ID. If someone else opened your earlier
activation link, this new link replaces it — the previous one no longer works.

If you did not attempt to activate an account, contact the scholarship office
immediately.
