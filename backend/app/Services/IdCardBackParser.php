<?php

namespace App\Services;

class IdCardBackParser
{
    /**
     * @return array{
     *     school_year: ?string,
     *     emergency_contact_name: ?string,
     *     emergency_contact_relationship: ?string,
     *     emergency_contact_phone: ?string
     * }
     */
    public function parseBackFields(string $text): array
    {
        $raw = trim($text);
        if ($raw === '') {
            return [
                'school_year' => null,
                'emergency_contact_name' => null,
                'emergency_contact_relationship' => null,
                'emergency_contact_phone' => null,
            ];
        }

        $schoolYear = null;
        if (preg_match(
            '/\b(?:S\.?\s*Y\.?|SY|School\s*Year|Academic\s*Year|A\.?\s*Y\.?)\s*[:\-]?\s*((?:20\d{2})\s*[-–\/]\s*(?:20)?\d{2,4})/i',
            $raw,
            $syMatch,
        )) {
            $schoolYear = preg_replace('/\s+/', '', str_replace(['–', '/'], '-', $syMatch[1]));
        } elseif (preg_match('/\b(20\d{2}\s*[-–\/]\s*20\d{2})\b/', $raw, $ayMatch)) {
            $schoolYear = preg_replace('/\s+/', '', str_replace(['–', '/'], '-', $ayMatch[1]));
        }

        $relationship = null;
        if (preg_match(
            '/\b(?:Relationship|Rel\.?)\s*[:\-]?\s*(Mother|Father|Guardian|Parent|Spouse|Sibling|Brother|Sister|Aunt|Uncle|Grandmother|Grandfather|Relative|Other)\b/i',
            $raw,
            $relMatch,
        )) {
            $relationship = ucfirst(strtolower($relMatch[1]));
        } elseif (preg_match('/\b(Mother|Father|Guardian|Parent|Spouse)\b/i', $raw, $relLoose)) {
            $relationship = ucfirst(strtolower($relLoose[1]));
        }

        $phone = null;
        if (preg_match(
            '/(?:\+?63|0)?[\s\-.]?(?:9\d{2}|2\d{2}|\d{3})[\s\-.]?\d{3}[\s\-.]?\d{4}\b/',
            $raw,
            $phoneMatch,
        )) {
            $phone = preg_replace('/[^\d+]/', '', $phoneMatch[0]);
        }

        $contactName = null;
        if (preg_match(
            '/(?:Emergency\s*(?:Contact|Person)|In\s*case\s*of\s*emergency|Contact\s*Person|Guardian)\s*[:\-]?\s*([A-Za-z][A-Za-z .\-]{2,60})/i',
            $raw,
            $nameMatch,
        )) {
            $candidate = trim(preg_replace('/\s+/', ' ', $nameMatch[1]));
            $candidate = preg_replace('/\b(?:Mother|Father|Guardian|Parent|Spouse|Relationship|Rel|Contact|Phone|Mobile|Tel)\b.*$/i', '', $candidate);
            $candidate = trim((string) $candidate, " \t:-");
            if ($candidate !== '' && str_word_count($candidate) >= 1) {
                $contactName = $candidate;
            }
        }

        return [
            'school_year' => $this->normalizeSchoolYear($schoolYear),
            'emergency_contact_name' => $contactName,
            'emergency_contact_relationship' => $relationship,
            'emergency_contact_phone' => $phone,
        ];
    }

    public function normalizeSchoolYear(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', str_replace(['–', '—', '/'], '-', $raw));
        $normalized = (string) preg_replace(
            '/^(?:S\.?Y\.?|SY|A\.?Y\.?|ACADEMICYEAR|SCHOOLYEAR)[:\-]*/i',
            '',
            (string) $normalized,
        );

        if (preg_match('/^(20\d{2})-(\d{2}|\d{4})$/', $normalized, $match)) {
            $start = $match[1];
            $end = $match[2];
            if (strlen($end) === 2) {
                $end = substr($start, 0, 2).$end;
            }

            return "{$start}-{$end}";
        }

        return $normalized !== '' ? $normalized : null;
    }
}
