URGENT: TCC UniFAST TES ACTIVATION & SUBMISSION DEADLINE REMINDER
{{ $daysRemaining }}
===============================================================

Dear {{ $grantee->full_name ?? $user->name }},

Our records indicate that you are a qualified Tertiary Education Subsidy (TES) grantee for:
- Batch: {{ $batch->name }} ({{ $batch->academic_year }} {{ $batch->semester }})
- Student ID: {{ $grantee->student_id }}
- Submission Deadline: {{ $deadlineFormatted }}

Your student portal account has not been activated yet. The submission deadline is approaching.

ACTIVATE YOUR ACCOUNT NOW:
{{ $activationUrl }}

STEPS TO COMPLETE:
1. Open the activation link above.
2. Scan your official school ID and complete face verification.
3. Choose your password and submit any required documents before the window closes.

If the deadline passes, late submissions cannot be accommodated through the portal. For urgent assistance, visit the TCC UniFAST/TES Office immediately.

--
Tagoloan Community College - UniFAST TES Office
Official Portal: {{ config('app.frontend_url') }}
