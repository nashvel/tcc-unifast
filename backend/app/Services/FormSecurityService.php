<?php

namespace App\Services;

use App\Models\FormSecurityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FormSecurityService
{
    private const UUID_V4_PATTERN =
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private const XSS_PATTERNS = [
        '/<script/i',
        '/on\w+\s*=/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/<iframe/i',
        '/<object/i',
        '/<embed/i',
        '/<svg.*on\w+/i',
        '/expression\s*\(/i',
    ];

    /** SQL injection: keyword + quote/comment combination. */
    private const SQLI_PATTERNS = [
        '/(\bUNION\b.*\bSELECT\b|\bSELECT\b.*\bFROM\b)/i',
        '/(\bDROP\b|\bTRUNCATE\b|\bDELETE\b|\bINSERT\b|\bUPDATE\b).*(\bTABLE\b|\bFROM\b|\bINTO\b|\bSET\b)/i',
        "/(--|#|\/\*).*/",                          // SQL comment sequences
        "/('[^']*'|\"[^\"]*\").*(\bOR\b|\bAND\b)/i", // quote + boolean operator
        "/\b(EXEC|EXECUTE|CAST|CONVERT|CHAR|NCHAR)\s*\(/i",
        "/'\s*(OR|AND)\s*'?\d/i",                   // classic ' OR '1'='1
    ];

    /** Validate that a string is a well-formed UUID v4 before touching the DB. */
    public function validateToken(string $token): bool
    {
        return (bool) preg_match(self::UUID_V4_PATTERN, $token);
    }

    /**
     * Sanitize a single scalar value.
     * Returns the clean string.
     */
    public function sanitizeValue(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        // Strip HTML tags
        $value = strip_tags($value);
        // Encode special characters
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Trim
        return trim($value);
    }

    /**
     * Detect XSS patterns in a raw (unsanitized) value.
     */
    public function detectXss(string $value): bool
    {
        foreach (self::XSS_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect SQL injection patterns in a raw value.
     */
    public function detectSqlInjection(string $value): bool
    {
        foreach (self::SQLI_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan all string fields in a submission payload for XSS/SQLi.
     * Returns the threat type if detected, null if clean.
     *
     * @param  array<string, mixed>  $data
     */
    public function detectThreat(array $data): ?string
    {
        foreach ($data as $value) {
            $scalar = is_array($value) ? implode(' ', $value) : (string) $value;

            if ($this->detectXss($scalar)) {
                return 'xss_attempt';
            }

            if ($this->detectSqlInjection($scalar)) {
                return 'sql_injection_attempt';
            }
        }

        return null;
    }

    /**
     * Sanitize all string fields in a submission payload.
     * Rejects any single value exceeding 10,000 characters.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function sanitizeSubmission(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $clean[$key] = array_map(function (mixed $item): mixed {
                    if (! is_string($item)) {
                        return $item;
                    }
                    if (mb_strlen($item) > 10000) {
                        throw ValidationException::withMessages([
                            $key => ['Field value exceeds the maximum allowed length.'],
                        ]);
                    }

                    return $this->sanitizeValue($item);
                }, $value);
            } elseif (is_string($value)) {
                if (mb_strlen($value) > 10000) {
                    throw ValidationException::withMessages([
                        $key => ['Field value exceeds the maximum allowed length.'],
                    ]);
                }
                $clean[$key] = $this->sanitizeValue($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Log a security event.
     */
    public function log(
        string $eventType,
        Request $request,
        ?int $formId = null,
        ?array $payload = null,
    ): void {
        FormSecurityLog::create([
            'form_id' => $formId,
            'event_type' => $eventType,
            'ip_address' => $request->ip() ?? '0.0.0.0',
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'payload' => $payload,
            'user_id' => $request->user()?->id,
            'created_at' => now(),
        ]);
    }
}
