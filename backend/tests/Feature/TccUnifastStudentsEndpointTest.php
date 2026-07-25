<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TccUnifastStudentsEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for the isolated endpoint test database.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        config()->set('services.tcc_unifast_n8n.endpoint_secret', 'test-secret');
        config()->set('services.tcc_unifast_n8n.student_table', 'students');

        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('full_name');
            $table->timestamps();
        });
        DB::table('students')->insert([
            ['student_id' => 'TCC-001', 'full_name' => 'First Student', 'created_at' => now(), 'updated_at' => now()],
            ['student_id' => 'TCC-002', 'full_name' => 'Second Student', 'created_at' => now(), 'updated_at' => now()],
            ['student_id' => 'TCC-003', 'full_name' => 'Third Student', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_it_returns_cursor_paginated_students(): void
    {
        $headers = ['X-TCC-UniFAST-Endpoint-Key' => 'test-secret'];
        $this->getJson('/api/integrations/n8n/tcc-unifast/students?limit=2', $headers)
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination.has_more', true)
            ->assertJsonPath('pagination.next_cursor', 'TCC-002');
        $this->getJson('/api/integrations/n8n/tcc-unifast/students?limit=2&after_student_id=TCC-002', $headers)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', 'TCC-003')
            ->assertJsonPath('pagination.has_more', false);
    }

    public function test_it_rejects_requests_without_the_endpoint_key(): void
    {
        $this->getJson('/api/integrations/n8n/tcc-unifast/students')->assertUnauthorized();
    }
}
