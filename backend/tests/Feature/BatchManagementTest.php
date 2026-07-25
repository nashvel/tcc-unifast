<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BatchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_batch_deactivates_previous_active_batch(): void
    {
        Mail::fake();
        $head = User::factory()->create(['role' => 'head', 'account_status' => 'active']);
        $first = Batch::create([
            'name' => 'Batch 1',
            'academic_year' => 'AY 2026-2027',
            'semester' => '1st Semester',
            'submission_deadline' => now()->addDays(5),
            'is_active' => true,
            'window_status' => 'active',
            'status' => 'active',
        ]);
        $second = Batch::create([
            'name' => 'Batch 2',
            'academic_year' => 'AY 2026-2027',
            'semester' => '2nd Semester',
            'submission_deadline' => now()->addDays(5),
            'window_status' => 'draft',
            'status' => 'draft',
        ]);

        $this->actingAs($head)
            ->postJson("/api/batches/{$second->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.window_status', 'active');

        $this->assertDatabaseHas('batches', ['id' => $first->id, 'is_active' => false, 'window_status' => 'closed']);
        $this->assertDatabaseHas('batches', ['id' => $second->id, 'is_active' => true, 'window_status' => 'active']);
    }

    public function test_student_submission_window_requires_active_non_expired_batch(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-1',
            'account_status' => 'active',
        ]);
        $batch = Batch::create([
            'name' => 'Batch 1',
            'academic_year' => 'AY 2026-2027',
            'semester' => '1st Semester',
            'submission_deadline' => now()->subDay(),
            'is_active' => true,
            'window_status' => 'active',
            'status' => 'active',
        ]);
        Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-1',
            'student_number' => '2026-0001',
            'full_name' => 'Maria Santos',
            'email' => $student->email,
            'program' => 'BSIT',
        ]);

        $this->actingAs($student)
            ->getJson('/api/student/submission-window')
            ->assertOk()
            ->assertJsonPath('data.open', false)
            ->assertJsonPath('data.status', 'expired');

        $batch->update(['submission_deadline' => now()->addDay()]);

        $this->actingAs($student)
            ->getJson('/api/student/submission-window')
            ->assertOk()
            ->assertJsonPath('data.open', true)
            ->assertJsonPath('data.status', 'active');
    }
}
