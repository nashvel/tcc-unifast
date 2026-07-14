<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicRecord;
use App\Models\AcademicSemester;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_records_include_course_remarks_and_approved_submission_count(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'student_id' => 'STU-1']);
        $grantee = Grantee::create([
            'user_id' => $student->id,
            'student_id' => 'STU-1',
            'student_number' => '2026-0001',
            'full_name' => 'Maria Santos',
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '1',
        ]);
        $record = AcademicRecord::create([
            'grantee_id' => $grantee->id,
            'student_id' => 'STU-1',
            'student_number' => '2026-0001',
            'grantee_name' => 'Maria Santos',
            'program' => 'BSIT',
            'year_level' => '1',
            'latest_gwa' => 1.75,
        ]);
        $semester = AcademicSemester::create([
            'academic_record_id' => $record->id,
            'term' => '1st Semester AY 2026-2027',
            'gwa' => 1.75,
            'units_taken' => 6,
            'units_passed' => 3,
        ]);
        AcademicCourse::create([
            'academic_semester_id' => $semester->id,
            'code' => 'IT 101',
            'title' => 'Programming 1',
            'units' => 3,
            'grade' => '1.50',
            'remark' => 'Passed',
        ]);
        AcademicCourse::create([
            'academic_semester_id' => $semester->id,
            'code' => 'MATH 101',
            'title' => 'College Algebra',
            'units' => 3,
            'grade' => '5.00',
            'remark' => 'Failed',
        ]);
        DocumentSubmission::create([
            'student_id' => 'STU-1',
            'student_name' => 'Maria Santos',
            'document_type' => 'Course History',
            'original_name' => 'history.pdf',
            'stored_path' => 'submissions/history.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'status' => 'approved',
        ]);
        DocumentSubmission::create([
            'student_id' => 'STU-1',
            'student_name' => 'Maria Santos',
            'document_type' => 'COR',
            'original_name' => 'cor.pdf',
            'stored_path' => 'submissions/cor.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'status' => 'rejected',
        ]);

        $this->actingAs($staff)
            ->getJson('/api/academic-records')
            ->assertOk()
            ->assertJsonPath('data.0.approved_submissions', 1)
            ->assertJsonPath('data.0.total_submissions', 2)
            ->assertJsonPath('data.0.remarks.passed', 1)
            ->assertJsonPath('data.0.remarks.failed', 1);
    }
}
