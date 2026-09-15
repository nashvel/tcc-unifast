<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_support_inbox_does_not_create_demo_tickets(): void
    {
        $user = $this->developer();

        $this->actingAs($user)->getJson('/api/support-tickets')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_developer_can_update_status_and_add_a_persistent_reply(): void
    {
        $user = $this->developer();
        $ticket = SupportTicket::create(['ticket_id' => 'TK-001', 'title' => 'Upload problem', 'category' => 'bug', 'priority' => 'High', 'status' => 'Open']);

        $this->actingAs($user)->patchJson("/api/support-tickets/{$ticket->id}", [
            'status' => 'In Progress',
            'reply' => 'We are investigating this issue.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'In Progress')
            ->assertJsonPath('data.replies.0.message', 'We are investigating this issue.');

        $this->assertDatabaseHas('support_ticket_replies', ['support_ticket_id' => $ticket->id, 'user_id' => $user->id, 'message' => 'We are investigating this issue.']);
    }

    public function test_developer_can_close_and_reopen_a_ticket(): void
    {
        $user = $this->developer();
        $ticket = SupportTicket::create(['ticket_id' => 'TK-002', 'title' => 'Access problem', 'category' => 'general', 'priority' => 'Normal', 'status' => 'Open']);

        $this->actingAs($user)->postJson("/api/support-tickets/{$ticket->id}/close")
            ->assertOk()->assertJsonPath('data.status', 'Resolved');
        $this->actingAs($user)->postJson("/api/support-tickets/{$ticket->id}/reopen")
            ->assertOk()->assertJsonPath('data.status', 'Open');

        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket_close']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket_reopen']);
    }

    private function developer(): User
    {
        $role = Role::create(['name' => 'developer', 'description' => 'Developer', 'is_system' => true]);
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }
}
