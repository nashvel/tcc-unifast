<?php

namespace App\Support;

/**
 * Shared headers for calls to the Python OCR microservice.
 *
 * The service authenticates with a static shared secret (X-OCR-Key). Centralised so
 * the three call sites cannot drift apart, and so an unset key degrades to "no
 * header" rather than sending an empty one.
 */
class OcrServiceRequest
{
    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $key = (string) config('services.ocr.api_key', '');

        return $key === '' ? [] : ['X-OCR-Key' => $key];
    }
}
