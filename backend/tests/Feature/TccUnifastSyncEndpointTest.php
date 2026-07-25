<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TccUnifastSyncEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.tcc_unifast_n8n', [
            'webhook_url' => 'https://n8n.example.test/webhook/tcc-unifast/sync',
            'webhook_header' => 'X-TCC-UniFAST-Key',
            'webhook_secret' => 'n8n-secret',
            'endpoint_secret' => 'laravel-secret',
            'timeout' => 15,
        ]);
    }

    public function test_it_forwards_a_sync_request_to_n8n(): void
    {
        Http::fake(['n8n.example.test/*' => Http::response(['ok' => true])]);

        $this->postJson('/api/integrations/n8n/tcc-unifast/sync', [
            'student_id' => 'TCC-2026-0001',
            'batch' => 'AY 2026-2027 Sem 1',
        ], ['X-TCC-UniFAST-Endpoint-Key' => 'laravel-secret'])
            ->assertAccepted()
            ->assertJsonStructure(['message', 'request_id']);

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://n8n.example.test/webhook/tcc-unifast/sync'
            && $request->hasHeader('X-TCC-UniFAST-Key', 'n8n-secret')
            && $request['student_id'] === 'TCC-2026-0001'
            && $request['source'] === 'laravel'
        );
    }

    public function test_it_rejects_an_invalid_endpoint_key(): void
    {
        Http::fake();

        $this->postJson('/api/integrations/n8n/tcc-unifast/sync')
            ->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_it_validates_the_request_payload(): void
    {
        Http::fake();

        $this->postJson('/api/integrations/n8n/tcc-unifast/sync', [
            'student_id' => str_repeat('x', 101),
        ], ['X-TCC-UniFAST-Endpoint-Key' => 'laravel-secret'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');

        Http::assertNothingSent();
    }
}
