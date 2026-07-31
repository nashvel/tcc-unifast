<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SecureUpload
{
    /** @var array<string, list<string>> */
    public const IMAGE_SIGNATURES = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG\r\n\x1A\n"],
        'image/webp' => ['RIFF'],
    ];

    /** @var list<string> */
    public const PDF_SIGNATURES = ['%PDF-'];

    /**
     * @param  list<string>  $allowedMimes
     */
    public static function assertAllowedMime(UploadedFile $file, array $allowedMimes, string $field = 'file'): string
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                $field => 'Uploaded file could not be read.',
            ]);
        }

        $detected = self::detectMime($path);
        if (! in_array($detected, $allowedMimes, true) || ! self::matchesMagic($path, $detected)) {
            throw ValidationException::withMessages([
                $field => 'File type is not allowed. Upload a valid '.self::humanTypes($allowedMimes).' file.',
            ]);
        }

        return $detected;
    }

    public static function sanitizeOriginalName(?string $name, string $fallback = 'document'): string
    {
        $name = trim((string) $name);
        $name = str_replace(["\0", "\r", "\n", '"', '\\'], '', $name);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base ?? '') ?: $fallback;
        $base = trim($base, '._-') ?: $fallback;
        $base = substr($base, 0, 120);

        if ($ext !== '' && preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
            return $base.'.'.$ext;
        }

        return $base;
    }

    public static function detectMime(string $absolutePath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($absolutePath) ?: 'application/octet-stream';

        // Some environments report PDF as application/x-pdf.
        if (in_array($mime, ['application/x-pdf', 'application/acrobat'], true)) {
            return 'application/pdf';
        }

        return $mime;
    }

    private static function matchesMagic(string $absolutePath, string $mime): bool
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $head = fread($handle, 16) ?: '';
        fclose($handle);

        if ($mime === 'application/pdf') {
            return str_starts_with($head, '%PDF-');
        }

        if ($mime === 'image/jpeg') {
            return str_starts_with($head, "\xFF\xD8\xFF");
        }

        if ($mime === 'image/png') {
            return str_starts_with($head, "\x89PNG\r\n\x1A\n");
        }

        if ($mime === 'image/webp') {
            return str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP';
        }

        return false;
    }

    /**
     * @param  list<string>  $mimes
     */
    private static function humanTypes(array $mimes): string
    {
        $labels = array_map(static fn (string $mime) => match ($mime) {
            'application/pdf' => 'PDF',
            'image/jpeg' => 'JPEG',
            'image/png' => 'PNG',
            'image/webp' => 'WebP',
            default => $mime,
        }, $mimes);

        return implode('/', array_unique($labels));
    }
}
