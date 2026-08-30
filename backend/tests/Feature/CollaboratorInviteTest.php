<?php

namespace Tests\Feature;

use App\Mail\StaffInviteMail;
use App\Models\ActivationToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 7 — no shared literal credentials.
 *
 * Invited collaborators used to be created with bcrypt('password'), so anyone able
 * to invite could mint a developer account whose credential was publicly guessable.
 */
class CollaboratorInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_collaborator_gets_no_usable_password(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'developer', 'account_status' => 'active']);

        $this->actingAs($admin)
            ->postJson('/api/collaborators/invite', [
                'email' => 'new.staff@unifast.gov.ph',
                'role' => 'staff',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $invited = User::query()->where('email', 'new.staff@unifast.gov.ph')->firstOrFail();

        $this->assertFalse(Hash::check('password', $invited->password));
        $this->assertFalse(Hash::check('', $invited->password));
        Mail::assertSent(StaffInviteMail::class);
        $this->assertDatabaseHas('activation_tokens', ['user_id' => $invited->id, 'used_at' => null]);
    }

    public function test_invited_developer_also_gets_no_usable_password(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'developer', 'account_status' => 'active']);

        $this->actingAs($admin)
            ->postJson('/api/collaborators/invite', [
                'email' => 'second.dev@unifast.gov.ph',
                'role' => 'developer',
            ])
            ->assertCreated();

        $invited = User::query()->where('email', 'second.dev@unifast.gov.ph')->firstOrFail();
        $this->assertFalse(Hash::check('password', $invited->password));
    }

    public function test_staff_can_set_their_password_from_the_invite_link(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'account_status' => 'pending',
            'password' => Hash::make(Str::random(64)),
        ]);
        $plainToken = Str::random(48);
        ActivationToken::create([
            'user_id' => $staff->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours(24),
        ]);

        $this->getJson("/api/staff-activation/{$plainToken}")
            ->assertOk()
            ->assertJsonPath('data.role', 'staff');

        $this->postJson("/api/staff-activation/{$plainToken}", [
            'password' => 'Str0ng-Staff-Pass!',
            'password_confirmation' => 'Str0ng-Staff-Pass!',
        ])
            ->assertOk()
            ->assertJsonPath('user.account_status', 'active');

        $staff = $staff->fresh();
        $this->assertTrue(Hash::check('Str0ng-Staff-Pass!', $staff->password));
        $this->assertNotNull(ActivationToken::query()->firstOrFail()->used_at);
    }

    /**
     * The staff endpoint sets a password directly, so a student token reaching it
     * would bypass biometric verification entirely.
     */
    public function test_student_token_cannot_use_the_staff_activation_endpoint(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'account_status' => 'unverified',
            'password' => Hash::make(Str::random(64)),
        ]);
        $plainToken = Str::random(48);
        ActivationToken::create([
            'user_id' => $student->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours(24),
        ]);

        $this->postJson("/api/staff-activation/{$plainToken}", [
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertUnprocessable();

        $this->assertFalse(Hash::check('Str0ng-Passw0rd!', $student->fresh()->password));
        $this->assertSame('unverified', $student->fresh()->account_status);
    }
}
