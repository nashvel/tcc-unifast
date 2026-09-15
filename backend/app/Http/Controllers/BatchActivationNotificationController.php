<?php

namespace App\Http\Controllers;

use App\Mail\GranteeActivationDeadlineReminderMail;
use App\Mail\GranteeActivationInviteMail;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Grantee;
use App\Services\ActivationTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BatchActivationNotificationController extends Controller
{
    public function __invoke(Request $request, string $batch, ActivationTokenIssuer $issuer): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        $students = Grantee::query()
            ->with('user')
            ->where('batch_id', $batch)
            ->whereHas('user', fn ($query) => $query->where('account_status', 'unverified'))
            ->get();

        $intro = $validated['message'] ?? 'Your student portal account has been created from the TES masterlist.';
        $sent = 0;
        $failed = [];

        foreach ($students as $student) {
            if (! $student->user) {
                continue;
            }
            // No temporary password: an unusable hash keeps the column NOT NULL
            // without handing out a credential before identity verification.
            $student->user->forceFill([
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => null,
            ])->save();

            $link = $issuer->issueLinkFor($student->user);

            try {
                Mail::to($student->email, $student->full_name)->send(new GranteeActivationInviteMail(
                    $student->user,
                    $link['url'],
                    $intro,
                    $validated['subject'] ?? null,
                ));
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed[] = ['email' => $student->email, 'message' => $exception->getMessage()];
            }
        }

        return response()->json([
            'batch' => $batch,
            'mailer' => config('mail.default'),
            'sent' => $sent,
            'failed' => $failed,
        ], $failed === [] ? 200 : 207);
    }

    public function sendDeadlineReminders(Request $request, string $batch, ActivationTokenIssuer $issuer): JsonResponse
    {
        $batchModel = Batch::findOrFail($batch);

        if (! $batchModel->submission_deadline) {
            return response()->json([
                'message' => 'This batch does not have a submission deadline configured.',
            ], 422);
        }

        $force = filter_var($request->input('force', false), FILTER_VALIDATE_BOOLEAN);

        $query = Grantee::query()
            ->with('user')
            ->where('batch_id', $batchModel->id)
            ->whereHas('user', fn ($q) => $q->where('account_status', 'unverified'));

        if (! $force) {
            $query->where(function ($q): void {
                $q->whereNull('last_deadline_reminder_sent_at')
                    ->orWhere('last_deadline_reminder_sent_at', '<=', now()->subHours(24));
            });
        }

        $students = $query->get();
        $sent = 0;
        $failed = [];

        foreach ($students as $student) {
            if (! $student->user) {
                continue;
            }

            $link = $issuer->issueLinkFor($student->user);

            try {
                Mail::to($student->email, $student->full_name)->send(new GranteeActivationDeadlineReminderMail(
                    user: $student->user,
                    grantee: $student,
                    batch: $batchModel,
                    activationUrl: $link['url'],
                ));

                $student->forceFill([
                    'last_deadline_reminder_sent_at' => now(),
                ])->save();

                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed[] = ['email' => $student->email, 'message' => $exception->getMessage()];
            }
        }

        AuditLog::create([
            'actor' => $request->user()?->name ?? 'Staff',
            'role' => ucfirst($request->user()?->role ?? 'staff'),
            'action' => 'batch_activation_deadline_reminders_triggered',
            'module' => 'Batches',
            'target' => "Batch #{$batchModel->id} ({$batchModel->name})",
            'context' => ['sent' => $sent, 'failed' => count($failed), 'force' => $force],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'batch' => $batchModel->id,
            'batch_name' => $batchModel->name,
            'deadline' => $batchModel->submission_deadline?->toIso8601String(),
            'eligible_count' => $students->count(),
            'sent' => $sent,
            'failed' => $failed,
        ], $failed === [] ? 200 : 207);
    }
}
