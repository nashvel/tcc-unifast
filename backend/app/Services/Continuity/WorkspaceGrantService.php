<?php

namespace App\Services\Continuity;

use App\Models\ContinuityGrant;
use Illuminate\Support\Facades\Cache;

class WorkspaceGrantService
{
    public function __construct(private GoogleWorkspaceClient $google, private WorkbookService $workbooks) {}

    public function reconcile(ContinuityGrant $grant): void
    {
        Cache::lock('continuity:grants', 120)->block(5, function () use ($grant): void {
            $grant->refresh()->load(['user.roles', 'resource']);
            if (! $grant->isEligible()) {
                if ($grant->google_permission_id) {
                    $this->google->api('DELETE', 'drive', 'files/'.$grant->resource->workbook_id.'/permissions/'.rawurlencode($grant->google_permission_id), ['query' => ['supportsAllDrives' => 'true']]);
                }
                $grant->update(['google_permission_id' => null, 'status' => 'revoked', 'access' => 'revoked']);

                return;
            }
            $role = $grant->access === 'write' ? 'writer' : 'reader';
            // Apply a downgrade before checking the effective ACL. Inherited writer
            // access still fails the subsequent check and cannot be hidden by a PATCH.
            if ($grant->google_permission_id && $role === 'reader') {
                $this->google->api('PATCH', 'drive', 'files/'.$grant->resource->workbook_id.'/permissions/'.rawurlencode($grant->google_permission_id), ['query' => ['supportsAllDrives' => 'true'], 'json' => ['role' => 'reader']]);
            }
            $this->workbooks->verifyPrivate($grant->resource);
            if ($grant->google_permission_id) {
                if ($role === 'writer') {
                    $this->google->api('PATCH', 'drive', 'files/'.$grant->resource->workbook_id.'/permissions/'.rawurlencode($grant->google_permission_id), ['query' => ['supportsAllDrives' => 'true'], 'json' => ['role' => $role]]);
                }
            } else {
                $permission = $this->google->api('POST', 'drive', 'files/'.$grant->resource->workbook_id.'/permissions', [
                    'query' => ['supportsAllDrives' => 'true', 'sendNotificationEmail' => 'false', 'fields' => 'id'],
                    'json' => ['type' => 'user', 'emailAddress' => $grant->email, 'role' => $role],
                ]);
                abort_unless(is_string($permission['id'] ?? null), 503, 'Google did not confirm the permission.');
                $grant->google_permission_id = $permission['id'];
            }
            $this->workbooks->verifyPrivate($grant->resource);
            $grant->status = 'active';
            $grant->save();
        });
    }
}
