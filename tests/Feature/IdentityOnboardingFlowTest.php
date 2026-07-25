<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdentityOnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_kyc_match_moves_to_pending_identity_not_active(): void
    {
        [$student, $grantee] = $this->studentWithMasterlist();

        $this->actingAs($student)->postJson('/api/student/kyc', [
            'full_name' => 'Maria Santos',
            'student_id' => 'STU-1',
            'program' => 'BSIT',
            'year_level' => '1',
            'contact' => '+639171234567',
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'pending_identity')
            ->assertJsonPath('data.next_step', 'id_scan');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'pending_identity']);
        $this->assertDatabaseHas('grantee_identity_profiles', [
            'grantee_id' => $grantee->id,
            'status' => 'pending_id_scan',
        ]);

        $this->actingAs($student->fresh())
            ->getJson('/api/student/requirement-vault')
            ->assertUnprocessable();
    }

    public function test_id_scan_requires_tcc_qr_and_ocr_match_then_liveness_activates(): void
    {
        Storage::fake('public');
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => "Maria Santos\nSTU-1\nBSIT"],
            ], 200),
        ]);

        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();
        $grantee->update(['status' => 'kyc_verified']);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_id_scan',
        ]);

        $this->actingAs($student)->post('/api/student/identity-onboarding/id-scan', [
            'id_frame' => UploadedFile::fake()->image('id.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'qr_payload' => 'https://evil.example.com/id/1',
            'face_quality_score' => 0.9,
        ])->assertUnprocessable();

        $this->actingAs($student)->post('/api/student/identity-onboarding/id-scan', [
            'id_frame' => UploadedFile::fake()->image('id.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'qr_payload' => 'https://registrar.tcc.edu.ph/verify/STU-1',
            'face_quality_score' => 0.91,
            'authenticity_skipped' => true,
        ])->assertOk()
            ->assertJsonPath('data.next_step', 'liveness');

        $this->assertDatabaseHas('grantee_identity_profiles', [
            'grantee_id' => $grantee->id,
            'status' => 'pending_liveness',
        ]);

        $this->actingAs($student)->post('/api/student/identity-onboarding/liveness', [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'challenge_sequence' => ['blink', 'turn_left', 'turn_right'],
            'distance' => 0.21,
            'liveness_confirmed' => true,
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.next_step', 'done');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'active']);
        $this->assertDatabaseHas('grantee_identity_profiles', [
            'grantee_id' => $grantee->id,
            'status' => 'completed',
        ]);
    }

    public function test_liveness_mismatch_blocks_account(): void
    {
        Storage::fake('public');
        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_liveness',
            'id_reference_face_path' => 'identity/1/id_reference_face.jpg',
        ]);
        Storage::disk('public')->put('identity/1/id_reference_face.jpg', 'fake');

        $this->actingAs($student)->post('/api/student/identity-onboarding/liveness', [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'challenge_sequence' => ['blink', 'turn_right', 'turn_left'],
            'distance' => 0.82,
            'liveness_confirmed' => true,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'blocked']);
    }

    /**
     * @return array{0: User, 1: Grantee}
     */
    private function studentWithMasterlist(): array
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-1',
            'account_status' => 'pending_kyc',
        ]);
        $batch = Batch::create([
            'name' => 'AY 2026-1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'status' => 'draft',
            'window_status' => 'draft',
            'is_active' => false,
            'submission_deadline' => now()->addDays(14),
        ]);
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

        return [$student, $grantee];
    }
}
