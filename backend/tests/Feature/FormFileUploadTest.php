<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormFileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_grantee_can_submit_form_with_required_image_upload(): void
    {
        Storage::fake('local');

        $batch = Batch::create([
            'name' => 'TES 2026 Batch',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'submission_deadline' => now()->addDays(10),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);

        $grantee = Grantee::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-FORM-01',
            'full_name' => 'Maria Form Student',
            'email' => $user->email,
            'program' => 'BSIT',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $form = Form::create([
            'title' => 'Student ID Verification Form',
            'visibility' => 'private',
            'batch_id' => $batch->id,
            'target_role' => 'grantee',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'label' => 'Full Name',
            'field_name' => 'full_name',
            'field_type' => 'text',
            'is_required' => true,
            'sort_order' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'label' => 'Valid ID Photo',
            'field_name' => 'id_photo',
            'field_type' => 'file',
            'accepted_types' => 'image/jpeg,image/png,image/webp',
            'max_file_size' => 5120,
            'is_required' => true,
            'sort_order' => 2,
        ]);

        $fakeImage = UploadedFile::fake()->image('id_photo.png', 400, 400);

        $response = $this->actingAs($user)
            ->post("/api/forms/{$form->id}/responses", [
                'full_name' => 'Maria Form Student',
                'id_photo' => $fakeImage,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('form_responses', [
            'form_id' => $form->id,
            'grantee_id' => $grantee->id,
        ]);

        $storedResponse = FormResponse::first();
        $storedPath = $storedResponse->responses['id_photo'];
        $this->assertStringStartsWith('form-uploads/', $storedPath);
        Storage::disk('local')->assertExists($storedPath);
    }

    public function test_form_fails_validation_when_required_image_is_omitted(): void
    {
        Storage::fake('local');

        $batch = Batch::create([
            'name' => 'TES 2026 Batch',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);

        Grantee::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-FORM-02',
            'full_name' => 'Student Without Image',
            'email' => $user->email,
            'program' => 'BSIT',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $form = Form::create([
            'title' => 'Photo Requirement Form',
            'visibility' => 'private',
            'batch_id' => $batch->id,
            'target_role' => 'grantee',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'label' => 'Profile Picture',
            'field_name' => 'profile_pic',
            'field_type' => 'file',
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/forms/{$form->id}/responses", [
                'profile_pic' => null,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_pic']);
    }

    public function test_staff_can_stream_uploaded_form_image(): void
    {
        Storage::fake('local');

        $staff = User::factory()->create([
            'role' => 'staff',
            'account_status' => 'active',
        ]);

        $form = Form::create([
            'title' => 'Staff Review Form',
            'visibility' => 'private',
            'is_active' => true,
            'created_by' => $staff->id,
        ]);

        $fakeContent = 'fake image binary content';
        $path = 'form-uploads/test-image-uuid.png';
        Storage::disk('local')->put($path, $fakeContent);

        $responseRecord = FormResponse::create([
            'form_id' => $form->id,
            'grantee_id' => null,
            'responses' => ['photo' => $path],
            'response_hash' => 'dummy_hash',
            'is_authenticated' => false,
            'submitter_ip' => '127.0.0.1',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($staff)
            ->get("/api/forms/{$form->id}/responses/{$responseRecord->id}/files/photo");

        $response->assertOk();
    }
}
