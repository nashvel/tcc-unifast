<?php

namespace Tests\Feature;

use App\Models\ActivationToken;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_masterlist_preview_flags_invalid_and_duplicate_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $file = UploadedFile::fake()->createWithContent('ched.csv', implode("\n", [
            'student_id,full_name,email,program,year_level,student_number',
            'STU-1,Maria Santos,maria@example.test,BSIT,1,2026-0001',
            'STU-1,Juan Cruz,juan@example.test,BSBA,1,2026-0002',
            'STU-3,Nicole Reyes,,BSED,2,2026-0003',
        ]));

        $response = $this->actingAs($admin)->postJson('/api/masterlist/imports/preview', [
            'file' => $file,
            'batch_name' => 'TES Batch 1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total_rows', 3)
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.invalid_rows', 2);
    }

    public function test_confirm_import_creates_unverified_accounts_and_activation_tokens(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $file = UploadedFile::fake()->createWithContent('ched.csv', implode("\n", [
            'student_id,full_name,email,program,year_level,student_number',
            'STU-1,Maria Santos,maria@example.test,BSIT,1,2026-0001',
        ]));

        $preview = $this->actingAs($admin)->postJson('/api/masterlist/imports/preview', [
            'file' => $file,
            'batch_name' => 'TES Batch 1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
        ])->json('data');

        $this->actingAs($admin)
            ->postJson("/api/masterlist/imports/{$preview['id']}/confirm")
            ->assertOk()
            ->assertJsonPath('data.imported_rows', 1)
            ->assertJsonPath('mail.sent', 1);

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.test',
            'role' => 'student',
            'student_id' => 'STU-1',
            'account_status' => 'unverified',
        ]);
        $this->assertDatabaseHas('grantees', ['student_id' => 'STU-1', 'program' => 'BSIT']);
        $this->assertDatabaseCount('activation_tokens', 1);
    }

    public function test_activation_requires_temporary_password_and_moves_student_to_kyc(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-1',
            'account_status' => 'unverified',
            'password' => Hash::make('TCC-ABCD-EFGH'),
        ]);
        $plainToken = 'activation-token';
        ActivationToken::create([
            'user_id' => $student->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson("/api/activation/{$plainToken}", [
            'temporary_password' => 'wrong',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable();

        $this->postJson("/api/activation/{$plainToken}", [
            'temporary_password' => 'TCC-ABCD-EFGH',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk()
            ->assertJsonPath('user.account_status', 'pending_kyc');
    }

    public function test_kyc_match_activates_and_mismatch_blocks_with_specific_errors(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-1',
            'account_status' => 'pending_kyc',
        ]);
        Grantee::create([
            'user_id' => $student->id,
            'student_id' => 'STU-1',
            'student_number' => '2026-0001',
            'full_name' => 'Maria Santos',
            'email' => $student->email,
            'program' => 'BSIT',
            'status' => 'unverified',
        ]);

        $this->actingAs($student)->postJson('/api/student/kyc', [
            'full_name' => 'Maria Santos',
            'student_id' => 'STU-1',
            'program' => 'Wrong Program',
        ])->assertUnprocessable()
            ->assertJsonPath('data.mismatches.program', 'Submitted program does not match the CHED masterlist record.');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'blocked']);

        $this->actingAs($student)->postJson('/api/student/kyc', [
            'full_name' => 'Maria Santos',
            'student_id' => 'STU-1',
            'program' => 'BSIT',
            'contact' => '+639171234567',
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'active');
    }
}
