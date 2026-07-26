<?php

namespace App\Services;

use App\Models\Grantee;
use App\Models\KycProfile;
use App\Models\MasterlistRow;
use Illuminate\Support\Str;

class MasterlistTruthService
{
    /**
     * @return array{full_name: string, student_id: string, program: string, year_level: string|null}
     */
    public function forGrantee(Grantee $grantee): array
    {
        $row = MasterlistRow::query()
            ->where('student_id', $grantee->student_id)
            ->where('status', 'valid')
            ->whereHas('import', function ($query) use ($grantee): void {
                $query->where('status', 'imported');
                if ($grantee->batch_id) {
                    $query->where('batch_id', $grantee->batch_id);
                }
            })
            ->latest('id')
            ->first();

        if ($row) {
            return [
                'full_name' => (string) $row->full_name,
                'student_id' => (string) $row->student_id,
                'program' => (string) $row->program,
                'year_level' => $row->year_level !== null ? (string) $row->year_level : null,
            ];
        }

        return [
            'full_name' => (string) $grantee->full_name,
            'student_id' => (string) $grantee->student_id,
            'program' => (string) $grantee->program,
            'year_level' => $grantee->year_level !== null ? (string) $grantee->year_level : null,
        ];
    }

    /**
     * @return array{full_name: string, student_id: string}
     */
    public function expectedIdentity(Grantee $grantee, ?KycProfile $profile = null): array
    {
        $truth = $this->forGrantee($grantee);
        $profile ??= $grantee->kycProfile;

        return [
            'full_name' => (string) ($profile?->full_name ?: $truth['full_name']),
            'student_id' => (string) ($profile?->student_id ?: $truth['student_id']),
        ];
    }

    public function key(mixed $value): string
    {
        return Str::of((string) $value)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
    }
}
