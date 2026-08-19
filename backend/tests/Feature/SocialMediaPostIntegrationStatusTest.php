<?php

namespace Tests\Feature;

use App\Models\SocialMediaPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialMediaPostIntegrationStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.tcc_unifast_n8n.webhook_url', 'http://n8n.test/webhook/facebook');
        config()->set('services.tcc_unifast_n8n.webhook_secret', 'outbound-secret');
        config()->set('services.tcc_unifast_n8n.endpoint_secret', 'callback-secret');
        config()->set('app.url', 'http://backend:8080');
    }

    public function test_configured_integration_reports_that_the_first_post_has_not_been_created(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->getJson('/api/social-media-posts/integration-status')
            ->assertOk()
            ->assertJsonPath('data.state', 'ready_for_first_post')
            ->assertJsonPath('data.n8n_configured', true)
            ->assertJsonPath('data.n8n_reachable', false)
            ->assertJsonPath('data.counts.total', 0)
            ->assertJsonPath('data.latest_post', null);
    }

    public function test_saved_draft_is_not_reported_as_a_missing_facebook_callback(): void
    {
        $staff = $this->staff();
        $post = $this->socialPost($staff, ['status' => 'draft']);

        $this->actingAs($staff)
            ->getJson('/api/social-media-posts/integration-status')
            ->assertOk()
            ->assertJsonPath('data.state', 'draft_saved')
            ->assertJsonPath('data.latest_post.id', $post->id)
            ->assertJsonPath('data.latest_post.status', 'draft');
    }

    public function test_review_first_post_is_reported_as_waiting_for_approval(): void
    {
        $staff = $this->staff();
        $post = $this->socialPost($staff, [
            'status' => 'sent_to_n8n',
            'approval_mode' => 'approval_required',
            'n8n_status' => 'accepted',
            'n8n_request_id' => 'first-request',
        ]);

        $this->actingAs($staff)
            ->getJson('/api/social-media-posts/integration-status')
            ->assertOk()
            ->assertJsonPath('data.state', 'awaiting_approval')
            ->assertJsonPath('data.n8n_reachable', true)
            ->assertJsonPath('data.latest_post.id', $post->id);
    }

    public function test_staff_can_approve_a_reviewed_post_and_receive_the_real_facebook_callback(): void
    {
        Http::fake([
            'http://n8n.test/*' => Http::response([
                'accepted' => true,
                'status' => 'received_by_n8n',
            ], 202),
        ]);

        $staff = $this->staff();
        $post = $this->socialPost($staff, [
            'status' => 'sent_to_n8n',
            'approval_mode' => 'approval_required',
            'n8n_status' => 'accepted',
            'n8n_request_id' => 'review-request',
        ]);

        $this->actingAs($staff)
            ->postJson("/api/social-media-posts/{$post->id}/dispatch", [
                'approval_mode' => 'pre_approved',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.approval_mode', 'pre_approved')
            ->assertJsonPath('data.status', 'sent_to_n8n');

        $post->refresh();
        $requestId = $post->n8n_request_id;

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://n8n.test/webhook/facebook'
            && $request['request_id'] === $requestId
            && $request['social_post']['approval_mode'] === 'pre_approved'
            && $request['callback']['url'] === "http://backend:8080/api/integrations/n8n/social-media-posts/{$post->id}/status"
        );

        $this->postJson("/api/integrations/n8n/social-media-posts/{$post->id}/status", [
            'request_id' => $requestId,
            'status' => 'published',
            'external_post_id' => '123456_789012',
            'external_permalink' => 'https://www.facebook.com/123456/posts/789012',
            'published_at' => now()->toIso8601String(),
            'facebook_page' => [
                'id' => '123456',
                'name' => 'Official TCC UniFAST Page',
                'url' => 'https://www.facebook.com/123456',
                'followers_count' => 1000,
                'fan_count' => 900,
            ],
            'response' => ['id' => '123456_789012'],
        ], [
            'X-TCC-UniFAST-Endpoint-Key' => 'callback-secret',
        ])->assertOk();

        $this->actingAs($staff)
            ->getJson('/api/social-media-posts/integration-status')
            ->assertOk()
            ->assertJsonPath('data.state', 'connected')
            ->assertJsonPath('data.facebook_confirmed', true)
            ->assertJsonPath('data.page.name', 'Official TCC UniFAST Page');

        $this->assertDatabaseHas('social_media_posts', [
            'id' => $post->id,
            'status' => 'published',
            'approval_mode' => 'pre_approved',
            'external_post_id' => '123456_789012',
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create([
            'role' => 'staff',
            'account_status' => 'active',
        ]);
    }

    private function socialPost(User $staff, array $overrides = []): SocialMediaPost
    {
        return SocialMediaPost::create([
            'created_by' => $staff->id,
            'title' => 'Batch 1 release announcement',
            'channel' => 'facebook',
            'campaign' => 'batch_1_release',
            'status' => 'draft',
            'approval_mode' => 'approval_required',
            'message' => 'This is an approved public Batch 1 release announcement.',
            ...$overrides,
        ]);
    }
}
