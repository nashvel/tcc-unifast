<?php

namespace App\Services;

use App\Imports\MasterlistRowsImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class MasterlistSpreadsheetParser
{
    /**
     * @return list<array<string, string>>
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv' => $this->parseCsv($file->getRealPath()),
            'xlsx', 'xls' => $this->parseWithLaravelExcel($file),
            default => throw new RuntimeException('Upload a CSV or XLSX masterlist file.'),
        };
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseWithLaravelExcel(UploadedFile $file): array
    {
        $import = new MasterlistRowsImport;
        Excel::import($import, $file);

        return $import->rows;
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $headers = null;
        $rows = [];
        $importer = new MasterlistRowsImport;
        
        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $possibleHeaders = $this->normalizeHeaders($line);
                if (in_array('award_number', $possibleHeaders) || in_array('student_id', $possibleHeaders) || in_array('last_name', $possibleHeaders) || in_array('full_name', $possibleHeaders)) {
                    $headers = $possibleHeaders;
                }
                continue;
            }
            $row = $importer->mapRow($headers, $line);
            if (!empty(array_filter($row))) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Fallback XLSX reader when Excel facade is unavailable in constrained environments.
     *
     * @return list<array<string, string>>
     */
    private function parseXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('XLSX parsing requires the PHP zip extension. Upload CSV instead.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the uploaded XLSX file.');
        }

        $sharedStrings = $this->sharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheet === false) {
            throw new RuntimeException('The XLSX file does not contain a first worksheet.');
        }

        $xml = new SimpleXMLElement($sheet);
        $lines = [];
        foreach ($xml->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $index = $this->columnIndex($reference);
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $values[$index] = trim($value);
            }
            if ($values !== []) {
                ksort($values);
                $lines[] = array_values($values);
            }
        }

        if ($lines === []) {
            return [];
        }

        $headers = null;
        $dataRows = [];
        $importer = new MasterlistRowsImport;
        
        foreach ($lines as $line) {
            if ($headers === null) {
                $possibleHeaders = $this->normalizeHeaders($line);
                if (in_array('award_number', $possibleHeaders) || in_array('student_id', $possibleHeaders) || in_array('last_name', $possibleHeaders) || in_array('full_name', $possibleHeaders)) {
                    $headers = $possibleHeaders;
                }
                continue;
            }
            
            $row = $importer->mapRow($headers, $line);
            if (!empty(array_filter($row))) {
                $dataRows[] = $row;
            }
        }

        return $dataRows;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');
        if ($contents === false) {
            return [];
        }

        $xml = new SimpleXMLElement($contents);
        $strings = [];
        foreach ($xml->si as $item) {
            if (isset($item->t)) {
                $strings[] = trim((string) $item->t);

                continue;
            }
            $parts = [];
            foreach ($item->r as $run) {
                $parts[] = (string) $run->t;
            }
            $strings[] = trim(implode('', $parts));
        }

        return $strings;
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?: 'A';
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param  list<string|null>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header): string {
            $key = Str::of(ltrim((string) $header, "\xEF\xBB\xBF"))
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->toString();

            return match ($key) {
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
        }, $headers);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $line
     * @return array<string, string>
     */
    private function combineRow(array $headers, array $line): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = trim((string) ($line[$index] ?? ''));
        }

        return $row;
    }
}
