<?php

namespace App\Services\Continuity;

use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Batch;
use App\Models\BillingReport;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\KycProfile;
use App\Models\MasterlistRow;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Explicit export schemas: new database columns never become exports implicitly. */
class ModuleRegistry
{
    public function modules(): array
    {
        return [
            'masterlist' => [MasterlistRow::class, ['student_id', 'student_number', 'full_name', 'email', 'program', 'year_level', 'status']],
            'grantees' => [Grantee::class, ['student_id', 'student_number', 'full_name', 'email', 'program', 'year_level', 'status']],
            'batches' => [Batch::class, ['name', 'academic_year', 'semester', 'status', 'submission_deadline']],
            'programs' => [AcademicProgram::class, ['code', 'name', 'pass_grade', 'is_active']],
            'onboarding' => [KycProfile::class, ['student_id', 'full_name', 'program', 'contact', 'address', 'status']],
            'documents' => [DocumentSubmission::class, ['student_id', 'original_name', 'status']],
            'academic' => [AcademicRecord::class, ['student_id', 'grantee_name', 'program', 'year_level', 'latest_gwa']],
            'billing' => [BillingReport::class, ['batch_id', 'type', 'total_grantees', 'total_amount', 'stipend_per_grantee', 'generated_at']],
            'distribution' => [BillingReport::class, ['batch_id', 'type', 'total_grantees', 'total_amount', 'stipend_per_grantee', 'generated_at']],
            'support' => [SupportTicket::class, ['ticket_id', 'title', 'category', 'priority', 'status', 'description']],
        ];
    }

    public function fields(string $module): array
    {
        abort_unless(isset($this->modules()[$module]), 422, 'Unknown continuity module.');

        return $this->modules()[$module][1];
    }

    public function query(string $module): Builder
    {
        $this->fields($module);
        $query = $this->modules()[$module][0]::query();
        if ($module === 'onboarding') {
            $query->with('grantee');
        }
        if (in_array($module, ['billing', 'distribution'], true)) {
            $query->where('type', $module === 'distribution' ? BillingReport::TYPE_DISTRIBUTION : BillingReport::TYPE_CALL_FOR_BILLING);
        }

        return $query;
    }

    public function snapshot(string $module, Model $record): array
    {
        $values = [];
        foreach ($this->fields($module) as $field) {
            $value = $module === 'onboarding' && in_array($field, ['student_id', 'full_name', 'program'], true)
                ? $record->grantee?->getAttribute($field)
                : $record->getAttribute($field);
            $values[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : ($value === null ? '' : (is_bool($value) ? ($value ? '1' : '0') : (string) $value));
        }

        return $values;
    }

    public function editable(string $module): array
    {
        // Domain decisions use the existing live-system review actions.
        return match ($module) {
            'support' => ['title', 'description'],
            'onboarding' => ['contact', 'address'],
            default => [],
        };
    }
}
