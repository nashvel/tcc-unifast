<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Urgent: TES Activation & Submission Deadline Approaching</title>
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
            color: #fca5a5;
            margin-top: 2px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .content {
            padding: 36px 32px;
        }
        .alert-banner {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            border-left: 5px solid #dc2626;
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .alert-title {
            color: #991b1b;
            font-weight: 700;
            font-size: 16px;
            display: flex;
            align-items: center;
            margin-bottom: 6px;
        }
        .alert-body {
            color: #7f1d1d;
            font-size: 14px;
            margin: 0;
        }
        .deadline-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .deadline-card table {
            width: 100%;
            border-collapse: collapse;
        }
        .deadline-card td {
            padding: 6px 0;
            font-size: 14px;
        }
        .deadline-label {
            color: #64748b;
            width: 35%;
        }
        .deadline-value {
            color: #0f172a;
            font-weight: 600;
        }
        .deadline-highlight {
            color: #dc2626;
            font-weight: 700;
            font-size: 15px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 16px;
        }
        .message {
            font-size: 15px;
            color: #4b5563;
            margin-bottom: 24px;
        }
        .btn-container {
            text-align: center;
            margin: 28px 0;
        }
        .btn {
            display: inline-block;
            background-color: #dc2626;
            color: #ffffff !important;
            font-weight: 600;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 6px;
            font-size: 16px;
            box-shadow: 0 2px 4px rgba(220, 38, 38, 0.2);
        }
        .steps {
            background-color: #f3f4f6;
            border-radius: 6px;
            padding: 18px 24px;
            margin-bottom: 24px;
        }
        .steps h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #1f2937;
        }
        .steps ol {
            margin: 0;
            padding-left: 20px;
            color: #4b5563;
            font-size: 13.5px;
        }
        .steps li {
            margin-bottom: 6px;
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
                    <div class="header-title">UniFAST TES Portal</div>
                    <div class="header-subtitle">Urgent Deadline Advisory</div>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="alert-banner">
                <div class="alert-title">⚠️ Action Required: {{ $daysRemaining }}</div>
                <p class="alert-body">
                    Your student portal account has not been activated yet, and the batch submission deadline is approaching fast.
                </p>
            </div>

            <div class="greeting">Dear {{ $grantee->full_name ?? $user->name }},</div>
            
            <div class="message">
                Our records show that you were qualified as a Tertiary Education Subsidy (TES) grantee for 
                <strong>{{ $batch->name }}</strong> ({{ $batch->academic_year }} {{ $batch->semester }}), but you have not yet completed your account activation.
            </div>

            <div class="deadline-card">
                <table>
                    <tr>
                        <td class="deadline-label">Student ID:</td>
                        <td class="deadline-value">{{ $grantee->student_id }}</td>
                    </tr>
                    <tr>
                        <td class="deadline-label">Academic Batch:</td>
                        <td class="deadline-value">{{ $batch->name }}</td>
                    </tr>
                    <tr>
                        <td class="deadline-label">Submission Deadline:</td>
                        <td class="deadline-value deadline-highlight">{{ $deadlineFormatted }}</td>
                    </tr>
                </table>
            </div>

            <div class="btn-container">
                <a href="{{ $activationUrl }}" class="btn">Activate Account & Complete Submission</a>
            </div>

            <div class="steps">
                <h4>What you need to do immediately:</h4>
                <ol>
                    <li>Click the <strong>Activate Account</strong> button above.</li>
                    <li>Scan your official physical School ID and complete the quick face verification.</li>
                    <li>Set your account password and review your required TES documents before the deadline.</li>
                </ol>
            </div>

            <div class="message" style="font-size: 13.5px; color: #6b7280; margin-bottom: 0;">
                <strong>Notice:</strong> Once the deadline passes, the submission window will close, which may delay or forfeit your grant validation for this semester. If you need assistance, please visit the TCC UniFAST/TES Office immediately.
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Tagoloan Community College - UniFAST TES Office. All rights reserved.</p>
            <p>Official Portal: {{ config('app.frontend_url') }}</p>
        </div>
    </div>
</body>
</html>
