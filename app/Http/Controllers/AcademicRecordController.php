<?php

namespace App\Http\Controllers;

use App\Models\AcademicRecord;
use App\Models\DocumentSubmission;
use Illuminate\Http\JsonResponse;

class AcademicRecordController extends Controller
{
    public function index(): JsonResponse
    {
        $records = AcademicRecord::query()
            ->with(['semesters.courses'])
            ->orderBy('grantee_name')
            ->get()
            ->map(fn (AcademicRecord $record) => $this->present($record, false));

        return response()->json(['data' => $records]);
    }

    public function show(AcademicRecord $record): JsonResponse
    {
        return response()->json(['data' => $this->present($record->load('semesters.courses'), true)]);
    }

    private function present(AcademicRecord $record, bool $includeSemesters): array
    {
        $approvedSubmissions = DocumentSubmission::query()
            ->where('student_id', $record->student_id)
            ->where('status', 'approved')
            ->count();
        $totalSubmissions = DocumentSubmission::query()
            ->where('student_id', $record->student_id)
            ->count();
        $courses = $record->semesters->flatMap->courses;

        $payload = [
            'id' => $record->id,
            'grantee_id' => $record->grantee_id,
            'student_id' => $record->student_id,
            'student_number' => $record->student_number,
            'name' => $record->grantee_name,
            'program' => $record->program,
            'year_level' => $record->year_level,
            'latest_gwa' => $record->latest_gwa,
            'approved_submissions' => $approvedSubmissions,
            'total_submissions' => $totalSubmissions,
            'remarks' => [
                'passed' => $courses->where('remark', 'Passed')->count(),
                'failed' => $courses->where('remark', 'Failed')->count(),
                'dropped' => $courses->where('remark', 'Dropped')->count(),
            ],
        ];

        if ($includeSemesters) {
            $payload['semesters'] = $record->semesters->map(fn ($semester) => [
                'id' => $semester->id,
                'term' => $semester->term,
                'gwa' => $semester->gwa,
                'units_taken' => $semester->units_taken,
                'units_passed' => $semester->units_passed,
                'courses' => $semester->courses->map(fn ($course) => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'units' => $course->units,
                    'grade' => $course->grade,
                    'remark' => $course->remark,
                ])->values(),
            ])->values();
        }

        return $payload;
    }
}
