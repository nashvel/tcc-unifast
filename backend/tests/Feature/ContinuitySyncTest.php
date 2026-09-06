<?php

namespace Tests\Feature;

use App\Jobs\RunContinuitySync;
use App\Models\ContinuityRecordState;
use App\Models\ContinuityReview;
use App\Models\ContinuityRevision;
use App\Models\GoogleWorkspaceConnection;
use App\Models\SupportTicket;
use App\Services\Continuity\ContinuitySyncService;
use App\Services\Continuity\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContinuitySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_export_fields_exist_and_queries_run(): void
    {
        $registry = app(ModuleRegistry::class);
        foreach ($registry->modules() as $module => [$model, $fields]) {
            foreach ($fields as $field) {
                if ($module === 'onboarding' && in_array($field, ['student_id', 'full_name', 'program'], true)) {
                    $this->assertTrue(Schema::hasColumn('grantees', $field));
                    continue;
                }
                $this->assertTrue(Schema::hasColumn((new $model)->getTable(), $field), $module.'.'.$field);
            }
            $this->assertIsInt($registry->query($module)->count());
        }
    }

    public function test_signed_requests_queue_once_and_reject_tampering(): void
    {
        Queue::fake();
        config(['continuity.enabled' => true, 'continuity.sync_secret' => str_repeat('s', 64)]);
        GoogleWorkspaceConnection::create(['enabled' => true, 'status' => 'connected']);
        $id = (string) Str::uuid();
        $body = json_encode(['request_id' => $id, 'source' => 'n8n']);
        $headers = $this->signedHeaders($id, $body, now()->timestamp);
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/api/internal/n8n/continuity-sync', [], [], [], $headers, $body)
                ->assertAccepted()->assertJsonPath('data.run_id', $id);
        }
        Queue::assertPushed(RunContinuitySync::class, 1);
        $this->call('POST', '/api/internal/n8n/continuity-sync', [], [], [], $headers, $body.' ')
            ->assertUnauthorized();
    }

    public function test_expired_signature_and_disabled_integration_fail_closed(): void
    {
        config(['continuity.sync_secret' => str_repeat('s', 64)]);
        $id = (string) Str::uuid();
        $body = json_encode(['request_id' => $id, 'source' => 'n8n']);
        $this->call('POST', '/api/internal/n8n/continuity-sync', [], [], [], $this->signedHeaders($id, $body, now()->subMinutes(6)->timestamp), $body)->assertUnauthorized();
        $this->call('POST', '/api/internal/n8n/continuity-sync', [], [], [], $this->signedHeaders($id, $body, now()->timestamp), $body)->assertConflict();
    }

    public function test_inbound_change_is_idempotent_and_decisions_are_held_for_review(): void
    {
        $ticket = SupportTicket::create(['ticket_id' => 'TEST-1', 'title' => 'Original title', 'category' => 'general', 'priority' => 'normal', 'status' => 'open', 'description' => 'Fictional test request']);
        $registry = app(ModuleRegistry::class);
        $base = $registry->snapshot('support', $ticket);
        $state = ContinuityRecordState::create(['module' => 'support', 'record_id' => $ticket->id, 'revision' => 1, 'base' => $base]);
        ContinuityRevision::create(['record_state_id' => $state->id, 'revision' => 1, 'snapshot' => $base]);
        $mirror = [...$base, 'title' => 'Updated request title', 'status' => 'closed'];
        $row = [$state->id, '1', ...array_values($mirror)];
        app(ContinuitySyncService::class)->import('support', $row);
        app(ContinuitySyncService::class)->import('support', $row);
        $this->assertSame('Updated request title', $ticket->fresh()->title);
        $this->assertSame($base['status'], $ticket->fresh()->status);
        $this->assertSame(1, ContinuityReview::count());
        $this->assertSame('pending', ContinuityReview::first()->status);
    }

    private function signedHeaders(string $id, string $body, int $timestamp): array
    {
        return [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CONTINUITY_TIMESTAMP' => (string) $timestamp, 'HTTP_IDEMPOTENCY_KEY' => $id,
            'HTTP_X_CONTINUITY_SIGNATURE' => hash_hmac('sha256', $timestamp."\nPOST\n/api/internal/n8n/continuity-sync\n".$body, str_repeat('s', 64)),
        ];
    }
}
