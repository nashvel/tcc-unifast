<?php

namespace Tests\Unit;

use App\Services\MasterlistTruthService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MasterlistTruthServiceTest extends TestCase
{
    #[Test]
    public function it_matches_names_case_insensitively_with_collapsed_spaces(): void
    {
        $truth = new MasterlistTruthService;

        $this->assertTrue($truth->valuesMatch('BRANDON NAGANGGA', 'Brandon Nagangga'));
        $this->assertTrue($truth->valuesMatch('  Brandon   Nagangga ', 'BRANDON NAGANGGA'));
        $this->assertTrue($truth->valuesMatch('Brandon-Nagangga', 'Brandon Nagangga'));
        $this->assertFalse($truth->valuesMatch('Brandon Cruz', 'Brandon Nagangga'));
    }

    #[Test]
    public function it_matches_split_name_parts_case_insensitively(): void
    {
        $truth = new MasterlistTruthService;

        $this->assertTrue($truth->namesMatch('Brandon', null, 'Nagangga', 'Brandon Nagangga'));
        $this->assertTrue($truth->namesMatch('BRANDON', null, 'nagangga', 'Brandon Nagangga'));
        $this->assertTrue($truth->namesMatch('brandon', '', 'nagangga', 'BRANDON NAGANGGA'));
        $this->assertTrue($truth->namesMatch('Brandon', 'X', 'Nagangga', 'Brandon X Nagangga'));
        $this->assertTrue($truth->namesMatch('Brandon', null, 'Nagangga', 'Brandon X Nagangga'));
        $this->assertTrue($truth->namesMatch('Brandon', null, 'Nagangga', 'Nagangga, Brandon'));
        $this->assertTrue($truth->namesMatch('Brandon', 'X', 'Nagangga', 'Nagangga, Brandon X'));
        $this->assertTrue($truth->namesMatch('Maria', null, 'Santos', 'Santos, Maria'));
        $this->assertTrue($truth->namesMatch('Maria', 'Clara', 'Dela Cruz', 'Dela Cruz, Maria Clara'));
        $this->assertTrue($truth->namesMatch('Maria', 'Clara', 'Dela Cruz', 'Maria Clara Dela Cruz'));
        $this->assertTrue($truth->namesMatch('Maria', 'Clara', 'Santos', 'Maria Santos'));

        $this->assertFalse($truth->namesMatch('Brandon', 'Y', 'Nagangga', 'Brandon X Nagangga'));
        $this->assertFalse($truth->namesMatch('Brandon', 'Wrong', 'Nagangga', 'Brandon X Nagangga'));
        $this->assertFalse($truth->namesMatch('Brandon', null, 'Santos', 'Brandon Nagangga'));
        $this->assertFalse($truth->namesMatch('Brandon', null, 'Cruz', 'BRANDON NAGANGGA'));
        $this->assertFalse($truth->namesMatch('Maria', null, 'Nagangga', 'Brandon Nagangga'));
    }

    #[Test]
    public function it_matches_student_ids_case_insensitively_and_ignores_separators(): void
    {
        $truth = new MasterlistTruthService;

        $this->assertTrue($truth->studentIdsMatch('STU-88', 'stu-88'));
        $this->assertTrue($truth->studentIdsMatch('STU88', 'stu-88'));
        $this->assertTrue($truth->studentIdsMatch('  STU-88  ', 'STU-88'));
        $this->assertFalse($truth->studentIdsMatch('STU-87', 'STU-88'));
    }

    #[Test]
    public function it_matches_program_code_to_name_via_academic_programs(): void
    {
        $truth = new MasterlistTruthService;
        $programs = [
            ['code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology'],
        ];

        $this->assertTrue($truth->programsMatch('BSIT', 'Bachelor of Science in Information Technology', $programs));
        $this->assertTrue($truth->programsMatch('bsit', 'BSIT', $programs));
        $this->assertFalse($truth->programsMatch('BSBA', 'BSIT', $programs));
    }

    #[Test]
    public function it_parses_western_and_comma_name_orders(): void
    {
        $truth = new MasterlistTruthService;

        $western = $truth->parseNameParts('Brandon X Nagangga');
        $this->assertSame('brandon', $western['first']);
        $this->assertSame('x', $western['middle']);
        $this->assertSame('nagangga', $western['last']);

        $comma = $truth->parseNameParts('Nagangga, Brandon X');
        $this->assertSame('brandon', $comma['first']);
        $this->assertSame('x', $comma['middle']);
        $this->assertSame('nagangga', $comma['last']);
    }
}
