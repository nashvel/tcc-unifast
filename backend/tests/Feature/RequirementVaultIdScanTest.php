<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\PolicySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FaceDescriptorFixtures;
use Tests\TestCase;

class RequirementVaultIdScanTest extends TestCase
{
    use FaceDescriptorFixtures;
    use RefreshDatabase;

    public function test_ocr_front_passes_without_persisting_school_id_slot(): void
    {
        Storage::fake('local');
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => "Maria Santos\nSTU-VAULT-1\nBSIT"],
            ], 200),
        ]);

        [$student, $grantee] = $this->activeVaultStudent('STU-VAULT-1', 'Maria Santos');
        $this->seedCompletedOnboarding($student, $grantee);

        $this->actingAs($student)->post('/api/student/requirement-vault/id/ocr-front', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
        ])->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.extracted_name', 'Maria Santos')
            ->assertJsonPath('data.extracted_student_id', 'STU-VAULT-1');

        $this->assertDatabaseMissing('document_submissions', [
            'grantee_id' => $grantee->id,
            'slot_key' => 'school_id',
        ]);
    }

    public function test_ocr_front_rejects_name_mismatch(): void
    {
        Storage::fake('local');
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => "Someone Else\nSTU-VAULT-2"],
            ], 200),
        ]);

        [$student, $grantee] = $this->activeVaultStudent('STU-VAULT-2', 'Maria Santos');
        $this->seedCompletedOnboarding($student, $grantee);

        $this->actingAs($student)->post('/api/student/requirement-vault/id/ocr-front', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_frame']);
    }

    public function test_store_id_requires_back_and_accepts_front_back_qr(): void
    {
        Storage::fake('local');
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => "Maria Santos\nSTU-VAULT-3\nBSIT\nSY 2026-2027"],
            ], 200),
        ]);
        PolicySetting::setValue('organization_academic_year', '2026-2027');

        [$student, $grantee] = $this->activeVaultStudent('STU-VAULT-3', 'Maria Santos');
        $face = $this->faceDescriptor(4);
        $this->seedCompletedOnboarding($student, $grantee, $face);

        $this->actingAs($student)->post('/api/student/requirement-vault/id', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'qr_payload' => 'https://registrar.tcc.edu.ph/verify/abc',
            'face_descriptor' => $face,
            'face_quality_score' => 0.91,
            'consent_accepted' => '1',
            'precheck_accepted' => '1',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_back']);

        $this->actingAs($student)->post('/api/student/requirement-vault/id', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'qr_payload' => 'https://registrar.tcc.edu.ph/verify/abc',
            'face_descriptor' => $face,
            'face_quality_score' => 0.91,
            'consent_accepted' => '1',
            'precheck_accepted' => '1',
        ])->assertOk()
            ->assertJsonPath('data.slot_key', 'school_id')
            ->assertJsonPath('data.status', 'draft');

        $submission = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('slot_key', 'school_id')
            ->first();
        $this->assertNotNull($submission);
        $this->assertNotNull($submission->secondary_stored_path);
        $this->assertSame('https://registrar.tcc.edu.ph/verify/abc', data_get($submission->metadata_payload, 'qr_payload'));
        $this->assertTrue((bool) data_get($submission->metadata_payload, 'qr_found'));
        $this->assertTrue((bool) data_get($submission->metadata_payload, 'qr_valid'));
        $this->assertTrue((bool) data_get($submission->metadata_payload, 'qr_extraction.parseable'));
        $this->assertSame('url', data_get($submission->metadata_payload, 'qr_extraction.kind'));
        $this->assertSame('registrar.tcc.edu.ph', data_get($submission->metadata_payload, 'qr_extraction.host'));
        $this->assertSame('/verify/abc', data_get($submission->metadata_payload, 'qr_extraction.path'));
        $this->assertSame('abc', data_get($submission->metadata_payload, 'qr_extraction.student_id'));
        $this->assertNotNull(data_get($submission->metadata_payload, 'back_path'));
        $this->assertNotNull(data_get($submission->metadata_payload, 'frame_path'));
        $this->assertSame('2026-2027', data_get($submission->metadata_payload, 'academic_year_ocr'));
        $this->assertSame('2026-2027', data_get($submission->metadata_payload, 'academic_year_expected'));
        $this->assertTrue((bool) data_get($submission->metadata_payload, 'academic_year_match'));
    }

    public function test_store_id_succeeds_without_qr_with_soft_flags(): void
    {
        Storage::fake('local');
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => "Maria Santos\nSTU-VAULT-4\nBSIT"],
            ], 200),
        ]);

        [$student, $grantee] = $this->activeVaultStudent('STU-VAULT-4', 'Maria Santos');
        $face = $this->faceDescriptor(5);
        $this->seedCompletedOnboarding($student, $grantee, $face);

        $this->actingAs($student)->post('/api/student/requirement-vault/id', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'face_descriptor' => $face,
            'face_quality_score' => 0.91,
            'consent_accepted' => '1',
            'precheck_accepted' => '1',
        ])->assertOk()
            ->assertJsonPath('data.slot_key', 'school_id')
            ->assertJsonPath('data.status', 'draft');

        $submission = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('slot_key', 'school_id')
            ->first();
        $this->assertNotNull($submission);
        $this->assertFalse((bool) data_get($submission->metadata_payload, 'qr_found'));
        $this->assertFalse((bool) data_get($submission->metadata_payload, 'qr_valid'));
        $this->assertNull(data_get($submission->metadata_payload, 'qr_payload'));
        $this->assertFalse((bool) data_get($submission->metadata_payload, 'qr_extraction.parseable'));
        $this->assertNull(data_get($submission->metadata_payload, 'qr_extraction.raw'));
    }

    public function test_store_id_invalid_qr_soft_flags_without_blocking(): void
    {
        Storage::fake('local');
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => "Maria Santos\nSTU-VAULT-5\nBSIT\nSchool Year 2025-2026"],
            ], 200),
        ]);
        PolicySetting::setValue('organization_academic_year', '2026-2027');

        [$student, $grantee] = $this->activeVaultStudent('STU-VAULT-5', 'Maria Santos');
        $face = $this->faceDescriptor(6);
        $this->seedCompletedOnboarding($student, $grantee, $face);

        $this->actingAs($student)->post('/api/student/requirement-vault/id', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'qr_payload' => 'https://evil.example.com/fake',
            'face_descriptor' => $face,
            'face_quality_score' => 0.91,
            'consent_accepted' => '1',
            'precheck_accepted' => '1',
        ])->assertOk()
            ->assertJsonPath('data.slot_key', 'school_id');

        $submission = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('slot_key', 'school_id')
            ->first();
        $this->assertNotNull($submission);
        $this->assertTrue((bool) data_get($submission->metadata_payload, 'qr_found'));
        $this->assertFalse((bool) data_get($submission->metadata_payload, 'qr_valid'));
        $this->assertSame('https://evil.example.com/fake', data_get($submission->metadata_payload, 'qr_payload'));
        $this->assertTrue((bool) data_get($submission->metadata_payload, 'qr_extraction.parseable'));
        $this->assertSame('evil.example.com', data_get($submission->metadata_payload, 'qr_extraction.host'));
        $this->assertSame('2025-2026', data_get($submission->metadata_payload, 'academic_year_ocr'));
        $this->assertSame('2026-2027', data_get($submission->metadata_payload, 'academic_year_expected'));
        $this->assertFalse((bool) data_get($submission->metadata_payload, 'academic_year_match'));
    }

    /**
     * @return array{0: User, 1: Grantee}
     */
    private function activeVaultStudent(string $studentId, string $fullName): array
    {
        $student = User::factory()->create([
            'role' => 'student',
            'name' => $fullName,
            'student_id' => $studentId,
            'account_status' => 'active',
        ]);
        $batch = Batch::create([
            'name' => 'AY 2026-1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'status' => 'active',
            'window_status' => 'active',
            'is_active' => true,
            'submission_deadline' => now()->addDays(14),
        ]);
        $grantee = Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => $studentId,
            'student_number' => '2026-'.$studentId,
            'full_name' => $fullName,
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'verified',
            'submission_status' => 'not_submitted',
        ]);

        return [$student, $grantee];
    }

    /**
     * @param  list<float>|null  $descriptor
     */
    private function seedCompletedOnboarding(User $student, Grantee $grantee, ?array $descriptor = null): void
    {
        $face = $descriptor ?? $this->faceDescriptor(1);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'completed',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'onboarding_selfie_path' => 'identity/'.$grantee->id.'/onboarding_selfie.jpg',
            'id_reference_face_descriptor' => $face,
            'onboarding_selfie_descriptor' => $face,
            'onboarding_completed_at' => now(),
            'id_scan_completed_at' => now(),
            'id_ocr_payload' => [
                'extracted_name' => $grantee->full_name,
            ],
        ]);
        Storage::disk('local')->put('identity/'.$grantee->id.'/id_reference_face.jpg', "\xFF\xD8\xFFfake");
        Storage::disk('local')->put('identity/'.$grantee->id.'/onboarding_selfie.jpg', "\xFF\xD8\xFFfake");
    }
}
