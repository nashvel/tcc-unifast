<?php

namespace App\Support;

final class TesStipend
{
    public const AMOUNT_PER_SEMESTER = 10000.0;

    /** Grantee statuses treated as Verified for billing inclusion. */
    public const VERIFIED_STATUSES = ['verified', 'eligible'];

    public static function isVerified(?string $status): bool
    {
        return in_array(strtolower((string) $status), self::VERIFIED_STATUSES, true);
    }

    public static function exclusionReason(?string $status): string
    {
        $normalized = strtolower((string) $status);

        return match ($normalized) {
            'non_compliant', 'non-compliant', 'rejected', 'not_eligible', 'ineligible' => 'Non-compliant / rejected',
            'pending', 'pending_review', 'docs_submitted' => 'Pending verification',
            'unverified', '' => 'Unverified / pending',
            default => $status ? "Status: {$status}" : 'Not verified',
        };
    }
}
