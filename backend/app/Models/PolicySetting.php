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
        return max(0, (int) static::getValue('max_failed_subjects_per_semester', '1'));
    }

    public static function defaultPassGrade(): float
    {
        return (float) static::getValue('default_pass_grade', '3.0');
    }
}
