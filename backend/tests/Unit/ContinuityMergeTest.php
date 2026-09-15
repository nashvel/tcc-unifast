<?php

namespace Tests\Unit;

use App\Services\Continuity\ThreeWayMerge;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ContinuityMergeTest extends TestCase
{
    public function test_independent_edits_merge_without_overwriting_either_side(): void
    {
        $result = (new ThreeWayMerge)->compare(
            ['name' => 'Alex', 'phone' => '111'],
            ['name' => 'Alex Smith', 'phone' => '111'],
            ['name' => 'Alex', 'phone' => '222'],
            ['name', 'phone'],
        );

        $this->assertSame(['name' => 'Alex Smith', 'phone' => '222'], $result['merged']);
        $this->assertSame([], $result['conflicts']);
        $this->assertSame([], $result['approvals']);
    }

    public function test_conflicts_preserve_all_three_values_and_do_not_change_local_value(): void
    {
        $result = (new ThreeWayMerge)->compare(['name' => 'A'], ['name' => 'B'], ['name' => 'C'], ['name']);

        $this->assertSame(['name' => 'B'], $result['merged']);
        $this->assertSame(['name' => ['base' => 'A', 'system' => 'B', 'mirror' => 'C']], $result['conflicts']);
    }

    public function test_sensitive_inbound_changes_wait_for_approval_even_without_conflict(): void
    {
        $result = (new ThreeWayMerge)->compare(
            ['amount' => '100.00'], ['amount' => '100.00'], ['amount' => '200.00'], ['amount'], ['amount'],
        );

        $this->assertSame(['amount' => '100.00'], $result['merged']);
        $this->assertSame(['amount' => ['base' => '100.00', 'system' => '100.00', 'mirror' => '200.00']], $result['approvals']);
    }

    public function test_equal_changes_do_not_create_conflicts_or_duplicate_approvals(): void
    {
        $result = (new ThreeWayMerge)->compare(['amount' => '100'], ['amount' => '200'], ['amount' => '200'], ['amount'], ['amount']);
        $this->assertSame(['amount' => '200'], $result['merged']);
        $this->assertSame([], $result['conflicts']);
        $this->assertSame([], $result['approvals']);
    }

    public function test_read_only_fields_cannot_be_imported(): void
    {
        $result = (new ThreeWayMerge)->compare(['id' => '1'], ['id' => '1'], ['id' => '2'], []);
        $this->assertSame(['id' => '1'], $result['merged']);
        $this->assertSame(['id'], $result['rejected_fields']);
    }

    public function test_missing_column_is_not_interpreted_as_a_clear_or_deletion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ThreeWayMerge)->compare(['name' => 'Alex'], ['name' => 'Alex'], [], ['name']);
    }

    public function test_extra_unallowlisted_columns_fail_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ThreeWayMerge)->compare(['name' => 'Alex'], ['name' => 'Alex'], ['name' => 'Alex', 'role' => 'admin'], ['name']);
    }

    public function test_null_and_empty_are_distinct_explicit_values(): void
    {
        $result = (new ThreeWayMerge)->compare(['phone' => '111'], ['phone' => null], ['phone' => ''], ['phone']);
        $this->assertSame(['phone' => ['base' => '111', 'system' => null, 'mirror' => '']], $result['conflicts']);
    }
}
