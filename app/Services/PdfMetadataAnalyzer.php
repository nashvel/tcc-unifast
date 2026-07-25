<?php

namespace App\Services;

/**
 * Score Course History / Grade Slip PDF metadata from PyMuPDF fields.
 */
class PdfMetadataAnalyzer
{
    /** @var list<string> */
    private const SUSPICIOUS_CREATOR_PRODUCER = [
        'microsoft word',
        'libreoffice',
        'openoffice',
        'canva',
        'adobe acrobat',
        'acrobat distiller',
        'adobe pdf library',
        'smallpdf',
        'ilovepdf',
        'pdf24',
        'foxit',
        'google docs',
        'wordpress',
        'chrome',
        'edge',
        'safari',
        'macos quartz',
        'preview',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   suspicious: bool,
     *   reasons: list<string>,
     *   fields: array<string, mixed>
     * }
     */
    public function analyze(array $metadata): array
    {
        if ($metadata === []) {
            return ['suspicious' => false, 'reasons' => [], 'fields' => []];
        }

        $reasons = [];
        $creator = strtolower(trim((string) ($metadata['creator'] ?? '')));
        $producer = strtolower(trim((string) ($metadata['producer'] ?? '')));
        $encryption = trim((string) ($metadata['encryption'] ?? ''));
        $isEncrypted = (bool) ($metadata['is_encrypted'] ?? false);

        if ($isEncrypted || ($encryption !== '' && strcasecmp($encryption, 'None') !== 0)) {
            $reasons[] = 'PDF is encrypted (unexpected for SIS grade documents).';
        }

        foreach (self::SUSPICIOUS_CREATOR_PRODUCER as $needle) {
            if ($creator !== '' && str_contains($creator, $needle)) {
                $reasons[] = "Suspicious creator tool: {$metadata['creator']}";
                break;
            }
        }

        foreach (self::SUSPICIOUS_CREATOR_PRODUCER as $needle) {
            if ($producer !== '' && str_contains($producer, $needle)) {
                $reasons[] = "Suspicious producer tool: {$metadata['producer']}";
                break;
            }
        }

        $creation = $this->parsePdfDate((string) ($metadata['creationDate'] ?? ''));
        $modified = $this->parsePdfDate((string) ($metadata['modDate'] ?? ''));

        if ($creation && $modified && $creation->getTimestamp() !== $modified->getTimestamp()) {
            // Allow tiny clock skew (≤ 2 minutes) between create/mod stamps.
            if (abs($creation->getTimestamp() - $modified->getTimestamp()) > 120) {
                $reasons[] = 'modDate differs from creationDate (PDF was re-saved after creation).';
            }
        }

        if ($creation && $creation->getTimestamp() > now()->addDay()->getTimestamp()) {
            $reasons[] = 'creationDate is in the future.';
        }

        return [
            'suspicious' => $reasons !== [],
            'reasons' => array_values(array_unique($reasons)),
            'fields' => [
                'format' => $metadata['format'] ?? null,
                'title' => $metadata['title'] ?? null,
                'author' => $metadata['author'] ?? null,
                'creator' => $metadata['creator'] ?? null,
                'producer' => $metadata['producer'] ?? null,
                'creationDate' => $metadata['creationDate'] ?? null,
                'modDate' => $metadata['modDate'] ?? null,
                'encryption' => $metadata['encryption'] ?? null,
                'is_encrypted' => $isEncrypted,
            ],
        ];
    }

    private function parsePdfDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // D:YYYYMMDDHHmmSS or D:YYYYMMDDHHmmSS+08'00'
        if (preg_match('/D:(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?(\d{2})?/', $value, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            $hour = (int) ($m[4] ?? 0);
            $minute = (int) ($m[5] ?? 0);
            $second = (int) ($m[6] ?? 0);

            try {
                return new \DateTimeImmutable(sprintf(
                    '%04d-%02d-%02d %02d:%02d:%02d',
                    $year,
                    $month,
                    $day,
                    $hour,
                    $minute,
                    $second,
                ));
            } catch (\Exception) {
                return null;
            }
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
