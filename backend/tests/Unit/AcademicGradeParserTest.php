<?php

namespace Tests\Unit;

use App\Models\AcademicProgram;
use App\Models\PolicySetting;
use App\Services\AcademicGradeParser;
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

        $parsed = (new AcademicGradeParser)->parse($text);

        $this->assertSame('BSIT', $parsed['program_code']);
        $this->assertTrue($parsed['program_matched']);
        $this->assertSame(3.0, $parsed['pass_grade']);
        $this->assertSame(1.74, $parsed['semester_gpa']);
        $this->assertContains(1.4, $parsed['grades']);
        $this->assertContains(5.0, $parsed['grades']);
        $this->assertContains(3.0, $parsed['grades']);
        $this->assertSame(1, $parsed['failed_count']);
    }

    #[Test]
    public function it_ignores_blank_grades_and_does_not_count_them_as_fails(): void
    {
        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            [
                'name' => 'BSIT',
                'pass_grade' => 3.0,
                'is_active' => true,
            ],
        );

        $text = "2023-2024 1st BSIT — Year 1st\nIT 101 Intro 3 1.5 Teacher\nIT 102 Advanced 3  Teacher";

        $parsed = (new AcademicGradeParser)->parse($text);

        $this->assertSame([1.5], $parsed['grades']);
        $this->assertSame(0, $parsed['failed_count']);
    }
}
