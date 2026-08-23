<?php

namespace Tests\Feature;

use App\Mail\GranteeActivationInviteMail;
use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

    public function test_activation_with_token_only_moves_student_to_kyc(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-1',
            'account_status' => 'unverified',
            'password' => Hash::make(Str::random(32)),
        ]);
        $plainToken = 'activation-token';
        ActivationToken::create([
            'user_id' => $student->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson("/api/activation/{$plainToken}", [
            'password' => 'new-password',
            'password_confirmation' => 'mismatch',
        ])->assertUnprocessable();

        $this->postJson("/api/activation/{$plainToken}", [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk()
            ->assertJsonPath('user.account_status', 'pending_kyc')
            ->assertJsonStructure(['user' => ['id', 'email', 'account_status']])
            ->assertJsonMissingPath('token')
            ->assertPlainCookie(config('services.auth.access_cookie'))
            ->assertPlainCookie(config('services.auth.refresh_cookie'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $student->id,
            'tokenable_type' => User::class,
        ]);
        $this->assertDatabaseCount('refresh_tokens', 1);
    }

    public function test_kyc_cross_checks_typed_identity_against_masterlist(): void
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

        $show = $this->actingAs($student)->getJson('/api/student/kyc');
        $show->assertOk()
            ->assertJsonPath('data.hint.student_id_last4', 'STU1')
            ->assertJsonMissingPath('data.locked.full_name')
            ->assertJsonMissingPath('data.reference.full_name');

        // Spoofed identity must be rejected — user must type matching values.
        // Year level is optional and is not part of the masterlist cross-check.
        $this->actingAs($student)->postJson('/api/student/kyc', [
            'first_name' => 'Attacker',
            'last_name' => 'Name',
            'student_id' => 'OTHER-99',
            'program' => 'BSIT',
            'year_level' => '4',
            'contact' => '+639171234567',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'student_id'])
            ->assertJsonMissingValidationErrors(['year_level']);

        $this->actingAs($student)->postJson('/api/student/kyc', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'student_id' => 'STU-1',
            'program' => 'BSIT',
            'year_level' => '1',
            'contact' => '+639171234567',
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'pending_identity')
            ->assertJsonPath('data.next_step', 'id_scan');
        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'pending_identity']);
        $this->assertDatabaseHas('kyc_profiles', [
            'user_id' => $student->id,
            'full_name' => 'Maria Santos',
            'student_id' => 'STU-1',
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'verified',
        ]);

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

    public function test_kyc_accepts_mismatched_or_missing_year_level(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-77',
            'account_status' => 'pending_kyc',
        ]);
        $batch = $this->batchWithDeadline();
        Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-77',
            'student_number' => '2026-0077',
            'full_name' => 'Ana Reyes',
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
            'student_id' => 'STU-77',
            'full_name' => 'Ana Reyes',
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'valid',
        ]);

        // Mismatched year must not block KYC when name / student ID / program match.
        $this->actingAs($student)->postJson('/api/student/kyc', [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'student_id' => 'STU-77',
            'program' => 'BSIT',
            'year_level' => '4',
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'pending_identity');

        $this->assertDatabaseHas('kyc_profiles', [
            'user_id' => $student->id,
            'full_name' => 'Ana Reyes',
            'student_id' => 'STU-77',
            'program' => 'BSIT',
            'year_level' => '4',
            'status' => 'verified',
        ]);

        $other = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-78',
            'account_status' => 'pending_kyc',
        ]);
        Grantee::create([
            'user_id' => $other->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-78',
            'student_number' => '2026-0078',
            'full_name' => 'Ben Cruz',
            'email' => $other->email,
            'program' => 'BSIT',
            'year_level' => '2',
            'status' => 'unverified',
        ]);
        MasterlistRow::create([
            'masterlist_import_id' => $import->id,
            'row_number' => 3,
            'student_id' => 'STU-78',
            'full_name' => 'Ben Cruz',
            'email' => $other->email,
            'program' => 'BSIT',
            'year_level' => null,
            'status' => 'valid',
        ]);

        // Omitting year_level must also succeed.
        $this->actingAs($other)->postJson('/api/student/kyc', [
            'first_name' => 'Ben',
            'last_name' => 'Cruz',
            'student_id' => 'STU-78',
            'program' => 'BSIT',
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'pending_identity');

        $this->assertDatabaseHas('kyc_profiles', [
            'user_id' => $other->id,
            'student_id' => 'STU-78',
            'year_level' => null,
            'status' => 'verified',
        ]);
    }

    public function test_kyc_accepts_case_insensitive_name_and_student_id(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-88',
            'account_status' => 'pending_kyc',
        ]);
        $batch = $this->batchWithDeadline();
        Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-88',
            'student_number' => '2026-0088',
            'full_name' => 'BRANDON NAGANGGA',
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '2',
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
            'student_id' => 'STU-88',
            'full_name' => 'BRANDON NAGANGGA',
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '2',
            'status' => 'valid',
        ]);

        $this->actingAs($student)->postJson('/api/student/kyc', [
            'first_name' => '  Brandon  ',
            'last_name' => 'Nagangga',
            'student_id' => 'stu-88',
            'program' => 'BSIT',
            'year_level' => '2',
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'pending_identity');

        $this->assertDatabaseHas('kyc_profiles', [
            'user_id' => $student->id,
            'full_name' => 'BRANDON NAGANGGA',
            'student_id' => 'STU-88',
            'status' => 'verified',
        ]);
    }

    public function test_login_resumes_mid_onboarding_without_reactivation(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-9',
            'email' => 'resume@example.test',
            'password' => Hash::make('password-after-activate'),
            'account_status' => 'pending_identity',
        ]);
        $batch = $this->batchWithDeadline();
        $grantee = Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-9',
            'full_name' => 'Resume Student',
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '2',
            'status' => 'kyc_verified',
        ]);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_liveness',
        ]);

        $this->getJson('/api/auth/captcha'); // warm session for captcha bypass in tests if configured

        config(['services.auth.dev_bypass_captcha' => true]);

        $this->postJson('/api/auth/login', [
            'email' => 'resume@example.test',
            'password' => 'password-after-activate',
            'captcha' => 'BYPASS',
        ])->assertOk()
            ->assertJsonPath('user.account_status', 'pending_identity')
            ->assertJsonPath('user.onboarding_next_step', 'liveness')
            ->assertJsonPath('user.onboarding_path', '/student/onboarding/liveness');
    }
}
