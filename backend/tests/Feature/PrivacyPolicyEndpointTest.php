<?php

namespace Tests\Feature;

use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPolicyEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_policy_endpoints_return_their_own_active_document(): void
    {
        Term::create([
            'title' => 'Terms',
            'content' => 'Terms body',
            'version' => 'v1.0',
            'document_type' => 'terms',
            'is_active' => true,
        ]);
        Term::create([
            'title' => 'Privacy',
            'content' => 'Privacy body',
            'version' => 'v1.0',
            'document_type' => 'privacy',
            'is_active' => true,
        ]);

        $this->getJson('/api/terms/active')
            ->assertOk()
            ->assertJsonPath('data.title', 'Terms')
            ->assertJsonPath('data.document_type', 'terms');

        $this->getJson('/api/privacy-policy/active')
            ->assertOk()
            ->assertJsonPath('data.title', 'Privacy')
            ->assertJsonPath('data.document_type', 'privacy');
    }
}
