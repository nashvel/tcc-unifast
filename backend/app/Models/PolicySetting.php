<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PolicySetting extends Model
{
    protected $guarded = [];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $cached = Cache::remember("policy_setting:{$key}", 60, function () use ($key) {
            return static::query()->where('key', $key)->value('value');
        });

        return $cached !== null ? (string) $cached : $default;
    }

    public static function setValue(string $key, string|int|float $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value],
        );
        Cache::forget("policy_setting:{$key}");
    }

    public static function maxFailedSubjects(): int
    {
        return max(0, (int) static::getValue('max_failed_subjects_per_semester', '3'));
    }

    public static function defaultPassGrade(): float
    {
        return (float) static::getValue('default_pass_grade', '3.0');
    }

    public static function facePassMax(): float
    {
        $override = static::getValue('identity_face_pass_max');
        if ($override !== null && $override !== '') {
            return max(0.0, (float) $override);
        }

        return max(0.0, (float) config('services.identity.face_pass_max', 0.45));
    }

    public static function faceReviewMax(): float
    {
        $override = static::getValue('identity_face_review_max');
        if ($override !== null && $override !== '') {
            return max(0.0, (float) $override);
        }

        return max(0.0, (float) config('services.identity.face_review_max', 0.60));
    }

    /** Organization academic year used for soft School ID back OCR comparison (e.g. 2026-2027). */
    public static function organizationAcademicYear(): string
    {
        $value = trim((string) static::getValue('organization_academic_year', '2026-2027'));

        return $value !== '' ? $value : '2026-2027';
    }
}
