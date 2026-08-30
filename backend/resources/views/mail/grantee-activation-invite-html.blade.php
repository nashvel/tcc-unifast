<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activate your TCC UniFAST TES student portal account</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f9fafb;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #7a1e2b;
            padding: 20px 32px;
            border-bottom: 4px solid #611620;
        }
        .header-content {
            display: table;
            width: 100%;
        }
        .header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 60px;
        }
        .header-logo img {
            height: 48px;
            width: auto;
            display: block;
        }
        .header-text {
            display: table-cell;
            vertical-align: middle;
            padding-left: 16px;
            color: #ffffff;
            text-align: left;
        }
        .header-title {
            font-size: 22px;
            font-weight: 600;
            line-height: 1.2;
            letter-spacing: 0.5px;
        }
        .header-subtitle {
            font-size: 13px;
            color: #e5e7eb;
            margin-top: 2px;
            font-weight: 400;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 40px 32px;
        }
        .greeting {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 24px;
        }
        .message {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 32px;
        }
        .btn-container {
            text-align: center;
            margin-bottom: 32px;
        }
        .btn {
            display: inline-block;
            background-color: #7a1e2b;
            color: #ffffff;
            font-weight: 600;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 6px;
            font-size: 16px;
        }
        .credentials {
            background-color: #f3f4f6;
            border-left: 4px solid #7a1e2b;
            padding: 16px 20px;
            margin-bottom: 32px;
            border-radius: 0 6px 6px 0;
        }
        .credentials p {
            margin: 0;
            font-size: 15px;
        }
        .credentials strong {
            color: #111827;
        }
        .password-box {
            display: inline-block;
            background: #e5e7eb;
            padding: 6px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 16px;
            font-weight: bold;
            color: #7a1e2b;
            letter-spacing: 1px;
            margin-top: 8px;
        }
        .footer {
            background-color: #f9fafb;
            padding: 24px 32px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-logo">
                    @if(config('app.logo_url'))
                        <img src="{{ config('app.logo_url') }}" alt="Logo">
                    @else
                        <img src="{{ $message->embed(public_path('system-logo.png')) }}" alt="Logo">
                    @endif
                </div>
                <div class="header-text">
                    <div class="header-title">UniFAST TES</div>
                    <div class="header-subtitle">Grantee Management</div>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="greeting">Hello {{ $user->name }},</div>
            
            <div class="message">
                {{ $intro ?? 'Your TCC UniFAST TES account was created from the CHED masterlist.' }}
            </div>
            
            <div class="btn-container">
                <a href="{{ $activationUrl }}" class="btn">Start verification</a>
            </div>

            <div class="message" style="margin-bottom: 0; font-size: 14px;">
                You will complete your KYC profile, scan your school ID, and finish a short
                face check. <strong>You choose your password at the end</strong>, once your
                identity has been verified.<br><br>
                Have your physical school ID ready before you begin. If the link expires,
                you can request a new one from the sign-in page.<br><br>
                <strong>Security notice:</strong> do not share this link. It is the only
                proof of your invitation.
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} TCC UniFAST TES. All rights reserved.</p>
            <p>If you did not expect this email, you can safely ignore it.</p>
        </div>
    </div>
</body>
</html>
