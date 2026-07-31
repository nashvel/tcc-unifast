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
    use \Tests\Support\FaceDescriptorFixtures;

    public function test_kyc_match_moves_to_pending_identity_not_active(): void
    {
        [$student, $grantee] = $this->studentWithMasterlist();

        $this->actingAs($student)->postJson('/api/student/kyc', [
            'first_name' => 'maria',
            'last_name' => 'santos',
            'student_id' => 'Stu-1',
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

    public function test_id_scan_ocr_and_face_then_liveness_activates_without_qr(): void
    {
        Storage::fake('public');
        Storage::fake('local');
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
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'face_descriptor' => $this->faceDescriptor(0),
            'face_quality_score' => 0.91,
            'authenticity_skipped' => true,
        ])->assertOk()
            ->assertJsonPath('data.next_step', 'liveness');

        $this->assertDatabaseHas('grantee_identity_profiles', [
            'grantee_id' => $grantee->id,
            'status' => 'pending_liveness',
        ]);

        $profileAfterScan = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->firstOrFail();
        $this->assertMatchesRegularExpression(
            '#^identity/'.$grantee->id.'/[a-f0-9]{32}_id_reference_face\.jpg$#',
            (string) $profileAfterScan->id_reference_face_path,
        );
        $this->assertTrue(Storage::disk('local')->exists($profileAfterScan->id_reference_face_path));
        $this->assertNotEmpty(data_get($profileAfterScan->id_ocr_payload, 'back_path'));
        $this->assertTrue((bool) data_get($profileAfterScan->id_ocr_payload, 'qr_deferred'));
        $this->assertFalse((bool) data_get($profileAfterScan->id_ocr_payload, 'qr_found'));
        $this->assertFalse((bool) data_get($profileAfterScan->id_ocr_payload, 'back_ocr.skipped'));
        $this->assertSame('local_ocr', data_get($profileAfterScan->id_ocr_payload, 'back_ocr.provider'));
        $this->assertSame("Maria Santos\nSTU-1\nBSIT", data_get($profileAfterScan->id_ocr_payload, 'back_ocr.text'));
        $this->assertFalse((bool) data_get($profileAfterScan->id_ocr_payload, 'back_ocr.text_empty'));
        $this->assertFalse((bool) data_get($profileAfterScan->id_ocr_payload, 'back_ocr.qr.found'));

        $this->actingAs($student)->post('/api/student/identity-onboarding/liveness', [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'challenge_sequence' => ['blink', 'turn_left', 'turn_right'],
            'face_descriptor' => $this->faceDescriptor(0),
            'distance' => 0.99, // spoofed client distance must be ignored
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
        Storage::fake('local');
        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_liveness',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'id_reference_face_descriptor' => $this->faceDescriptor(0),
        ]);
        Storage::disk('local')->put('identity/'.$grantee->id.'/id_reference_face.jpg', "\xFF\xD8\xFFfakejpeg");

        $this->actingAs($student)->post('/api/student/identity-onboarding/liveness', [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'challenge_sequence' => ['blink', 'turn_right', 'turn_left'],
            'face_descriptor' => $this->faceDescriptor(7),
            'distance' => 0.01, // spoofed "pass" distance must be ignored
            'liveness_confirmed' => true,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'blocked']);
    }

    public function test_id_scan_rejects_non_image_payload(): void
    {
        Storage::fake('local');
        Http::fake();
        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_id_scan',
        ]);

        $this->actingAs($student)->post('/api/student/identity-onboarding/id-scan', [
            'id_frame' => UploadedFile::fake()->createWithContent('id.jpg', 'MZ-not-image'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'face_descriptor' => $this->faceDescriptor(0),
            'face_quality_score' => 0.9,
        ])->assertUnprocessable();
    }

    public function test_id_scan_accepts_empty_back_ocr_text_when_service_ok(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Http::fakeSequence('http://127.0.0.1:8001/ocr/image')
            ->push(['result' => ['cleaned_text' => "Maria Santos\nSTU-1\nBSIT"]], 200)
            ->push(['result' => ['cleaned_text' => '']], 200);

        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();
        $grantee->update(['status' => 'kyc_verified']);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_id_scan',
        ]);

        $this->actingAs($student)->post('/api/student/identity-onboarding/id-scan', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'face_descriptor' => $this->faceDescriptor(0),
            'face_quality_score' => 0.91,
            'authenticity_skipped' => true,
        ])->assertOk()
            ->assertJsonPath('data.next_step', 'liveness');

        $profile = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->firstOrFail();
        $this->assertFalse((bool) data_get($profile->id_ocr_payload, 'back_ocr.skipped'));
        $this->assertTrue((bool) data_get($profile->id_ocr_payload, 'back_ocr.text_empty'));
        $this->assertSame('', data_get($profile->id_ocr_payload, 'back_ocr.text'));
        $this->assertNotEmpty(data_get($profile->id_ocr_payload, 'back_ocr.warning'));
    }

    public function test_id_scan_fails_clearly_when_back_ocr_service_errors(): void
    {
        Storage::fake('local');
        Http::fakeSequence('http://127.0.0.1:8001/ocr/image')
            ->push(['result' => ['cleaned_text' => "Maria Santos\nSTU-1\nBSIT"]], 200)
            ->push(['error' => ['message' => 'Tesseract OCR is not available.']], 503);

        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();
        $grantee->update(['status' => 'kyc_verified']);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_id_scan',
        ]);

        $this->actingAs($student)->post('/api/student/identity-onboarding/id-scan', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'face_descriptor' => $this->faceDescriptor(0),
            'face_quality_score' => 0.91,
            'authenticity_skipped' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_back']);
    }

    public function test_id_scan_stores_back_qr_when_local_ocr_returns_it(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Http::fakeSequence('http://127.0.0.1:8001/ocr/image')
            ->push([
                'result' => ['cleaned_text' => "Maria Santos\nSTU-1\nBSIT"],
                'qr_code' => ['found' => false, 'value' => null],
            ], 200)
            ->push([
                'result' => ['cleaned_text' => 'Emergency contact'],
                'qr_code' => [
                    'found' => true,
                    'value' => 'https://registrar.tcc.edu.ph/verify?sid=STU-1',
                    'type' => 'QRCODE',
                    'engine' => 'pyzbar',
                ],
            ], 200);

        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();
        $grantee->update(['status' => 'kyc_verified']);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_id_scan',
        ]);

        $this->actingAs($student)->post('/api/student/identity-onboarding/id-scan', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'face_descriptor' => $this->faceDescriptor(0),
            'face_quality_score' => 0.91,
            'authenticity_skipped' => true,
        ])->assertOk();

        $profile = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->firstOrFail();
        $this->assertSame(
            'https://registrar.tcc.edu.ph/verify?sid=STU-1',
            $profile->id_qr_payload,
        );
        $this->assertTrue((bool) data_get($profile->id_ocr_payload, 'qr_found'));
        $this->assertFalse((bool) data_get($profile->id_ocr_payload, 'qr_deferred'));
        $this->assertTrue((bool) data_get($profile->id_ocr_payload, 'back_ocr.qr.found'));
        $this->assertSame('pyzbar', data_get($profile->id_ocr_payload, 'back_ocr.qr.engine'));
    }

    public function test_id_scan_maps_name_mismatch_to_id_frame(): void
    {
        Storage::fake('local');
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => "Someone Else\nSTU-1"],
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
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'face_descriptor' => $this->faceDescriptor(0),
            'face_quality_score' => 0.91,
            'authenticity_skipped' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_frame']);
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
