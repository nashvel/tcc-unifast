<?php

namespace Tests\Unit;

use App\Models\AcademicProgram;
use App\Models\Grantee;
use App\Models\PolicySetting;
use App\Services\AcademicGradeParser;
use App\Services\SubmissionRiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AcademicGradeParserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_parses_program_and_fails_grades_above_pass_grade(): void
    {
        PolicySetting::setValue('default_pass_grade', '3.0');
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            [
                'name' => 'Bachelor of Science in Information Technology',
                'pass_grade' => 3.0,
                'is_active' => true,
            ],
        );

        $text = <<<'TXT'
COURSE HISTORY
Student ID: 20231908
2023-2024 1st BSIT — Year 1st ACCEPTED
PATH FIT 1 Movement Competency Training 3 1.4 Instructor A
GEC 1 Understanding The Self 3 1.0 Instructor B
IT 103 Computer Programming 1 3 1.5 Instructor C
IT 102 3 5.0 Instructor D
Math 1 3 3.0 Instructor E
Total Units: 23 Units with Grade: 23 Semester GPA: 1.74
TXT;

        $parsed = (new AcademicGradeParser)->parse($text, null, null, 'course_history');

        $this->assertSame('BSIT', $parsed['program_code']);
        $this->assertTrue($parsed['program_matched']);
        $this->assertSame(3.0, $parsed['pass_grade']);
        $this->assertSame(1.74, $parsed['semester_gpa']);
        $this->assertContains(5.0, $parsed['grades']);
        $this->assertSame(1, $parsed['failed_count']);
        $this->assertSame(0, $parsed['blank_count']);
        $this->assertSame(1, $parsed['retention_count']);
    }

    #[Test]
    public function grade_slip_blanks_are_informational_only(): void
    {
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'BSIT', 'pass_grade' => 3.0, 'is_active' => true],
        );

        $courses = [
            ['code' => 'IT Elec 4', 'description' => 'Fundamentals', 'units' => '3', 'grade' => null, 'instructor' => 'Perez', 'remarks' => null],
            ['code' => 'IT Elec 5', 'description' => 'Multimedia', 'units' => '3', 'grade' => '', 'instructor' => 'Romero', 'remarks' => null],
            ['code' => 'IT Elec 6', 'description' => 'MIS', 'units' => '3', 'grade' => '1.5', 'instructor' => 'Senara', 'remarks' => 'Passed'],
        ];

        $parsed = (new AcademicGradeParser)->parse('Program: BSIT', 'BSIT', $courses, 'grade_slip');

        $this->assertSame(2, $parsed['blank_count']);
        $this->assertSame(0, $parsed['failed_count']);
        $this->assertSame(0, $parsed['dropped_count']);
        $this->assertSame(0, $parsed['retention_count']);
        $this->assertFalse($parsed['courses'][0]['counts_as_fail']);
        $this->assertSame('blank', $parsed['courses'][0]['fail_reason']);
        $this->assertNull($parsed['courses'][0]['grade']);
    }

    #[Test]
    public function course_history_blanks_without_term_are_pending_not_retention(): void
    {
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'BSIT', 'pass_grade' => 3.0, 'is_active' => true],
        );

        $courses = [
            ['code' => 'IT Elec 4', 'description' => 'Fundamentals', 'units' => '3', 'grade' => null, 'instructor' => 'Perez', 'remarks' => null],
            ['code' => 'IT Elec 5', 'description' => 'Multimedia', 'units' => '3', 'grade' => '', 'instructor' => 'Romero', 'remarks' => null],
            ['code' => 'IT Elec 6', 'description' => 'MIS', 'units' => '3', 'grade' => '1.5', 'instructor' => 'Senara', 'remarks' => 'Passed'],
        ];

        $parsed = (new AcademicGradeParser)->parse('Program: BSIT', 'BSIT', $courses, 'course_history');

        $this->assertSame(0, $parsed['blank_count']);
        $this->assertSame(2, $parsed['pending_count']);
        $this->assertSame(0, $parsed['failed_count']);
        $this->assertSame(0, $parsed['dropped_count']);
        $this->assertSame(0, $parsed['retention_count']);
        $this->assertFalse($parsed['courses'][0]['counts_as_fail']);
        $this->assertSame('pending', $parsed['courses'][0]['fail_reason']);
        $this->assertNull($parsed['courses'][0]['grade']);
        $this->assertSame('Pending', $parsed['courses'][0]['remarks']);
    }

    #[Test]
    public function it_counts_numeric_fails_and_dropped_but_not_grade_slip_blanks_for_retention(): void
    {
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'BSIT', 'pass_grade' => 3.0, 'is_active' => true],
        );

        $courses = [
            ['code' => 'A', 'description' => 'Blank', 'units' => '3', 'grade' => null, 'remarks' => null],
            ['code' => 'B', 'description' => 'Fail', 'units' => '3', 'grade' => '5.0', 'remarks' => 'Failed'],
            ['code' => 'C', 'description' => 'Drop', 'units' => '3', 'grade' => null, 'remarks' => 'Dropped'],
            ['code' => 'D', 'description' => 'Pass', 'units' => '3', 'grade' => '2.0', 'remarks' => 'Passed'],
        ];

        $parsed = (new AcademicGradeParser)->parse('BSIT — Year 1st', 'BSIT', $courses, 'grade_slip');

        $this->assertSame(1, $parsed['blank_count']);
        $this->assertSame(1, $parsed['failed_count']);
        $this->assertSame(1, $parsed['dropped_count']);
        $this->assertSame(2, $parsed['retention_count']);
        $this->assertFalse($parsed['courses'][0]['counts_as_fail']);
        $this->assertTrue($parsed['courses'][1]['counts_as_fail']);
        $this->assertTrue($parsed['courses'][2]['counts_as_fail']);
    }

    #[Test]
    public function course_history_pending_blank_plus_fail_keeps_pending_out_of_retention(): void
    {
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'BSIT', 'pass_grade' => 3.0, 'is_active' => true],
        );

        $courses = [
            ['code' => 'A', 'description' => 'Blank pending', 'units' => '3', 'grade' => null, 'remarks' => null],
            ['code' => 'B', 'description' => 'Fail', 'units' => '3', 'grade' => '5.0', 'remarks' => 'Failed'],
            ['code' => 'C', 'description' => 'Explicit drop', 'units' => '3', 'grade' => null, 'remarks' => 'Dropped'],
            ['code' => 'D', 'description' => 'Pass', 'units' => '3', 'grade' => '2.0', 'remarks' => 'Passed'],
        ];

        $parsed = (new AcademicGradeParser)->parse('BSIT — Year 1st', 'BSIT', $courses, 'course_history');

        $this->assertSame(0, $parsed['blank_count']);
        $this->assertSame(1, $parsed['pending_count']);
        $this->assertSame(1, $parsed['failed_count']);
        $this->assertSame(1, $parsed['dropped_count']);
        $this->assertSame(2, $parsed['retention_count']);
    }

    #[Test]
    public function eligibility_passes_when_only_grade_slip_blanks_exist(): void
    {
        PolicySetting::setValue('max_failed_subjects_per_semester', '1');
        PolicySetting::setValue('default_pass_grade', '3.0');
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'BSIT', 'pass_grade' => 3.0, 'is_active' => true],
        );

        $grantee = new Grantee(['student_id' => '20231908', 'full_name' => 'Nagangga, Brandon', 'program' => 'BSIT']);
        $service = $this->app->make(SubmissionRiskScoringService::class);

        $eligibility = $service->evaluateEligibility($grantee, [
            'grade_slip' => [
                'text' => 'Grade Slip Program: BSIT',
                'raw_text' => 'Grade Slip Program: BSIT',
                'courses' => [
                    ['code' => 'IT Elec 4', 'description' => 'A', 'units' => '3', 'grade' => null, 'remarks' => null],
                    ['code' => 'IT Elec 5', 'description' => 'B', 'units' => '3', 'grade' => null, 'remarks' => null],
                    ['code' => 'IT Elec 6', 'description' => 'C', 'units' => '3', 'grade' => '1.5', 'remarks' => 'Passed'],
                ],
            ],
        ]);

        $this->assertSame('pass', $eligibility['status']);
        $this->assertSame(0, $eligibility['failed_count']);
        $this->assertSame(2, $eligibility['blank_count']);
        $this->assertSame(0, $eligibility['dropped_count']);
        $this->assertSame(0, $eligibility['retention_count']);
        $this->assertStringContainsString('Blank grades ignored', (string) $eligibility['note']);
    }

    #[Test]
    public function eligibility_passes_when_course_history_current_blanks_are_pending(): void
    {
        PolicySetting::setValue('max_failed_subjects_per_semester', '2');
        PolicySetting::setValue('default_pass_grade', '3.0');
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'BSIT', 'pass_grade' => 3.0, 'is_active' => true],
        );

        $grantee = new Grantee(['student_id' => '20231908', 'full_name' => 'Nagangga, Brandon', 'program' => 'BSIT']);
        $service = $this->app->make(SubmissionRiskScoringService::class);

        $eligibility = $service->evaluateEligibility($grantee, [
            'course_history' => [
                'text' => 'Course History Program: BSIT',
                'raw_text' => 'Course History Program: BSIT',
                'courses' => [
                    ['code' => 'IT Elec 4', 'description' => 'A', 'units' => '3', 'grade' => null, 'remarks' => null],
                    ['code' => 'IT Elec 5', 'description' => 'B', 'units' => '3', 'grade' => null, 'remarks' => null],
                    ['code' => 'IT Elec 6', 'description' => 'C', 'units' => '3', 'grade' => '1.5', 'remarks' => 'Passed'],
                ],
            ],
        ]);

        $this->assertSame('pass', $eligibility['status']);
        $this->assertSame(0, $eligibility['failed_count']);
        $this->assertSame(2, $eligibility['pending_count']);
        $this->assertSame(0, $eligibility['dropped_count']);
        $this->assertSame(0, $eligibility['retention_count']);
        $this->assertSame('course_history', $eligibility['source']);
    }

    #[Test]
    public function eligibility_prefers_course_history_over_grade_slip(): void
    {
        PolicySetting::setValue('max_failed_subjects_per_semester', '2');
        PolicySetting::setValue('default_pass_grade', '3.0');
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'BSIT', 'pass_grade' => 3.0, 'is_active' => true],
        );

        $grantee = new Grantee(['student_id' => '20231908', 'full_name' => 'Julius', 'program' => 'BSIT']);
        $service = $this->app->make(SubmissionRiskScoringService::class);

        // CH has older-term blanks-as-drops → fail; GS alone would pass.
        $eligibility = $service->evaluateEligibility($grantee, [
            'grade_slip' => [
                'text' => 'Grade Slip Program: BSIT',
                'raw_text' => 'Grade Slip Program: BSIT',
                'courses' => [
                    ['code' => 'IT 201', 'description' => 'Pass', 'units' => '3', 'grade' => '1.5', 'remarks' => 'Passed'],
                    ['code' => 'IT 202', 'description' => 'Pass', 'units' => '3', 'grade' => '2.0', 'remarks' => 'Passed'],
                ],
            ],
            'course_history' => [
                'text' => 'Course History Program: BSIT',
                'raw_text' => 'Course History Program: BSIT',
                'terms' => [
                    [
                        'academic_term' => '2023-2024 1st',
                        'program_code' => 'BSIT',
                        'courses' => [
                            ['code' => 'FIL 1', 'description' => 'Blank drop', 'units' => '3', 'grade' => null, 'remarks' => null],
                            ['code' => 'FIL 2', 'description' => 'Blank drop', 'units' => '3', 'grade' => null, 'remarks' => null],
                            ['code' => 'IT 103', 'description' => 'Pass', 'units' => '3', 'grade' => '1.5', 'remarks' => 'Passed'],
                        ],
                    ],
                    [
                        'academic_term' => '2025-2026 2nd',
                        'program_code' => 'BSIT',
                        'courses' => [
                            ['code' => 'IT 201', 'description' => 'Pass', 'units' => '3', 'grade' => '1.5', 'remarks' => 'Passed'],
                        ],
                    ],
                    [
                        'academic_term' => '2025-2026 Summer',
                        'program_code' => 'BSIT',
                        'courses' => [
                            ['code' => 'IT 202', 'description' => 'Pass', 'units' => '3', 'grade' => '2.0', 'remarks' => 'Passed'],
                        ],
                    ],
                    [
                        'academic_term' => '2026-2027 1st',
                        'program_code' => 'BSIT',
                        'courses' => [
                            ['code' => 'IT 301', 'description' => 'Pass', 'units' => '3', 'grade' => '1.75', 'remarks' => 'Passed'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('fail', $eligibility['status']);
        $this->assertSame('course_history', $eligibility['source']);
        $this->assertSame(2, $eligibility['dropped_count']);
        $this->assertSame(2, $eligibility['retention_count']);
        $this->assertNotNull($eligibility['grade_slip_supplement']);
        $this->assertSame(0, $eligibility['grade_slip_supplement']['retention_count']);
    }

    #[Test]
    public function multi_program_shift_uses_per_term_pass_grades(): void
    {
        PolicySetting::setValue('max_failed_subjects_per_semester', '3');
        PolicySetting::setValue('default_pass_grade', '3.0');
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSED'],
            ['name' => 'Bachelor of Secondary Education', 'pass_grade' => 3.0, 'is_active' => true],
        );
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'Bachelor of Science in Information Technology', 'pass_grade' => 2.5, 'is_active' => true],
        );

        $terms = [
            [
                'academic_term' => '2023-2024 1st',
                'program_raw' => 'BSED Filipino',
                'program_code' => 'BSED',
                'year_level' => 'Year 1st',
                'enrollment_status' => 'ENROLLED',
                'courses' => [
                    ['code' => 'FIL 111', 'description' => 'Komunikasyon', 'units' => '3', 'grade' => '1.5', 'remarks' => 'Passed'],
                    ['code' => 'EDUC 1', 'description' => 'Foundations', 'units' => '3', 'grade' => '5.0', 'remarks' => 'Failed'],
                    ['code' => 'PATH FIT 1', 'description' => 'Movement', 'units' => '2', 'grade' => null, 'remarks' => null],
                ],
            ],
            [
                'academic_term' => '2024-2025 1st',
                'program_raw' => 'BSIT',
                'program_code' => 'BSIT',
                'year_level' => 'Year 2nd',
                'enrollment_status' => 'ENROLLED',
                'courses' => [
                    ['code' => 'IT 103', 'description' => 'Programming 1', 'units' => '3', 'grade' => '2.75', 'remarks' => null],
                    ['code' => 'IT 102', 'description' => 'Discrete', 'units' => '3', 'grade' => '1.75', 'remarks' => 'Passed'],
                ],
            ],
        ];

        $parsed = (new AcademicGradeParser)->parse(
            "COURSE HISTORY\n2023-2024 1st BSED Filipino — Year 1st ENROLLED\n2024-2025 1st BSIT — Year 2nd ENROLLED",
            'BSIT',
            null,
            'course_history',
            $terms,
        );

        $this->assertCount(2, $parsed['terms']);
        $this->assertSame('BSED', $parsed['terms'][0]['program_code']);
        $this->assertSame('BSIT', $parsed['terms'][1]['program_code']);
        // Both terms in pending window (current + 1 prior): blank → pending, not dropped.
        $this->assertSame(1, $parsed['terms'][0]['failed_count']);
        $this->assertSame(1, $parsed['terms'][0]['pending_count']);
        $this->assertSame(0, $parsed['terms'][0]['dropped_count']);
        $this->assertSame(1, $parsed['terms'][1]['failed_count']);
        $this->assertSame(0, $parsed['terms'][1]['dropped_count']);
        $this->assertSame(2, $parsed['failed_count']);
        $this->assertSame(1, $parsed['pending_count']);
        $this->assertSame(0, $parsed['dropped_count']);
        $this->assertSame(2, $parsed['retention_count']);
        $this->assertSame('BSIT', $parsed['program_code']); // latest shift

        $grantee = new Grantee(['student_id' => '20230001', 'full_name' => 'Julius Sample', 'program' => 'BSIT']);
        $eligibility = $this->app->make(SubmissionRiskScoringService::class)->evaluateEligibility($grantee, [
            'course_history' => [
                'text' => 'COURSE HISTORY',
                'raw_text' => 'COURSE HISTORY',
                'terms' => $terms,
                'courses' => [],
            ],
            'grade_slip' => [
                'text' => 'Grade Slip all pass',
                'raw_text' => 'Grade Slip all pass',
                'courses' => [
                    ['code' => 'IT 201', 'description' => 'Pass', 'units' => '3', 'grade' => '1.0', 'remarks' => 'Passed'],
                ],
            ],
        ]);

        $this->assertSame('pass', $eligibility['status']);
        $this->assertSame(2, $eligibility['retention_count']);
        $this->assertSame(1, $eligibility['pending_count']);
        $this->assertSame('course_history', $eligibility['source']);
        $this->assertCount(2, $eligibility['terms']);
    }

    #[Test]
    public function course_history_pending_window_classifies_blanks_by_term_age(): void
    {
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'BSIT', 'pass_grade' => 3.0, 'is_active' => true],
        );

        $terms = [
            [
                'academic_term' => '2024-2025 1st',
                'program_code' => 'BSIT',
                'courses' => [
                    ['code' => 'OLD 1', 'description' => 'Old blank', 'units' => '3', 'grade' => null, 'remarks' => null],
                    ['code' => 'OLD 2', 'description' => 'Old pass', 'units' => '3', 'grade' => '1.5', 'remarks' => 'Passed'],
                ],
            ],
            [
                'academic_term' => '2025-2026 2nd',
                'program_code' => 'BSIT',
                'courses' => [
                    ['code' => 'IT 201', 'description' => 'Graded', 'units' => '3', 'grade' => '1.25', 'remarks' => 'Passed'],
                    ['code' => 'IT 202', 'description' => 'Graded', 'units' => '3', 'grade' => '2.0', 'remarks' => 'Passed'],
                ],
            ],
            [
                'academic_term' => '2025-2026 Summer',
                'program_code' => 'BSIT',
                'courses' => [
                    ['code' => 'IT 250', 'description' => 'Summer blank', 'units' => '3', 'grade' => null, 'remarks' => null],
                    ['code' => 'IT 251', 'description' => 'Summer pass', 'units' => '3', 'grade' => '1.75', 'remarks' => 'Passed'],
                ],
            ],
            [
                'academic_term' => '2026-2027 1st',
                'program_code' => 'BSIT',
                'courses' => [
                    ['code' => 'IT 301', 'description' => 'Current blank', 'units' => '3', 'grade' => 'INC', 'remarks' => null],
                    ['code' => 'IT 302', 'description' => 'Current blank 2', 'units' => '3', 'grade' => null, 'remarks' => null],
                    ['code' => 'IT 303', 'description' => 'Current blank 3', 'units' => '3', 'grade' => '—', 'remarks' => null],
                ],
            ],
        ];

        $parsed = (new AcademicGradeParser)->parse(
            'COURSE HISTORY',
            'BSIT',
            null,
            'course_history',
            $terms,
        );

        $this->assertSame(0, $parsed['blank_count']);
        $this->assertSame(4, $parsed['pending_count']); // Summer 1 + current 3
        $this->assertSame(1, $parsed['dropped_count']); // 2024-2025 1st blank
        $this->assertSame(0, $parsed['failed_count']);
        $this->assertSame(1, $parsed['retention_count']);

        $byTerm = collect($parsed['terms'])->keyBy('academic_term');
        $this->assertSame('dropped', $byTerm['2024-2025 1st']['blank_mode']);
        $this->assertSame(1, $byTerm['2024-2025 1st']['dropped_count']);
        $this->assertSame('pending', $byTerm['2025-2026 2nd']['blank_mode']);
        $this->assertSame('pending', $byTerm['2025-2026 Summer']['blank_mode']);
        $this->assertSame(1, $byTerm['2025-2026 Summer']['pending_count']);
        $this->assertSame('pending', $byTerm['2026-2027 1st']['blank_mode']);
        $this->assertSame(3, $byTerm['2026-2027 1st']['pending_count']);

        // Chronological order (oldest → newest).
        $this->assertSame(
            ['2024-2025 1st', '2025-2026 2nd', '2025-2026 Summer', '2026-2027 1st'],
            array_column($parsed['terms'], 'academic_term'),
        );

        $parser = new AcademicGradeParser;
        $this->assertTrue($parser->termSortKey('2025-2026 Summer') > $parser->termSortKey('2025-2026 2nd'));
        $this->assertTrue($parser->termSortKey('2026-2027 1st') > $parser->termSortKey('2025-2026 Summer'));
    }

    #[Test]
    public function resolve_program_maps_bsed_filipino_label(): void
    {
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSED'],
            ['name' => 'Bachelor of Secondary Education', 'pass_grade' => 3.0, 'is_active' => true],
        );

        $parser = new AcademicGradeParser;
        $this->assertSame('BSED', $parser->resolveProgramCodeFromLabel('BSED Filipino'));
        $this->assertSame('BSED', $parser->extractProgramCode('2023-2024 1st BSED Filipino — Year 1st ENROLLED'));
    }

    #[Test]
    public function structured_grade_slip_rows_preserve_blank_without_counting_as_fail(): void
    {
        $summary = (new AcademicGradeParser)->summarizeCourses([
            ['code' => 'IT Elec 4', 'description' => 'Fundamentals', 'units' => '3', 'grade' => null, 'instructor' => 'Perez', 'remarks' => null],
        ], 3.0, 'grade_slip');

        $this->assertNull($summary['courses'][0]['grade']);
        $this->assertFalse($summary['courses'][0]['counts_as_fail']);
        $this->assertSame('blank', $summary['courses'][0]['fail_reason']);
        $this->assertSame(1, $summary['blank_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertSame(0, $summary['dropped_count']);
    }

    #[Test]
    public function structured_course_history_blank_row_is_classified_as_pending_by_default(): void
    {
        $summary = (new AcademicGradeParser)->summarizeCourses([
            ['code' => 'IT Elec 4', 'description' => 'Fundamentals', 'units' => '3', 'grade' => null, 'instructor' => 'Perez', 'remarks' => null],
        ], 3.0, 'course_history');

        $this->assertNull($summary['courses'][0]['grade']);
        $this->assertFalse($summary['courses'][0]['counts_as_fail']);
        $this->assertSame('pending', $summary['courses'][0]['fail_reason']);
        $this->assertSame('Pending', $summary['courses'][0]['remarks']);
        $this->assertSame(0, $summary['blank_count']);
        $this->assertSame(1, $summary['pending_count']);
        $this->assertSame(0, $summary['dropped_count']);
        $this->assertSame(0, $summary['retention_count']);
    }

    #[Test]
    public function structured_course_history_blank_row_can_be_forced_dropped(): void
    {
        $summary = (new AcademicGradeParser)->summarizeCourses([
            ['code' => 'IT Elec 4', 'description' => 'Fundamentals', 'units' => '3', 'grade' => null, 'instructor' => 'Perez', 'remarks' => null],
        ], 3.0, 'course_history', null, 'dropped');

        $this->assertSame('dropped', $summary['courses'][0]['fail_reason']);
        $this->assertTrue($summary['courses'][0]['counts_as_fail']);
        $this->assertSame(1, $summary['dropped_count']);
        $this->assertSame(1, $summary['retention_count']);
    }
}
