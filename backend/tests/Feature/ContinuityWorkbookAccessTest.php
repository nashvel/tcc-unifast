<?php

namespace Tests\Feature;

use App\Models\ContinuityGrant;
use App\Models\ContinuityResource;
use App\Models\GoogleWorkspaceConnection;
use App\Models\User;
use App\Services\Continuity\WorkbookService;
use App\Services\Continuity\WorkspaceGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContinuityWorkbookAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function unsafePermissions(): array
    {
        return [
            'unknown individual' => [['type' => 'user', 'emailAddress' => 'unknown@example.edu', 'role' => 'reader']],
            'group membership cannot be verified' => [['type' => 'group', 'emailAddress' => 'staff@example.edu', 'role' => 'reader']],
            'public' => [['type' => 'anyone', 'role' => 'reader']],
            'domain' => [['type' => 'domain', 'role' => 'reader']],
            'missing principal' => [['type' => 'user', 'role' => 'reader']],
        ];
    }

    #[DataProvider('unsafePermissions')]
    public function test_rejects_unapproved_access_even_on_later_permission_page(array $permission): void
    {
        $resource = $this->resource();
        $this->permissions([$permission]);
        $this->expectException(HttpException::class);
        app(WorkbookService::class)->verifyPrivate($resource);
    }

    public static function staffRoles(): array
    {
        return [['reader', true], ['writer', false], ['organizer', false]];
    }

    #[DataProvider('staffRoles')]
    public function test_selected_verified_staff_cannot_exceed_approved_access(string $role, bool $allowed): void
    {
        $resource = $this->resource();
        $user = User::factory()->create(['role' => 'staff', 'account_status' => 'active', 'email' => 'staff@example.edu', 'google_id' => 'fixture-id', 'google_email_verified_at' => now()]);
        ContinuityGrant::create(['user_id' => $user->id, 'resource_id' => $resource->id, 'email' => $user->email, 'access' => 'read', 'status' => 'active']);
        $this->permissions([['type' => 'user', 'emailAddress' => $user->email, 'role' => $role, 'permissionDetails' => [['inherited' => true]]]]);
        if (! $allowed) {
            $this->expectException(HttpException::class);
        }
        app(WorkbookService::class)->verifyPrivate($resource);
        $this->addToAssertionCount(1);
    }

    public function test_student_with_old_staff_grant_no_longer_authorizes_export(): void
    {
        $resource = $this->resource();
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'active', 'email' => 'student@example.edu', 'google_id' => 'fixture-id', 'google_email_verified_at' => now()]);
        ContinuityGrant::create(['user_id' => $user->id, 'resource_id' => $resource->id, 'email' => $user->email, 'access' => 'write', 'status' => 'active']);
        $this->permissions([['type' => 'user', 'emailAddress' => $user->email, 'role' => 'writer']]);
        $this->expectException(HttpException::class);
        app(WorkbookService::class)->verifyPrivate($resource);
    }

    private function resource(): ContinuityResource
    {
        GoogleWorkspaceConnection::create(['status' => 'connected', 'email' => 'integration@example.edu', 'drive_id' => 'fixture-drive', 'access_token' => 'fixture-token', 'expires_at' => now()->addHour()]);

        return ContinuityResource::create(['module' => 'support', 'workbook_id' => 'fixture-workbook']);
    }

    public function test_read_downgrade_is_applied_before_verifying_effective_access(): void
    {
        $resource = $this->resource();
        $user = User::factory()->create(['role' => 'staff', 'account_status' => 'active', 'email' => 'staff@example.edu', 'google_id' => 'fixture-id', 'google_email_verified_at' => now()]);
        $grant = ContinuityGrant::create(['user_id' => $user->id, 'resource_id' => $resource->id, 'email' => $user->email, 'access' => 'read', 'status' => 'pending', 'google_permission_id' => 'fixture-permission']);
        $effectiveRole = 'writer';
        Http::fake(function ($request) use (&$effectiveRole, $user) {
            if ($request->method() === 'PATCH') {
                $effectiveRole = $request['role'];

                return Http::response(['id' => 'fixture-permission']);
            }
            if (str_contains($request->url(), '/permissions')) {
                return Http::response(['permissions' => [['type' => 'user', 'emailAddress' => $user->email, 'role' => $effectiveRole]]]);
            }

            return Http::response(['driveId' => 'fixture-drive', 'mimeType' => 'application/vnd.google-apps.spreadsheet', 'trashed' => false]);
        });
        app(WorkspaceGrantService::class)->reconcile($grant);
        $this->assertSame('reader', $effectiveRole);
        $this->assertSame('active', $grant->fresh()->status);
    }

    private function permissions(array $permissions): void
    {
        Http::fake([
            'www.googleapis.com/drive/v3/files/fixture-workbook/permissions*' => Http::sequence()
                ->push(['permissions' => [['type' => 'user', 'emailAddress' => 'integration@example.edu', 'role' => 'organizer']], 'nextPageToken' => 'page-two'])
                ->push(['permissions' => $permissions]),
            'www.googleapis.com/drive/v3/files/fixture-workbook*' => Http::response(['driveId' => 'fixture-drive', 'mimeType' => 'application/vnd.google-apps.spreadsheet', 'trashed' => false]),
        ]);
    }
}
