<?php

namespace App\Services\Continuity;

use InvalidArgumentException;

/**
 * Compare normalized, allowlisted business snapshots without performing writes.
 * The caller must persist review items before advancing any common baseline.
 */
final class ThreeWayMerge
{
    /**
     * @param  array<string, scalar|null>  $base
     * @param  array<string, scalar|null>  $system
     * @param  array<string, scalar|null>  $mirror
     * @param  list<string>  $editableFields
     * @param  list<string>  $approvalFields
     * @return array{merged: array, conflicts: array, approvals: array, rejected_fields: array}
     */
    public function compare(
        array $base,
        array $system,
        array $mirror,
        array $editableFields,
        array $approvalFields = [],
    ): array {
        $fields = array_keys($base);
        foreach ([$system, $mirror] as $snapshot) {
            if (count($snapshot) !== count($base) || array_diff($fields, array_keys($snapshot)) !== []) {
                throw new InvalidArgumentException('Continuity snapshot schema mismatch.');
            }
        }

        foreach ([$base, $system, $mirror] as $snapshot) {
            foreach ($snapshot as $value) {
                if ($value !== null && ! is_scalar($value)) {
                    throw new InvalidArgumentException('Continuity values must be normalized scalars.');
                }
            }
        }

        $result = ['merged' => $system, 'conflicts' => [], 'approvals' => [], 'rejected_fields' => []];

        foreach ($fields as $field) {
            if ($mirror[$field] === $base[$field] || $mirror[$field] === $system[$field]) {
                continue;
            }

            if (! in_array($field, $editableFields, true)) {
                $result['rejected_fields'][] = $field;

                continue;
            }

            $difference = ['base' => $base[$field], 'system' => $system[$field], 'mirror' => $mirror[$field]];
            if ($system[$field] !== $base[$field]) {
                $result['conflicts'][$field] = $difference;
            } elseif (in_array($field, $approvalFields, true)) {
                $result['approvals'][$field] = $difference;
            } else {
                $result['merged'][$field] = $mirror[$field];
            }
        }

        return $result;
    }
}
