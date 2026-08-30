<?php

namespace Tests\Feature;

use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FaceDescriptorFixtures;
use Tests\TestCase;

/**
 * End-to-end journey for identity-first activation.
 *
 * Every phase has unit/feature coverage in isolation; this exercises the seams
 * between them, which is where the real defects have been. It walks one student
 * from an emailed link all the way into the vault and asserts, at each step, that
 * the credential does not exist yet.
 */
class IdentityFirstJourneyTest extends TestCase
{
    use FaceDescriptorFixtures;
    use RefreshDatabase;

    public function test_student_walks_link_to_vault_and_only_gets_a_password_at_the_end(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => "Maria Santos\nSTU-1\nBSIT"],
            ], 200),
        ]);

        [$student, $grantee, $batch, $token] = $this->invitedStudent();
        $invitedHash = $student->password;

        // ── 1. Public probe leaks nothing identifying ─────────────────────────
        $probe = $this->getJson("/api/activation/{$token}")->assertOk();
        $this->assertStringNotContainsString('STU-1', $probe->getContent());
        $this->assertStringNotContainsString('Maria Santos', $probe->getContent());

        // ── 2. /begin opens a scoped session, sets no credential ──────────────
        $this->postJson("/api/activation/{$token}/begin")
            ->assertOk()
            ->assertJsonPath('user.onboarding_next_step', 'kyc');

        $student = $student->fresh();
        $this->assertSame('pending_kyc', $student->account_status);
        $this->assertSame($invitedHash, $student->password);
        $this->assertNull($student->email_verified_at);
        $this->assertDatabaseCount('refresh_tokens', 0);

        // The token is stamped as opened but NOT spent, so the funnel can resume.
        $activation = ActivationToken::query()->firstOrFail();
        $this->assertNotNull($activation->first_used_at);
        $this->assertNull($activation->used_at);

        // ── 3. Onboarding session is confined to the funnel ───────────────────
        $this->actAsOnboarding($student);
        $this->getJson('/api/student/requirement-vault')->assertForbidden();
        $this->getJson('/api/student/notifications')->assertForbidden();

        // ── 4. KYC ────────────────────────────────────────────────────────────
        $this->postJson('/api/student/kyc', [
            'first_name' => 'maria',
            'last_name' => 'santos',
            'student_id' => 'Stu-1',
            'program' => 'BSIT',
            'year_level' => '1',
            'contact' => '+639171234567',
        ])->assertOk()->assertJsonPath('data.next_step', 'id_scan');

        $student = $student->fresh();
        $this->assertSame('pending_identity', $student->account_status);
        $this->assertSame($invitedHash, $student->password, 'Still no credential after KYC.');

        // ── 5. ID scan ────────────────────────────────────────────────────────
        $this->actAsOnboarding($student);
        $this->post('/api/student/identity-onboarding/id-scan', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'face_descriptor' => $this->faceDescriptor(0),
            'face_quality_score' => 0.91,
            'authenticity_skipped' => true,
        ])->assertOk()->assertJsonPath('data.next_step', 'liveness');

        $this->assertSame($invitedHash, $student->fresh()->password, 'Still no credential after ID scan.');

        // ── 6. Liveness auto-pass → identity_verified, still no password ──────
        $this->post('/api/student/identity-onboarding/liveness', $this->livenessPayload($this->faceDescriptor(0)))
            ->assertOk()
            ->assertJsonPath('data.account_status', 'identity_verified')
            ->assertJsonPath('data.next_step', 'credentials');

        $student = $student->fresh();
        $this->assertSame('identity_verified', $student->account_status);
        $this->assertSame($invitedHash, $student->password, 'Identity is proven but the account has no credential.');
        $this->assertNull($student->email_verified_at);

        // Vault is still closed: verified is not the same as credentialed.
        $this->actAsOnboarding($student);
        $this->getJson('/api/student/requirement-vault')->assertForbidden();

        // ── 7. Credential creation — the only place a password is written ─────
        $this->postJson('/api/onboarding/credentials', [
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertOk()->assertJsonPath('user.account_status', 'active');

        $student = $student->fresh();
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $student->password));
        $this->assertNotNull($student->email_verified_at);
        $this->assertNotNull($student->activated_at);
        $this->assertNotNull(ActivationToken::query()->firstOrFail()->used_at, 'Token is spent here, not at link click.');
        $this->assertDatabaseHas('refresh_tokens', ['user_id' => $student->id, 'scope' => 'full']);

        // ── 8. Vault now opens, and holds exactly 3 slots ─────────────────────
        $batch->update([
            'status' => 'active',
            'window_status' => 'open',
            'is_active' => true,
            'submission_deadline' => now()->addDays(7),
        ]);
        $grantee->update(['status' => 'verified']);

        Sanctum::actingAs($student, ['*']);
        $this->getJson('/api/student/requirement-vault')->assertOk();

        foreach ([
            ['course_history', 'course.pdf', 'pdf'],
            ['grade_slip', 'grades.pdf', 'pdf'],
            ['specimen_signatures', 'specimens.pdf', 'pdf'],
        ] as [$slot, $name, $kind]) {
            $this->post('/api/student/requirement-vault/document', [
                'slot_key' => $slot,
                'file' => UploadedFile::fake()->createWithContent(
                    $name,
                    "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n",
                ),
            ])->assertOk()->assertJsonPath('data.slot_key', $slot);
        }

        // school_id is gone: identity is verified once, during onboarding.
        $this->post('/api/student/requirement-vault/document', [
            'slot_key' => 'school_id',
            'file' => UploadedFile::fake()->image('id.jpg'),
        ])->assertUnprocessable();

        $this->postJson('/api/student/requirement-vault/confirm')
            ->assertOk()
            ->assertJsonPath('grantee.submission_status', 'docs_submitted');
    }

    /**
     * A student who abandons mid-funnel resumes from the same link, not the start.
     */
    public function test_abandoned_funnel_resumes_from_the_same_link(): void
    {
        [$student, , , $token] = $this->invitedStudent();

        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $this->actAsOnboarding($student->fresh());
        $this->postJson('/api/student/kyc', [
            'first_name' => 'maria',
            'last_name' => 'santos',
            'student_id' => 'Stu-1',
            'program' => 'BSIT',
            'year_level' => '1',
            'contact' => '+639171234567',
        ])->assertOk();

        // Session lapses; the student reopens the emailed link.
        $this->postJson("/api/activation/{$token}/begin")
            ->assertOk()
            ->assertJsonPath('user.onboarding_next_step', 'id_scan')
            ->assertJsonPath('user.onboarding_path', '/student/onboarding/id-scan');
    }

    /**
     * Staff rejection is recoverable: the real grantee keeps a way back in.
     */
    public function test_rejected_face_review_leaves_the_student_recoverable(): void
    {
        Storage::fake('local');
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => "Maria Santos\nSTU-1\nBSIT"],
            ], 200),
        ]);

        [$student, $grantee, , $token] = $this->invitedStudent();
        $this->postJson("/api/activation/{$token}/begin")->assertOk();

        $this->actAsOnboarding($student->fresh());
        $this->postJson('/api/student/kyc', [
            'first_name' => 'maria',
            'last_name' => 'santos',
            'student_id' => 'Stu-1',
            'program' => 'BSIT',
            'year_level' => '1',
            'contact' => '+639171234567',
        ])->assertOk();

        $this->actAsOnboarding($student->fresh());
        $this->post('/api/student/identity-onboarding/id-scan', [
            'id_frame' => UploadedFile::fake()->image('id_front.jpg'),
            'id_back' => UploadedFile::fake()->image('id_back.jpg'),
            'id_face_crop' => UploadedFile::fake()->image('face.jpg'),
            'face_descriptor' => $this->faceDescriptor(0),
            'face_quality_score' => 0.91,
            'authenticity_skipped' => true,
        ])->assertOk();

        // Borderline match → staff review.
        $this->post(
            '/api/student/identity-onboarding/liveness',
            $this->livenessPayload($this->faceDescriptorAtDistance(0.52)),
        )->assertOk()->assertJsonPath('data.next_step', 'face_review');

        $profile = $grantee->identityProfile()->firstOrFail();
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);

        $this->actingAs($staff)
            ->postJson("/api/face-reviews/{$profile->id}/reject", ['reason' => 'Selfie does not match ID photo'])
            ->assertOk()
            ->assertJsonPath('data.account_status', 'identity_rejected');

        $student = $student->fresh();
        // Recoverable, not blocked — and still no credential to be stolen.
        $this->assertSame('identity_rejected', $student->account_status);
        $this->assertFalse(Hash::check('Str0ng-Passw0rd!', $student->password));
        $this->assertSame(0, $student->tokens()->count(), 'The rejected session is revoked.');
        $this->assertDatabaseHas('activation_tokens', ['user_id' => $student->id, 'used_at' => null]);
    }

    private function actAsOnboarding(User $student): void
    {
        Sanctum::actingAs($student->fresh(), [AuthTokenService::ONBOARDING_ABILITY]);
    }

    /**
     * @param  list<float>  $descriptor
     * @return array<string, mixed>
     */
    private function livenessPayload(array $descriptor): array
    {
        return [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'challenge_still_1' => UploadedFile::fake()->image('challenge1.jpg'),
            'challenge_still_2' => UploadedFile::fake()->image('challenge2.jpg'),
            'challenge_still_labels' => ['blink', 'turn_left'],
            'challenge_sequence' => ['blink', 'turn_left', 'turn_right'],
            'face_descriptor' => $descriptor,
            'liveness_confirmed' => true,
        ];
    }

    /**
     * @return array{0: User, 1: Grantee, 2: Batch, 3: string}
     */
    private function invitedStudent(): array
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-1',
            'name' => 'Maria Santos',
            'account_status' => 'unverified',
            // Unusable hash: an invited account holds no credential.
            'password' => Hash::make(Str::random(64)),
            'email_verified_at' => null,
            'activated_at' => null,
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

        $plainToken = Str::random(48);
        ActivationToken::create([
            'user_id' => $student->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours(24),
        ]);

        return [$student, $grantee, $batch, $plainToken];
    }
}
