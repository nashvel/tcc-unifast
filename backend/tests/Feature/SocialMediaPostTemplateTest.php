<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialMediaPostTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_generate_facebook_copy_from_real_batch_data_without_grantee_pii(): void
    {
        config()->set('app.frontend_url', 'https://tes.tcc.edu.ph');
        config()->set('mail.from.address', 'tes@tcc.edu.ph');

        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        $batch = Batch::create([
            'name' => 'TES Batch 2026-A',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'submission_deadline' => now()->addWeek(),
            'is_active' => true,
            'window_status' => 'active',
            'status' => 'active',
        ]);

        Grantee::create([
            'batch_id' => $batch->id,
            'student_id' => 'STU-PII-001',
            'student_number' => '2026-0001',
            'full_name' => 'Private Student',
            'email' => 'private.student@example.test',
            'program' => 'BSIT',
            'status' => 'eligible',
        ]);

        $this->actingAs($staff)
            ->getJson("/api/social-media-posts/template?batch_id={$batch->id}")
            ->assertOk()
            ->assertJsonPath('data.batch.id', $batch->id)
            ->assertJsonPath('data.facts.portal_url', 'https://tes.tcc.edu.ph/login')
            ->assertJsonPath('data.facts.support_email', 'tes@tcc.edu.ph')
            ->assertJsonPath('data.facts.grantees_count', 1)
            ->assertJsonPath('data.campaign', 'tes_batch_2026_a')
            ->assertJsonMissing(['Private Student', 'private.student@example.test', 'STU-PII-001']);
    }
}
