<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MasterlistRowsImport implements ToCollection, WithHeadingRow
{
    /** @var list<array<string, string>> */
    public array $rows = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $mapped = $this->mapRow($row->toArray());
            if ($this->rowEmpty($mapped)) {
                continue;
            }
            $this->rows[] = $mapped;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function mapRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeHeader((string) $key)] = trim((string) ($value ?? ''));
        }

        return [
            'student_id' => $normalized['student_id']
                ?? $normalized['studentid']
                ?? $normalized['id_number']
                ?? '',
            'student_number' => $normalized['student_number']
                ?? $normalized['studentno']
                ?? $normalized['student_no']
                ?? '',
            'full_name' => $normalized['full_name']
                ?? $normalized['fullname']
                ?? $normalized['fullname_name']
                ?? '',
            'email' => $normalized['email']
                ?? $normalized['email_address']
                ?? '',
            'program' => $normalized['program']
                ?? $normalized['course']
                ?? '',
            'year_level' => $normalized['year_level']
                ?? $normalized['year']
                ?? $normalized['yearlevel']
                ?? '',
        ];
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowEmpty(array $row): bool
    {
        return trim(implode('', $row)) === '';
    }
}
