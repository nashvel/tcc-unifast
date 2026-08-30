<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Document Vault PIN (DFD subprocess 3.7) must be verified server-side.
 *
 * The UI prompted for it and disabled the submit button, but the API never checked
 * it — so a direct request could confirm a package without the PIN. These tests
 * pin the enforcement so the documented control cannot silently become UI-only again.
 */
class VaultPinEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_is_rejected_when_the_pin_is_missing(): void
    {
        [$student] = $this->readyStudent(pin: '123456');

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);

        $this->assertSame('not_submitted', $student->grantee->fresh()->submission_status);
    }

    public function test_confirm_is_rejected_when_the_pin_is_wrong(): void
    {
        [$student] = $this->readyStudent(pin: '123456');

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm', ['pin' => '999999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);

        // Rejections are recorded so repeated guessing is visible in the audit trail.
        $this->assertDatabaseHas('audit_logs', ['action' => 'vault_pin_rejected']);
    }

    public function test_confirm_succeeds_with_the_correct_pin(): void
    {
        [$student, $grantee] = $this->readyStudent(pin: '123456');

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm', ['pin' => '123456'])
            ->assertOk()
            ->assertJsonPath('grantee.submission_status', 'docs_submitted');

        $this->assertSame('docs_submitted', $grantee->fresh()->submission_status);
    }

    public function test_students_without_a_pin_are_unaffected(): void
    {
        [$student, $grantee] = $this->readyStudent(pin: null);

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm')
            ->assertOk();

        $this->assertSame('docs_submitted', $grantee->fresh()->submission_status);
    }

    public function test_repeated_wrong_pins_are_locked_out(): void
    {
        [$student] = $this->readyStudent(pin: '123456');
        $max = (int) config('services.requirement_vault.pin_max_attempts', 5);

        for ($i = 0; $i < $max; $i++) {
            $this->actingAs($student)
                ->postJson('/api/student/requirement-vault/confirm', ['pin' => '000000'])
                ->assertUnprocessable();
        }

        // Even the correct PIN is refused once the limiter trips.
        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm', ['pin' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
    }

    /**
     * A student with identity onboarding complete and all three vault slots filled.
     *
     * @return array{0: User, 1: Grantee}
     */
    private function readyStudent(?string $pin): array
    {
        Storage::fake('local');

        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-PIN-1',
            'name' => 'Maria Santos',
            'account_status' => 'active',
            'security_pin' => $pin ? Hash::make($pin) : null,
        ]);

        $batch = Batch::create([
            'name' => 'AY 2026-1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'status' => 'active',
            'window_status' => 'open',
            'is_active' => true,
            'submission_deadline' => now()->addDays(7),
        ]);

        $grantee = Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-PIN-1',
            'student_number' => '2026-0001',
            'full_name' => 'Maria Santos',
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'verified',
            'submission_status' => 'not_submitted',
        ]);

        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'completed',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'onboarding_selfie_path' => 'identity/'.$grantee->id.'/onboarding_selfie.jpg',
            'onboarding_completed_at' => now(),
            'id_ocr_payload' => ['extracted_name' => 'Maria Santos'],
        ]);

        foreach ([
            'course_history' => 'Course History',
            'grade_slip' => 'Grade Slip',
            'specimen_signatures' => 'ID (Back-to-Back) & Specimen',
        ] as $slot => $label) {
            $path = 'documents/'.$grantee->id.'/'.$batch->id.'/'.$slot.'.pdf';
            Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF\n");

            DocumentSubmission::create([
                'student_id' => $student->student_id,
                'grantee_id' => $grantee->id,
                'batch_id' => $batch->id,
                'slot_key' => $slot,
                'student_name' => $student->name,
                'document_type' => $label,
                'original_name' => $slot.'.pdf',
                'stored_path' => $path,
                'mime_type' => 'application/pdf',
                'file_size' => 20,
                'status' => 'draft',
                'risk_level' => 'low',
            ]);
        }

        return [$student, $grantee];
    }
}
