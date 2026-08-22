<?php

namespace App\Services;

use App\Models\AcademicProgram;
use App\Models\Grantee;
use App\Models\User;

class MasterlistImportRowValidator
{
    /**
     * @return list<string>
     */
    public function activePrograms(): array
    {
        return AcademicProgram::query()
            ->where('is_active', true)
            ->get()
            ->flatMap(fn ($p) => [strtoupper($p->name), strtoupper($p->code)])
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string|null>
     */
    public function normalize(array $row): array
    {
        return [
            'student_id' => $row['student_id'] ?? null,
            'student_number' => $row['student_number'] ?? null,
            'full_name' => $row['full_name'] ?? null,
            'email' => $row['email'] ?? null,
            'program' => $row['program'] ?? null,
            'year_level' => $row['year_level'] ?? null,
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $seenStudentIds
     * @param  list<string>  $seenStudentNumbers
     * @param  list<string>  $validPrograms
     * @return list<string>
     */
    public function errors(array $row, array $seenStudentIds, array $seenStudentNumbers, array $validPrograms): array
    {
        $errors = [];
        foreach (['student_id', 'full_name', 'email', 'program', 'year_level'] as $field) {
            if (($row[$field] ?? '') === '') {
                $errors[] = "Missing {$field}.";
            }
        }
        if (($row['program'] ?? '') !== '') {
            $progStr = strtoupper(trim($row['program']));
            if (! in_array($progStr, $validPrograms, true)) {
                $errors[] = 'Program not recognized in the system (Caution: update system first).';
            }
        }
        if (($row['email'] ?? '') !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if (($row['student_id'] ?? '') !== '' && in_array($row['student_id'], $seenStudentIds, true)) {
            $errors[] = 'Duplicate student ID in file.';
        }
        if (($row['student_number'] ?? '') !== '' && in_array($row['student_number'], $seenStudentNumbers, true)) {
            $errors[] = 'Duplicate student number in file.';
        }
        if (($row['student_id'] ?? '') !== '' && User::query()->where('student_id', $row['student_id'])->exists()) {
            $errors[] = 'Student ID already has an account.';
        }
        if (($row['student_id'] ?? '') !== '' && Grantee::query()->where('student_id', $row['student_id'])->exists()) {
            $errors[] = 'Student ID already exists in grantees.';
        }
        if (($row['email'] ?? '') !== '' && User::query()->where('email', $row['email'])->exists()) {
            $errors[] = 'Email already has an account.';
        }

        return $errors;
    }
}
