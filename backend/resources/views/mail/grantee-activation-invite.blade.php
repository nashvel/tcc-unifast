Hello {{ $user->name }},

{{ $intro ?? 'Your TCC UniFAST TES account was created from the CHED masterlist.' }}

Activation link: {{ $activationUrl }}

Temporary password: {{ $temporaryPassword }}

Use the link once, replace your password, then complete your KYC profile.
Do not share this temporary password with anyone.
