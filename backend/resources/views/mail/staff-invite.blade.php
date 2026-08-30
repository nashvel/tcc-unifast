Hello {{ $user->name }},

@if ($invitedBy)
{{ $invitedBy }} has invited you to the TCC UniFAST TES admin portal as {{ $user->role }}.
@else
You have been invited to the TCC UniFAST TES admin portal as {{ $user->role }}.
@endif

Set your password using the link below:

{{ $activationUrl }}

The link expires soon and can only be used once. If it expires, ask an administrator
to resend your invite.

Do not share this link with anyone.
