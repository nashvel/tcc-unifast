<?php

namespace Tests\Feature;

use App\Mail\GranteeActivationInviteMail;
use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function batchWithDeadline(): Batch
    {
        return Batch::create([
            'name' => 'TES Batch 1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'submission_deadline' => now()->addDays(14),
            'status' => 'draft',
            'window_status' => 'draft',
            'is_active' => false,
        ]);
    }

    public function test_masterlist_preview_flags_invalid_and_duplicate_rows(): void
    {
$admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $batch = $this->batchWithDeadline();
        $file = UploadedFile::fake()->createWithContent('ched.csv', implode("\n", [
            'student_id,full_name,email,program,year_level,student_number',
            'STU-1,Maria Santos,maria@example.test,BSIT,1,2026-0001',
            'STU-1,Juan Cruz,juan@example.test,BSBA,1,2026-0002',
            'STU-3,Nicole Reyes,,BSED,2,2026-0003',
            'STU-4,No Year,noyear@example.test,BSIT,,2026-0004',
        ]));

        $response = $this->actingAs($admin)->postJson('/api/masterlist/imports/preview', [
            'file' => $file,
            'batch_id' => $batch->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total_rows', 4)
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.invalid_rows', 3);
    }

    public function test_preview_requires_existing_batch_with_deadline(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $batch = Batch::create([
            'name' => 'No Deadline',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'status' => 'draft',
        ]);
        $file = UploadedFile::fake()->createWithContent('ched.csv', implode("\n", [
            'student_id,full_name,email,program,year_level',
            'STU-1,Maria Santos,maria@example.test,BSIT,1',
        ]));

        $this->actingAs($admin)->postJson('/api/masterlist/imports/preview', [
            'file' => $file,
            'batch_id' => $batch->id,
        ])->assertUnprocessable();
    }

    public function test_confirm_import_creates_unverified_accounts_and_activation_tokens(): void
    {
        Mail::fake();
$admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $batch = $this->batchWithDeadline();
        $file = UploadedFile::fake()->createWithContent('ched.csv', implode("\n", [
            'student_id,full_name,email,program,year_level,student_number',
            'STU-1,Maria Santos,maria@example.test,BSIT,1,2026-0001',
        ]));

        $preview = $this->actingAs($admin)->postJson('/api/masterlist/imports/preview', [
            'file' => $file,
            'batch_id' => $batch->id,
        ])->json('data');

        $this->actingAs($admin)
            ->postJson("/api/masterlist/imports/{$preview['id']}/confirm")
            ->assertOk()
            ->assertJsonPath('data.imported_rows', 1)
            ->assertJsonPath('mail.sent', 1);

        Mail::assertSent(GranteeActivationInviteMail::class);

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.test',
            'role' => 'student',
            'student_id' => 'STU-1',
            'account_status' => 'unverified',
        ]);
        $this->assertDatabaseHas('grantees', [
            'student_id' => 'STU-1',
            'program' => 'BSIT',
            'year_level' => '1',
            'batch_id' => $batch->id,
            'status' => 'unverified',
        ]);
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
        $batch = $this->batchWithDeadline();
        $grantee = Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-1',
            'student_number' => '2026-0001',
            'full_name' => 'Maria Santos',
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'unverified',
        ]);
        $import = MasterlistImport::create([
            'batch_id' => $batch->id,
            'uploaded_by' => $student->id,
            'original_name' => 'ched.csv',
            'stored_path' => 'masterlist-imports/ched.csv',
            'status' => 'imported',
            'total_rows' => 1,
            'valid_rows' => 1,
            'imported_rows' => 1,
        ]);
        MasterlistRow::create([
            'masterlist_import_id' => $import->id,
            'row_number' => 2,
            'student_id' => 'STU-1',
            'full_name' => 'Maria Santos',
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'valid',
        ]);

        $this->actingAs($student)->postJson('/api/student/kyc', [
            'full_name' => 'Maria Santos',
            'student_id' => 'STU-1',
            'program' => 'Wrong Program',
            'year_level' => '1',
        ])->assertUnprocessable()
            ->assertJsonPath('data.mismatches.program', 'Submitted program does not match the CHED masterlist record.');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'blocked']);

        $this->actingAs($student)->postJson('/api/student/kyc', [
            'full_name' => 'Maria Santos',
            'student_id' => 'STU-1',
            'program' => 'BSIT',
            'year_level' => '2',
        ])->assertUnprocessable()
            ->assertJsonPath('data.mismatches.year_level', 'Submitted year level does not match the CHED masterlist record.');

        $this->actingAs($student)->postJson('/api/student/kyc', [
            'full_name' => 'Maria Santos',
            'student_id' => 'STU-1',
            'program' => 'BSIT',
            'year_level' => '1',
            'contact' => '+639171234567',
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'pending_identity');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'pending_identity']);

        $this->actingAs($student->fresh())
            ->getJson('/api/student/requirement-vault')
            ->assertUnprocessable();

        $blocked = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-2',
            'account_status' => 'pending_kyc',
        ]);
        Grantee::create([
            'user_id' => $blocked->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-2',
            'full_name' => 'Other',
            'email' => $blocked->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'unverified',
        ]);

        $this->actingAs($blocked)
            ->getJson('/api/student/requirement-vault')
            ->assertUnprocessable();

        $this->assertNotNull($grantee->fresh());
    }
}
