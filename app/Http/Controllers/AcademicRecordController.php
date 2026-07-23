<?php

namespace App\Http\Controllers;

use App\Models\AcademicRecord;
use App\Models\DocumentSubmission;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'grantee_name');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['grantee_name', 'student_number', 'student_id', 'program', 'latest_gwa'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'grantee_name';
        }

        $query = AcademicRecord::query()->with(['semesters.courses']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('grantee_name', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('program', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy($sort, $direction)->paginate($perPage);
        $rows = collect($paginator->items())->map(fn (AcademicRecord $record) => $this->present($record, false));

        return PaginatedJson::from($paginator, $rows->values());
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
