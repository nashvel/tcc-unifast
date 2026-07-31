<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class FaceDescriptorMath
{
    public const SIZE = 128;

    /**
     * @param  mixed  $raw
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
        $threshold ??= (float) config('services.identity.face_match_threshold', 0.5);

        return self::euclidean($first, $second) < $threshold;
    }

    public static function threshold(): float
    {
        return (float) config('services.identity.face_match_threshold', 0.5);
    }
}
