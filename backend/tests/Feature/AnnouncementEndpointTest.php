<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrator_can_create_list_and_update_an_announcement(): void
    {
        $user = $this->publisher();

        $created = $this->actingAs($user)->postJson('/api/announcements', [
            'title' => 'Document deadline reminder',
            'body' => 'Submit your requirements before Friday.',
            'audience_type' => 'all',
            'channels' => ['in_app', 'email'],
            'status' => 'draft',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->assertContains('in_app', $created->json('data.channels'));

        $id = $created->json('data.id');

        $this->actingAs($user)->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->actingAs($user)->putJson("/api/announcements/{$id}", [
            'title' => 'Updated deadline reminder',
            'channels' => ['sms'],
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated deadline reminder');

        $this->assertSame(['sms'], $this->actingAs($user)->getJson("/api/announcements/{$id}")->json('data.channels'));

        $this->assertDatabaseCount('announcement_channels', 1);
    }

    public function test_only_drafts_can_be_deleted_and_only_scheduled_announcements_can_be_cancelled(): void
    {
        $user = $this->publisher();
        $draft = $this->actingAs($user)->postJson('/api/announcements', ['title' => 'Draft', 'body' => 'Body', 'audience_type' => 'all', 'channels' => ['in_app'], 'status' => 'draft'])->json('data.id');
        $scheduled = $this->actingAs($user)->postJson('/api/announcements', ['title' => 'Scheduled', 'body' => 'Body', 'audience_type' => 'all', 'channels' => ['in_app'], 'status' => 'scheduled'])->json('data.id');
        $sent = $this->actingAs($user)->postJson('/api/announcements', ['title' => 'Sent', 'body' => 'Body', 'audience_type' => 'all', 'channels' => ['in_app'], 'status' => 'sent'])->json('data.id');

        $this->actingAs($user)->deleteJson("/api/announcements/{$draft}")->assertOk();
        $this->actingAs($user)->postJson("/api/announcements/{$scheduled}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->actingAs($user)->deleteJson("/api/announcements/{$sent}")->assertUnprocessable();
        $this->actingAs($user)->putJson("/api/announcements/{$sent}", ['title' => 'Changed'])->assertUnprocessable();
    }

    private function publisher(): User
    {
        $permission = Permission::create(['name' => 'publish_announcements', 'description' => 'Publish announcements', 'category' => 'Announcements']);
        $role = Role::create(['name' => 'admin', 'description' => 'Administrator', 'is_system' => true]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }
}
