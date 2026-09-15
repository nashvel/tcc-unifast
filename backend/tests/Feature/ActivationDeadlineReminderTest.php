<?php

namespace Tests\Feature;

use App\Mail\GranteeActivationDeadlineReminderMail;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ActivationDeadlineReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_email_to_unactivated_grantees_with_approaching_deadline(): void
    {
        Mail::fake();

        $batch = Batch::create([
            'name' => 'TES-2026-Batch-1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'submission_deadline' => now()->addDays(2),
            'is_active' => true,
        ]);

        $unverifiedUser = User::factory()->create([
            'account_status' => 'unverified',
            'email' => 'unverified@example.com',
        ]);

        $grantee = Grantee::create([
            'user_id' => $unverifiedUser->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-1001',
            'full_name' => 'Maria Santos',
            'email' => 'unverified@example.com',
            'program' => 'BSIT',
            'status' => 'unverified',
        ]);

        $activeUser = User::factory()->create([
            'account_status' => 'active',
            'email' => 'active@example.com',
        ]);

        Grantee::create([
            'user_id' => $activeUser->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-1002',
            'full_name' => 'Juan Dela Cruz',
            'email' => 'active@example.com',
            'program' => 'BSCS',
            'status' => 'active',
        ]);

        $this->artisan('unifast:send-activation-deadline-reminders', ['--days' => 3])
            ->assertSuccessful();

        Mail::assertSent(GranteeActivationDeadlineReminderMail::class, function ($mail) use ($grantee) {
            return $mail->hasTo($grantee->email);
        });

        Mail::assertNotSent(GranteeActivationDeadlineReminderMail::class, function ($mail) use ($activeUser) {
            return $mail->hasTo($activeUser->email);
        });

        $this->assertNotNull($grantee->fresh()->last_deadline_reminder_sent_at);
    }

    public function test_cooldown_prevents_duplicate_reminders_within_24_hours(): void
    {
        Mail::fake();

        $batch = Batch::create([
            'name' => 'TES-2026-Batch-1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'submission_deadline' => now()->addDays(1),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'account_status' => 'unverified',
            'email' => 'cooldown@example.com',
        ]);

        $grantee = Grantee::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-1003',
            'full_name' => 'Pedro Penduko',
            'email' => 'cooldown@example.com',
            'program' => 'BSIT',
            'status' => 'unverified',
            'last_deadline_reminder_sent_at' => now()->subHours(2), // Sent 2 hours ago
        ]);

        $this->artisan('unifast:send-activation-deadline-reminders', ['--days' => 3])
            ->assertSuccessful();

        Mail::assertNotSent(GranteeActivationDeadlineReminderMail::class);

        // With --force option, it should send
        $this->artisan('unifast:send-activation-deadline-reminders', ['--days' => 3, '--force' => true])
            ->assertSuccessful();

        Mail::assertSent(GranteeActivationDeadlineReminderMail::class);
    }

    public function test_batch_deadline_reminders_http_endpoint(): void
    {
        Mail::fake();

        $staff = User::factory()->create([
            'role' => 'staff',
            'account_status' => 'active',
        ]);

        $batch = Batch::create([
            'name' => 'TES-2026-Batch-API',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'submission_deadline' => now()->addDays(2),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'account_status' => 'unverified',
            'email' => 'student_api@example.com',
        ]);

        $grantee = Grantee::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-1004',
            'full_name' => 'Ana Reyes',
            'email' => 'student_api@example.com',
            'program' => 'BSIS',
            'status' => 'unverified',
        ]);

        $response = $this->actingAs($staff)
            ->postJson("/api/batches/{$batch->id}/deadline-reminders");

        $response->assertOk()
            ->assertJsonPath('sent', 1)
            ->assertJsonPath('batch_name', $batch->name);

        Mail::assertSent(GranteeActivationDeadlineReminderMail::class, function ($mail) use ($grantee) {
            return $mail->hasTo($grantee->email);
        });
    }
}
