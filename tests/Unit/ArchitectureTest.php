<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ArchitectureTest extends TestCase
{
    public function test_the_application_uses_the_expected_php_runtime(): void
    {
        $this->assertGreaterThanOrEqual(80300, PHP_VERSION_ID);
    }
}
