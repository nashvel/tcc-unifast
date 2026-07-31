<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

class MasterlistRowsImport implements ToCollection
{
    /** @var list<array<string, string>> */
    public array $rows = [];

    public function collection(Collection $rows): void
    {
        $headers = [];
        
        foreach ($rows as $row) {
            $rowArray = $row->toArray();
            
            if (empty($headers)) {
                $possibleHeaders = $this->normalizeHeaders($rowArray);
                
                // Identify the header row if it contains key columns
                if (in_array('award_number', $possibleHeaders) || in_array('student_id', $possibleHeaders) || in_array('last_name', $possibleHeaders) || in_array('full_name', $possibleHeaders)) {
                    $headers = $possibleHeaders;
                }
                continue;
            }
            
            $mapped = $this->mapRow($headers, $rowArray);
            if ($this->rowEmpty($mapped)) {
                continue;
            }
            $this->rows[] = $mapped;
        }
    }

    /**
     * @param list<string> $headers
     * @param array<int, mixed> $row
     * @return array<string, string>
     */
    public function mapRow(array $headers, array $row): array
    {
        $normalized = [];
        foreach ($headers as $index => $key) {
            if ($key !== '') {
                $normalized[$key] = trim((string) ($row[$index] ?? ''));
            }
        }

        $lastName = $normalized['last_name'] ?? '';
        $givenName = $normalized['given_name'] ?? '';
        $ext = $normalized['ext'] ?? '';
        $middleName = $normalized['middle_name'] ?? '';
        
        $fullName = $normalized['full_name'] 
            ?? $normalized['fullname'] 
            ?? $normalized['fullname_name'] 
            ?? '';

        if ($fullName === '' && ($lastName !== '' || $givenName !== '')) {
            $parts = [];
            if ($lastName !== '') {
                $parts[] = $lastName . ',';
            }
            if ($givenName !== '') {
                $parts[] = $givenName;
            }
            if ($ext !== '') {
                $parts[] = $ext . ',';
            }
            if ($middleName !== '') {
                $parts[] = $middleName;
            }
            $fullName = implode(' ', $parts);
            // Clean up weird spacing
            $fullName = trim(preg_replace('/\s+/', ' ', str_replace(', ,', ',', $fullName)), ' ,');
        }

        return [
            'student_id' => $normalized['student_id']
                ?? $normalized['studentid']
                ?? $normalized['id_number']
                ?? $normalized['award_number']
                ?? '',
            'student_number' => $normalized['student_number']
                ?? $normalized['studentno']
                ?? $normalized['student_no']
                ?? '',
            'full_name' => $fullName,
            'email' => $normalized['email']
                ?? $normalized['email_address']
                ?? '',
            'program' => $normalized['program']
                ?? $normalized['course']
                ?? $normalized['degree_program']
                ?? '',
            'year_level' => $normalized['year_level']
                ?? $normalized['year']
                ?? $normalized['yearlevel']
                ?? '',
        ];
    }

    /**
     * @param array<int, mixed> $row
     * @return list<string>
     */
    private function normalizeHeaders(array $row): array
    {
        $headers = [];
        foreach ($row as $header) {
            $key = Str::of((string) $header)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            
            $key = match ($key) {
                'name', 'student_name', 'grantee_name', 'full_name' => 'full_name',
                'student_no', 'student_number', 'school_id_number' => 'student_number',
                'student_id', 'id_number' => 'student_id',
                'email', 'email_address', 'student_email' => 'email',
                'course', 'degree_program', 'program' => 'program',
                'year', 'year_level', 'level' => 'year_level',
                'award_no', 'award_number' => 'award_number',
                'last_name' => 'last_name',
                'given_name', 'first_name' => 'given_name',
                'ext', 'extension', 'extension_name' => 'ext',
                'middle_name' => 'middle_name',
                default => $key,
            };
            
            $headers[] = $key;
        }
        return $headers;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowEmpty(array $row): bool
    {
        return trim(implode('', $row)) === '';
    }
}
