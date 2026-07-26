<?php

namespace App\Services;

use App\Models\AcademicProgram;
use App\Models\PolicySetting;
use Illuminate\Support\Str;

class AcademicGradeParser
{
    /**
     * Parse Course History / Grade Slip OCR text for program + numeric grades.
     * Blank grades are ignored. Pass rule: fail if grade > pass_grade (e.g. BSIT 3.0).
     *
     * @return array{
     *   program_code: ?string,
     *   academic_year: ?string,
     *   semester_label: ?string,
     *   year_level: ?string,
     *   semester_gpa: ?float,
     *   grades: list<float>,
     *   failed_count: int,
     *   pass_grade: float,
     *   program_matched: bool
     * }
     */
    public function parse(string $text, ?string $fallbackProgram = null): array
    {
        $programCode = $this->extractProgramCode($text) ?: $this->normalizeProgramCode($fallbackProgram);
        $program = $programCode
            ? AcademicProgram::query()->whereRaw('UPPER(code) = ?', [Str::upper($programCode)])->first()
            : null;

        $passGrade = $program?->pass_grade ?? PolicySetting::defaultPassGrade();
        $grades = $this->extractNumericGrades($text);
        $failed = 0;
        foreach ($grades as $grade) {
            if ($grade > $passGrade) {
                $failed++;
            }
        }

        return [
            'program_code' => $program?->code ?? $programCode,
            'academic_year' => $this->extractAcademicYear($text),
            'semester_label' => $this->extractSemesterLabel($text),
            'year_level' => $this->extractYearLevel($text),
            'semester_gpa' => $this->extractSemesterGpa($text),
            'grades' => $grades,
            'failed_count' => $failed,
            'pass_grade' => (float) $passGrade,
            'program_matched' => $program !== null,
        ];
    }

    public function extractProgramCode(string $text): ?string
    {
        // Header bar: "2023-2024 1st BSIT — Year 1st" or "BSIT - Year 1"
        if (preg_match('/\b([A-Z]{2,8}(?:\s?[A-Z0-9]{1,6})?)\s*[—\-–]\s*Year\b/iu', $text, $m)) {
            return $this->normalizeProgramCode($m[1]);
        }

        if (preg_match('/\b(?:20\d{2})\s*[-–\/]\s*(?:20\d{2}|\d{2}).{0,40}?\b([A-Z]{2,8})\b/iu', $text, $m)) {
            $candidate = $this->normalizeProgramCode($m[1]);
            if ($candidate && ! in_array(Str::upper($candidate), ['PATH', 'GEC', 'MATH', 'ROTC', 'CWTS', 'NSTP'], true)) {
                return $candidate;
            }
        }

        $known = AcademicProgram::query()->pluck('code');
        foreach ($known as $code) {
            if (preg_match('/\b'.preg_quote((string) $code, '/').'\b/i', $text)) {
                return (string) $code;
            }
        }

        return null;
    }

    /**
     * @return list<float>
     */
    public function extractNumericGrades(string $text): array
    {
        $grades = [];

        // Prefer row-style: units (1–6) then grade (1.0–5.0), blank RE GRADE skipped naturally.
        if (preg_match_all('/\b([1-6])\s+([1-5](?:\.\d{1,2})?)\b/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $grade = (float) $match[2];
                if ($this->looksLikeGrade($grade)) {
                    $grades[] = $grade;
                }
            }
        }

        if ($grades !== []) {
            return $grades;
        }

        // Fallback: standalone decimal grades; exclude Semester GPA / GWA lines.
        $stripped = preg_replace('/(?:Semester\s*GPA|GWA)\s*[:#]?\s*[0-9]+(?:\.[0-9]+)?/iu', '', $text) ?? $text;
        if (preg_match_all('/\b([1-5]\.\d{1,2})\b/', $stripped, $decimalMatches)) {
            foreach ($decimalMatches[1] as $raw) {
                $grade = (float) $raw;
                if ($this->looksLikeGrade($grade)) {
                    $grades[] = $grade;
                }
            }
        }

        return $grades;
    }

    private function looksLikeGrade(float $grade): bool
    {
        return $grade >= 1.0 && $grade <= 5.0;
    }

    private function extractAcademicYear(string $text): ?string
    {
        if (preg_match('/\b(20\d{2})\s*[-–\/]\s*(20\d{2}|\d{2})\b/', $text, $m)) {
            $end = strlen($m[2]) === 2 ? substr($m[1], 0, 2).$m[2] : $m[2];

            return $m[1].'-'.$end;
        }

        return null;
    }

    private function extractSemesterLabel(string $text): ?string
    {
        if (preg_match('/\b(20\d{2}\s*[-–\/]\s*(?:20\d{2}|\d{2}))\s+(1st|2nd|3rd|Summer|Midyear)\b/iu', $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[0]));
        }

        if (preg_match('/\b(1st|2nd|3rd)\s+(?:Semester|Sem)\b/iu', $text, $m)) {
            return $m[0];
        }

        return null;
    }

    private function extractYearLevel(string $text): ?string
    {
        if (preg_match('/Year\s*(1st|2nd|3rd|4th|5th|First|Second|Third|Fourth|[1-5])/iu', $text, $m)) {
            return 'Year '.$m[1];
        }

        return null;
    }

    private function extractSemesterGpa(string $text): ?float
    {
        if (preg_match('/Semester\s*GPA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)/iu', $text, $m)) {
            return (float) $m[1];
        }

        if (preg_match('/\bGWA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)/iu', $text, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private function normalizeProgramCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = strtoupper(trim(preg_replace('/\s+/', '', $value) ?? ''));

        return $clean !== '' ? $clean : null;
    }
}
