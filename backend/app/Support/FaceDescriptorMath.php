<?php

namespace App\Support;

use App\Models\PolicySetting;
use Illuminate\Validation\ValidationException;

class FaceDescriptorMath
{
    public const SIZE = 128;

    public const ZONE_CONFIDENT = 'confident';

    public const ZONE_UNCERTAIN = 'uncertain';

    public const ZONE_MISMATCH = 'mismatch';

    /**
     * @return list<float>
     */
    public static function normalize(mixed $raw, string $field = 'face_descriptor'): array
    {
        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                $field => 'Face descriptor must be a 128-dimension numeric array.',
            ]);
        }

        if (count($raw) !== self::SIZE) {
            throw ValidationException::withMessages([
                $field => 'Face descriptor must contain exactly 128 values.',
            ]);
        }

        $descriptor = [];
        $sumSquares = 0.0;
        foreach ($raw as $index => $value) {
            if (! is_numeric($value)) {
                throw ValidationException::withMessages([
                    $field => 'Face descriptor contains a non-numeric value.',
                ]);
            }
            $float = (float) $value;
            if (! is_finite($float)) {
                throw ValidationException::withMessages([
                    $field => 'Face descriptor contains a non-finite value.',
                ]);
            }
            $descriptor[] = $float;
            $sumSquares += $float * $float;
        }

        // face-api.js descriptors are L2-normalized (~1.0). Reject empty/garbage vectors.
        $norm = sqrt($sumSquares);
        if ($norm < 0.1 || $norm > 2.5) {
            throw ValidationException::withMessages([
                $field => 'Face descriptor failed integrity checks. Retake the capture.',
            ]);
        }

        return $descriptor;
    }

    /**
     * @param  list<float>  $first
     * @param  list<float>  $second
     */
    public static function euclidean(array $first, array $second): float
    {
        if (count($first) !== self::SIZE || count($second) !== self::SIZE) {
            throw new \InvalidArgumentException('Face descriptors must be 128-dimensional.');
        }

        $total = 0.0;
        for ($i = 0; $i < self::SIZE; $i++) {
            $delta = $first[$i] - $second[$i];
            $total += $delta * $delta;
        }

        return sqrt($total);
    }

    /**
     * @param  list<float>  $first
     * @param  list<float>  $second
     */
    public static function matches(array $first, array $second, ?float $threshold = null): bool
    {
        $threshold ??= self::threshold();

        return self::euclidean($first, $second) < $threshold;
    }

    /** Vault / legacy single threshold (pass if distance < threshold). */
    public static function threshold(): float
    {
        return (float) config('services.identity.face_match_threshold', 0.5);
    }

    /** Onboarding: activate when distance < pass_max. */
    public static function passMax(): float
    {
        return PolicySetting::facePassMax();
    }

    /** Onboarding: hard mismatch (retry) when distance >= review_max. */
    public static function reviewMax(): float
    {
        return PolicySetting::faceReviewMax();
    }

    /**
     * Three-tier onboarding classification.
     *
     * @return self::ZONE_*
     */
    public static function classify(float $distance, ?float $passMax = null, ?float $reviewMax = null): string
    {
        $passMax ??= self::passMax();
        $reviewMax ??= self::reviewMax();

        if ($reviewMax < $passMax) {
            $reviewMax = $passMax;
        }

        if ($distance < $passMax) {
            return self::ZONE_CONFIDENT;
        }

        if ($distance < $reviewMax) {
            return self::ZONE_UNCERTAIN;
        }

        return self::ZONE_MISMATCH;
    }

    public static function isConfident(float $distance, ?float $passMax = null, ?float $reviewMax = null): bool
    {
        return self::classify($distance, $passMax, $reviewMax) === self::ZONE_CONFIDENT;
    }

    public static function isUncertain(float $distance, ?float $passMax = null, ?float $reviewMax = null): bool
    {
        return self::classify($distance, $passMax, $reviewMax) === self::ZONE_UNCERTAIN;
    }

    public static function isMismatch(float $distance, ?float $passMax = null, ?float $reviewMax = null): bool
    {
        return self::classify($distance, $passMax, $reviewMax) === self::ZONE_MISMATCH;
    }
}
