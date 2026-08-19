<?php

namespace App\Services;

use App\Models\AcademicProgram;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AcademicGradeTextParser
{
    public function extractProgramCode(string $text): ?string
    {
        if (preg_match('/(.+?)\s*[—\-–]\s*Year\b/iu', $text, $m)) {
            $before = trim(preg_replace('/\s+/', ' ', $m[1]) ?? '');
            if (preg_match('/(20\d{2}\s*[-–\/]\s*(?:20\d{2}|\d{2})\s+(?:1st|2nd|3rd|Summer|Midyear))\s+(.+)$/iu', $before, $tm)) {
                $before = trim($tm[2]);
            }
            $resolved = $this->resolveProgramCodeFromLabel($before);
            if ($resolved) {
                return $resolved;
            }
        }

        if (preg_match('/\bProgram\s*[:#]?\s*([A-Z]{2,12})\b/iu', $text, $m)) {
            return $this->resolveProgramCodeFromLabel($m[1]);
        }

        if (preg_match('/\b(?:20\d{2})\s*[-–\/]\s*(?:20\d{2}|\d{2}).{0,40}?\b([A-Z]{2,8})\b/iu', $text, $m)) {
            $candidate = $this->resolveProgramCodeFromLabel($m[1]);
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

    public function resolveProgramCodeFromLabel(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $raw = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($raw === '') {
            return null;
        }

        $compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if ($compact === '') {
            return null;
        }

        /** @var Collection<int, string> $codes */
        $codes = AcademicProgram::query()->pluck('code');
        foreach ($codes as $code) {
            if (strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code) ?? '') === $compact) {
                return (string) $code;
            }
        }

        $sorted = $codes->sortByDesc(fn ($c) => strlen((string) $c))->values();
        foreach ($sorted as $code) {
            $codeCompact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code) ?? '');
            if ($codeCompact !== '' && str_starts_with($compact, $codeCompact)) {
                return (string) $code;
            }
        }

        $programs = AcademicProgram::query()->get(['code', 'name']);
        foreach ($programs as $program) {
            $nameCompact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $program->name) ?? '');
            if ($nameCompact !== '' && (str_contains($nameCompact, $compact) || str_contains($compact, $nameCompact))) {
                return (string) $program->code;
            }
        }

        if (preg_match('/^([A-Za-z]{2,12})/', $raw, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /** @return list<float> */
    public function extractNumericGrades(string $text): array
    {
        $grades = [];
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

    /** @return list<array<string, mixed>> */
    public function coursesFromLinearText(string $text): array
    {
        $courses = [];
        if (preg_match_all('/\b([1-6])\s+([1-5](?:\.\d{1,2})?)\b/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $courses[] = [
                    'code' => null,
                    'description' => null,
                    'units' => $match[1],
                    'grade' => $match[2],
                    'instructor' => null,
                    'remarks' => null,
                ];
            }
        }
        if (preg_match_all('/\b([1-6])\s+([A-Z][A-Za-zÑñ\'\-]+,\s*[A-Z][A-Za-zÑñ\'\-\s\.]+)/u', $text, $blankMatches, PREG_SET_ORDER)) {
            foreach ($blankMatches as $match) {
                $courses[] = [
                    'code' => null,
                    'description' => null,
                    'units' => $match[1],
                    'grade' => null,
                    'instructor' => trim($match[2]),
                    'remarks' => null,
                ];
            }
        }

        return $courses;
    }

    /** @return list<array<string, mixed>> */
    public function termsFromLinearText(string $text): array
    {
        $terms = [];
        $pattern = '/(20\d{2}\s*[-–\/]\s*(?:20\d{2}|\d{2})\s+(?:1st|2nd|3rd|Summer|Midyear))\s+(.+?)\s*[—\-–]\s*Year\s*(1st|2nd|3rd|4th|5th|First|Second|Third|Fourth|[1-5])(?:\s+(ENROLLED|ACCEPTED|DROPPED))?/iu';
        if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($matches as $m) {
            $programRaw = trim(preg_replace('/\s+/', ' ', $m[2]) ?? '');
            $terms[] = [
                'academic_term' => trim(preg_replace('/\s+/', ' ', $m[1]) ?? ''),
                'program_raw' => $programRaw !== '' ? $programRaw : null,
                'program_code' => $this->resolveProgramCodeFromLabel($programRaw),
                'year_level' => 'Year '.$m[3],
                'enrollment_status' => isset($m[4]) ? strtoupper($m[4]) : null,
                'courses' => [],
            ];
        }

        return $terms;
    }

    /** @return 'course_history'|'grade_slip'|null */
    public function normalizeDocumentType(?string $documentType): ?string
    {
        if ($documentType === null) {
            return null;
        }

        $normalized = Str::lower(trim(str_replace([' ', '-'], '_', $documentType)));

        return match ($normalized) {
            'course_history', 'coursehistory' => 'course_history',
            'grade_slip', 'gradeslip', 'grade_slip_pdf' => 'grade_slip',
            default => null,
        };
    }

    public function countDroppedRemarksInText(string $text): int
    {
        if (preg_match_all('/\bDropped\b|\bDRP\b/i', $text, $matches)) {
            return count($matches[0]);
        }

        return 0;
    }

    public function extractAcademicYear(string $text): ?string
    {
        if (preg_match('/\b(20\d{2})\s*[-–\/]\s*(20\d{2}|\d{2})\b/', $text, $m)) {
            $end = strlen($m[2]) === 2 ? substr($m[1], 0, 2).$m[2] : $m[2];

            return $m[1].'-'.$end;
        }

        return null;
    }

    public function extractSemesterLabel(string $text): ?string
    {
        if (preg_match('/\b(20\d{2}\s*[-–\/]\s*(?:20\d{2}|\d{2}))\s+(1st|2nd|3rd|Summer|Midyear)\b/iu', $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[0]));
        }
        if (preg_match('/\b(1st|2nd|3rd)\s+(?:Semester|Sem)\b/iu', $text, $m)) {
            return $m[0];
        }
        if (preg_match('/Academic\s*Term\s*[:#]?\s*(.+)$/imu', $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        return null;
    }

    public function extractYearLevel(string $text): ?string
    {
        if (preg_match('/Year\s*(1st|2nd|3rd|4th|5th|First|Second|Third|Fourth|[1-5])/iu', $text, $m)) {
            return 'Year '.$m[1];
        }

        return null;
    }

    public function extractSemesterGpa(string $text): ?float
    {
        if (preg_match('/Semester\s*GPA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)/iu', $text, $m)) {
            return (float) $m[1];
        }
        if (preg_match('/\bGWA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)/iu', $text, $m)) {
            return (float) $m[1];
        }
        if (preg_match('/\bGPA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)/iu', $text, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    public function looksLikeGrade(float $grade): bool
    {
        return $grade >= 1.0 && $grade <= 5.0;
    }
}
