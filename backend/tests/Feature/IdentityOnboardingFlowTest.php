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
            ->assertJsonPath('data.next_step', 'liveness')
            ->assertJsonPath('data.qr_found', false);

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
        $this->assertIsArray(data_get($profileAfterScan->id_ocr_payload, 'back_fields'));
        $this->assertFalse((bool) data_get($profileAfterScan->id_ocr_payload, 'back_ocr.skipped'));
        $this->assertSame('local_ocr', data_get($profileAfterScan->id_ocr_payload, 'back_ocr.provider'));
        $this->assertSame("Maria Santos\nSTU-1\nBSIT", data_get($profileAfterScan->id_ocr_payload, 'back_ocr.text'));
        $this->assertFalse((bool) data_get($profileAfterScan->id_ocr_payload, 'back_ocr.text_empty'));
        $this->assertFalse((bool) data_get($profileAfterScan->id_ocr_payload, 'back_ocr.qr.found'));

        $this->actingAs($student)->post('/api/student/identity-onboarding/liveness', [
            ...$this->livenessPayload($this->faceDescriptor(0), [
                'distance' => 0.99, // spoofed client distance must be ignored
            ]),
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.next_step', 'done');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'active']);
        $this->assertDatabaseHas('grantee_identity_profiles', [
            'grantee_id' => $grantee->id,
            'status' => 'completed',
        ]);
    }

    public function test_liveness_mismatch_allows_retry_without_blocking(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();
        $granteeStatus = $grantee->status;
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_liveness',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'id_reference_face_descriptor' => $this->faceDescriptor(0),
        ]);
        Storage::disk('local')->put('identity/'.$grantee->id.'/id_reference_face.jpg', "\xFF\xD8\xFFfakejpeg");

        $this->actingAs($student)->post('/api/student/identity-onboarding/liveness', [
            ...$this->livenessPayload($this->faceDescriptor(7), [
                'challenge_sequence' => ['blink', 'turn_right', 'turn_left'],
                'distance' => 0.01, // spoofed "pass" distance must be ignored
            ]),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['face_descriptor']);

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'pending_identity']);
        $this->assertDatabaseHas('grantees', ['id' => $grantee->id, 'status' => $granteeStatus]);
        $this->assertDatabaseHas('grantee_identity_profiles', [
            'grantee_id' => $grantee->id,
            'status' => 'pending_liveness',
        ]);
        $this->assertDatabaseMissing('users', ['id' => $student->id, 'account_status' => 'blocked']);
        $this->assertDatabaseMissing('grantees', ['id' => $grantee->id, 'status' => 'identity_mismatch']);

        // Retry with a confident match still works after a hard mismatch.
        $this->actingAs($student)->post('/api/student/identity-onboarding/liveness', [
            ...$this->livenessPayload($this->faceDescriptor(0), [
                'challenge_sequence' => ['blink', 'turn_right', 'turn_left'],
            ]),
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.next_step', 'done');
    }

    public function test_liveness_uncertain_zone_flags_for_staff_review(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config([
            'services.identity.face_pass_max' => 0.45,
            'services.identity.face_review_max' => 0.60,
        ]);

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
            ...$this->livenessPayload($this->faceDescriptorAtDistance(0.50), [
                'distance' => 0.01,
            ]),
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'pending_face_review')
            ->assertJsonPath('data.next_step', 'face_review')
            ->assertJsonPath('data.face_zone', 'uncertain');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'pending_face_review']);
        $this->assertDatabaseHas('grantee_identity_profiles', [
            'grantee_id' => $grantee->id,
            'status' => 'pending_face_review',
        ]);
        $this->assertDatabaseHas('grantees', [
            'id' => $grantee->id,
            'status' => 'pending_face_review',
        ]);

        $profile = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->firstOrFail();
        $this->assertNotEmpty($profile->liveness_challenge_1_path);
        $this->assertNotEmpty($profile->liveness_challenge_2_path);
        $this->assertTrue(Storage::disk('local')->exists($profile->liveness_challenge_1_path));
        $this->assertTrue(Storage::disk('local')->exists($profile->liveness_challenge_2_path));
        $this->assertSame(['blink', 'turn_left'], $profile->liveness_challenge_labels);
    }

    public function test_staff_can_approve_pending_face_review(): void
    {
        Storage::fake('local');
        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_face_review'])->save();
        $grantee->update(['status' => 'pending_face_review']);
        $challenge1 = 'identity/'.$grantee->id.'/aaaabbbbccccddddeeeeffff00001111_liveness_challenge_1.jpg';
        $challenge2 = 'identity/'.$grantee->id.'/aaaabbbbccccddddeeeeffff00002222_liveness_challenge_2.jpg';
        $idRef = 'identity/'.$grantee->id.'/id_reference_face.jpg';
        $selfie = 'identity/'.$grantee->id.'/onboarding_selfie.jpg';
        $profile = GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_face_review',
            'id_reference_face_path' => $idRef,
            'onboarding_selfie_path' => $selfie,
            'liveness_challenge_1_path' => $challenge1,
            'liveness_challenge_2_path' => $challenge2,
            'liveness_challenge_labels' => ['turn_left', 'turn_right'],
            'id_reference_face_descriptor' => $this->faceDescriptor(0),
            'onboarding_selfie_descriptor' => $this->faceDescriptorAtDistance(0.5),
            'onboarding_face_distance' => 0.5,
        ]);
        Storage::disk('local')->put($idRef, "\xFF\xD8\xFFfakejpeg");
        Storage::disk('local')->put($selfie, "\xFF\xD8\xFFfakejpeg");
        Storage::disk('local')->put($challenge1, "\xFF\xD8\xFFfakejpeg");
        Storage::disk('local')->put($challenge2, "\xFF\xD8\xFFfakejpeg");

        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);

        $this->actingAs($staff)->getJson('/api/face-reviews')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $profile->id)
            ->assertJsonPath('data.0.liveness_challenge_labels.0', 'turn_left')
            ->assertJsonPath('data.0.liveness_challenge_labels.1', 'turn_right');

        $reviewDetail = $this->actingAs($staff)->getJson('/api/face-reviews/'.$profile->id)
            ->assertOk()
            ->json('data');
        $this->assertNotNull($reviewDetail['liveness_challenge_1_url'] ?? null);
        $this->assertNotNull($reviewDetail['liveness_challenge_2_url'] ?? null);
        // Staff Face Reviews loads stills via auth blob fetch — route must allow challenge filenames.
        $this->actingAs($staff)->get($reviewDetail['liveness_challenge_1_url'])->assertOk();
        $this->actingAs($staff)->get($reviewDetail['liveness_challenge_2_url'])->assertOk();
        $this->actingAs($staff)->get('/api/grantees/'.$grantee->id.'/identity-photos/onboarding_selfie.jpg')->assertOk();

        $this->actingAs($staff)->postJson("/api/face-reviews/{$profile->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.decision', 'approved');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'active']);
        $this->assertDatabaseHas('grantee_identity_profiles', [
            'id' => $profile->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('grantees', ['id' => $grantee->id, 'status' => 'verified']);

        $fresh = $profile->fresh();
        $this->assertNull($fresh->liveness_challenge_1_path);
        $this->assertNull($fresh->liveness_challenge_2_path);
        $this->assertNull($fresh->liveness_challenge_labels);
        $this->assertFalse(Storage::disk('local')->exists($challenge1));
        $this->assertFalse(Storage::disk('local')->exists($challenge2));
        // Slot 1 anchors retained
        $this->assertSame($idRef, $fresh->id_reference_face_path);
        $this->assertSame($selfie, $fresh->onboarding_selfie_path);
        $this->assertTrue(Storage::disk('local')->exists($idRef));
        $this->assertTrue(Storage::disk('local')->exists($selfie));
        $this->assertIsArray($fresh->id_reference_face_descriptor);
        $this->assertIsArray($fresh->onboarding_selfie_descriptor);
    }

    public function test_staff_can_reject_pending_face_review(): void
    {
        Storage::fake('local');
        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_face_review'])->save();
        $grantee->update(['status' => 'pending_face_review']);
        $challenge1 = 'identity/'.$grantee->id.'/aaaabbbbccccddddeeeeffff00001111_liveness_challenge_1.jpg';
        $challenge2 = 'identity/'.$grantee->id.'/aaaabbbbccccddddeeeeffff00002222_liveness_challenge_2.jpg';
        $idRef = 'identity/'.$grantee->id.'/id_reference_face.jpg';
        $selfie = 'identity/'.$grantee->id.'/onboarding_selfie.jpg';
        $profile = GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'pending_face_review',
            'id_reference_face_path' => $idRef,
            'onboarding_selfie_path' => $selfie,
            'liveness_challenge_1_path' => $challenge1,
            'liveness_challenge_2_path' => $challenge2,
            'liveness_challenge_labels' => ['blink', 'turn_left'],
            'id_reference_face_descriptor' => $this->faceDescriptor(0),
            'onboarding_selfie_descriptor' => $this->faceDescriptorAtDistance(0.55),
            'onboarding_face_distance' => 0.55,
        ]);
        Storage::disk('local')->put($idRef, "\xFF\xD8\xFFfakejpeg");
        Storage::disk('local')->put($selfie, "\xFF\xD8\xFFfakejpeg");
        Storage::disk('local')->put($challenge1, "\xFF\xD8\xFFfakejpeg");
        Storage::disk('local')->put($challenge2, "\xFF\xD8\xFFfakejpeg");

        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);

        $this->actingAs($staff)->postJson("/api/face-reviews/{$profile->id}/reject", [
            'reason' => 'Selfie does not match ID photo',
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'blocked')
            ->assertJsonPath('data.decision', 'rejected');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'blocked']);
        $this->assertDatabaseHas('grantees', ['id' => $grantee->id, 'status' => 'identity_mismatch']);

        $fresh = $profile->fresh();
        $this->assertNull($fresh->liveness_challenge_1_path);
        $this->assertNull($fresh->liveness_challenge_2_path);
        $this->assertFalse(Storage::disk('local')->exists($challenge1));
        $this->assertFalse(Storage::disk('local')->exists($challenge2));
        $this->assertSame($idRef, $fresh->id_reference_face_path);
        $this->assertSame($selfie, $fresh->onboarding_selfie_path);
        $this->assertTrue(Storage::disk('local')->exists($idRef));
        $this->assertTrue(Storage::disk('local')->exists($selfie));
        $this->assertIsArray($fresh->id_reference_face_descriptor);
        $this->assertIsArray($fresh->onboarding_selfie_descriptor);
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
        ])->assertOk()
            ->assertJsonPath('data.qr_found', true)
            ->assertJsonPath('data.qr_payload', 'https://registrar.tcc.edu.ph/verify?sid=STU-1');

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

    public function test_id_scan_stores_back_fields_from_ocr_text(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Http::fakeSequence('http://127.0.0.1:8001/ocr/image')
            ->push(['result' => ['cleaned_text' => "Maria Santos\nSTU-1\nBSIT"]], 200)
            ->push([
                'result' => [
                    'cleaned_text' => "SY 2026-2027\nEmergency Contact: Juan Dela Cruz\nRelationship: Mother\n09171234567",
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
        ])->assertOk()
            ->assertJsonPath('data.back_fields.school_year', '2026-2027')
            ->assertJsonPath('data.back_fields.emergency_contact_relationship', 'Mother');

        $profile = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->firstOrFail();
        $this->assertSame('2026-2027', data_get($profile->id_ocr_payload, 'back_fields.school_year'));
        $this->assertSame('Juan Dela Cruz', data_get($profile->id_ocr_payload, 'back_fields.emergency_contact_name'));
        $this->assertSame('Mother', data_get($profile->id_ocr_payload, 'back_fields.emergency_contact_relationship'));
        $this->assertNotEmpty(data_get($profile->id_ocr_payload, 'back_fields.emergency_contact_phone'));
    }

    public function test_ocr_health_proxy_reports_unavailable_when_service_down(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/health' => Http::failedConnection(),
        ]);

        [$student] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();

        $this->actingAs($student)->getJson('/api/student/identity-onboarding/ocr-health')
            ->assertOk()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.message', 'Local OCR (:8001) is unavailable');
    }

    public function test_ocr_health_proxy_reports_ok_when_service_up(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response([
                'status' => 'healthy',
                'tesseract_available' => true,
            ], 200),
        ]);

        [$student] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'pending_identity'])->save();

        $this->actingAs($student)->getJson('/api/student/identity-onboarding/ocr-health')
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.status', 'healthy');
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
            ->assertJsonValidationErrors(['id_frame'])
            ->assertJsonFragment(['id_frame' => [
                'Front of School ID: student ID matches, but the name is unreadable. Retake the front in brighter light with less glare so the full name is sharp. OCR saw: Someone Else STU-1',
            ]]);
    }

    public function test_ocr_front_passes_without_persisting_id_scan(): void
    {
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

        $this->actingAs($student)->post('/api/student/identity-onboarding/id-scan/ocr-front', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
        ])->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.extracted_name', 'Maria Santos')
            ->assertJsonPath('data.extracted_student_id', 'STU-1');

        $this->assertDatabaseHas('grantee_identity_profiles', [
            'grantee_id' => $grantee->id,
            'status' => 'pending_id_scan',
        ]);
        $this->assertNull(
            GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->value('id_scan_completed_at'),
        );
    }

    public function test_ocr_front_rejects_name_mismatch(): void
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

        $this->actingAs($student)->post('/api/student/identity-onboarding/id-scan/ocr-front', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_frame']);
    }

    public function test_show_returns_kyc_when_account_incomplete_even_with_stale_liveness_profile(): void
    {
        [$student, $grantee] = $this->studentWithMasterlist();
        $student->forceFill(['account_status' => 'unverified'])->save();

        GranteeIdentityProfile::query()->create([
            'grantee_id' => $grantee->id,
            'user_id' => $student->id,
            'status' => 'pending_liveness',
            'id_scan_completed_at' => now(),
        ]);

        $this->actingAs($student->fresh())
            ->getJson('/api/student/identity-onboarding')
            ->assertOk()
            ->assertJsonPath('data.next_step', 'kyc')
            ->assertJsonPath('data.account_status', 'unverified');
    }

    /**
     * @param  list<float>  $descriptor
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function livenessPayload(array $descriptor, array $overrides = []): array
    {
        return array_merge([
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'challenge_still_1' => UploadedFile::fake()->image('challenge1.jpg'),
            'challenge_still_2' => UploadedFile::fake()->image('challenge2.jpg'),
            'challenge_still_labels' => ['blink', 'turn_left'],
            'challenge_sequence' => ['blink', 'turn_left', 'turn_right'],
            'face_descriptor' => $descriptor,
            'liveness_confirmed' => true,
        ], $overrides);
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
