<?php

namespace App\Console\Commands;

use App\Mail\GranteeActivationDeadlineReminderMail;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Grantee;
use App\Services\ActivationTokenIssuer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendActivationDeadlineRemindersCommand extends Command
{
    protected $signature = 'unifast:send-activation-deadline-reminders
                            {--days=3 : Look ahead window in days for approaching deadlines}
                            {--batch= : Specific batch ID to process}
                            {--force : Ignore 24-hour reminder cooldown}
                            {--dry-run : Simulate and preview recipients without sending emails}';

    protected $description = 'Send urgent email reminders to unactivated grantees whose batch submission deadline is approaching';

    public function handle(ActivationTokenIssuer $issuer): int
    {
        $days = max(1, (int) $this->option('days'));
        $batchId = $this->option('batch');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Scanning for batches with submission deadlines within {$days} days...");

        $batchQuery = Batch::query()
            ->whereNotNull('submission_deadline')
            ->where('submission_deadline', '>', now());

        if ($batchId) {
            $batchQuery->where('id', $batchId);
        } else {
            $batchQuery->where('is_active', true)
                ->where('submission_deadline', '<=', now()->addDays($days));
        }

        $batches = $batchQuery->get();

        if ($batches->isEmpty()) {
            $this->info('No batches found matching the approaching deadline criteria.');

            return self::SUCCESS;
        }

        $totalSent = 0;
        $totalFailed = 0;

        foreach ($batches as $batch) {
            $this->line("Processing Batch: <comment>{$batch->name}</comment> (Deadline: {$batch->submission_deadline?->timezone('Asia/Manila')->format('Y-m-d H:i')})");

            $granteeQuery = Grantee::query()
                ->with(['user', 'batch'])
                ->where('batch_id', $batch->id)
                ->whereHas('user', fn ($q) => $q->where('account_status', 'unverified'));

            if (! $force) {
                $granteeQuery->where(function ($q): void {
                    $q->whereNull('last_deadline_reminder_sent_at')
                        ->orWhere('last_deadline_reminder_sent_at', '<=', now()->subHours(24));
                });
            }

            $grantees = $granteeQuery->get();

            if ($grantees->isEmpty()) {
                $this->line('  No unactivated grantees eligible for reminder in this batch.');

                continue;
            }

            $this->line("  Found {$grantees->count()} unactivated grantee(s).");

            if ($dryRun) {
                $rows = $grantees->map(fn (Grantee $g) => [
                    'ID' => $g->student_id,
                    'Name' => $g->full_name,
                    'Email' => $g->email,
                    'Last Sent' => $g->last_deadline_reminder_sent_at?->diffForHumans() ?? 'Never',
                ])->toArray();

                $this->table(['ID', 'Name', 'Email', 'Last Sent'], $rows);

                continue;
            }

            foreach ($grantees as $grantee) {
                $user = $grantee->user;
                if (! $user) {
                    continue;
                }

                $link = $issuer->issueLinkFor($user);

                try {
                    Mail::to($grantee->email, $grantee->full_name)
                        ->send(new GranteeActivationDeadlineReminderMail(
                            user: $user,
                            grantee: $grantee,
                            batch: $batch,
                            activationUrl: $link['url'],
                        ));

                    $grantee->forceFill([
                        'last_deadline_reminder_sent_at' => now(),
                    ])->save();

                    $totalSent++;
                    $this->line("  [SENT] {$grantee->email} ({$grantee->student_id})");
                } catch (\Throwable $e) {
                    report($e);
                    $totalFailed++;
                    $this->error("  [FAILED] {$grantee->email}: {$e->getMessage()}");
                }
            }
        }

        if (! $dryRun && $totalSent > 0) {
            AuditLog::create([
                'actor' => 'System / Scheduled Command',
                'role' => 'System',
                'action' => 'activation_deadline_reminders_sent',
                'module' => 'Batches',
                'target' => "{$totalSent} reminders",
                'context' => [
                    'days_window' => $days,
                    'sent' => $totalSent,
                    'failed' => $totalFailed,
                ],
                'ip_address' => '127.0.0.1',
            ]);
        }

        $this->info("Completed. Total sent: {$totalSent}, Failed: {$totalFailed}".($dryRun ? ' (Dry Run)' : ''));

        return self::SUCCESS;
    }
}
