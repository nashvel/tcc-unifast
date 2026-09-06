<?php

namespace App\Http\Controllers;

use App\Models\ContinuityResource;
use App\Models\GoogleWorkspaceConnection;
use App\Services\Continuity\GoogleWorkspaceClient;
use App\Services\Continuity\WorkspaceConnectionService;
use App\Services\Continuity\WorkbookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleWorkspaceController extends Controller
{
    public function status(GoogleWorkspaceClient $google): JsonResponse
    {
        return response()->json(['data' => [
            'configured' => $google->configured(), 'connection' => GoogleWorkspaceConnection::first(),
            'resources' => ContinuityResource::orderBy('module')->get(),
        ]]);
    }

    public function oauth(Request $request, WorkspaceConnectionService $service): JsonResponse
    {
        return response()->json(['data' => ['authorization_url' => $service->authorize($request)]]);
    }

    public function callback(Request $request, WorkspaceConnectionService $service): RedirectResponse
    {
        $service->callback($request);

        return redirect(rtrim((string) config('services.auth.frontend_url'), '/').'/app/integrations/workspace?connected=1');
    }

    public function drives(GoogleWorkspaceClient $google): JsonResponse
    {
        return response()->json(['data' => $google->api('GET', 'drive', 'drives', ['query' => ['pageSize' => 100]])['drives'] ?? []]);
    }

    public function selectDrive(Request $request, GoogleWorkspaceClient $google, WorkspaceConnectionService $service): JsonResponse
    {
        $data = $request->validate(['drive_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{5,200}$/']]);
        abort_if(ContinuityResource::whereNotNull('workbook_id')->exists(), 409, 'Existing mirrored resources must be migrated before changing Shared Drive.');
        $drive = $google->api('GET', 'drive', 'drives/'.rawurlencode($data['drive_id']));
        abort_unless(($drive['id'] ?? null) === $data['drive_id'] && is_string($drive['name'] ?? null), 422, 'Select a valid Shared Drive.');
        $connection = GoogleWorkspaceConnection::firstOrFail();
        $connection->update(['drive_id' => $drive['id'], 'drive_name' => $drive['name'], 'validated_at' => now()]);
        $service->audit($request->user(), 'workspace.drive_selected', $drive['id']);

        return $this->status($google);
    }

    public function provision(Request $request, WorkbookService $workbooks, WorkspaceConnectionService $service, GoogleWorkspaceClient $google): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        $workbooks->provision();
        $service->audit($request->user(), 'workspace.workbooks_provisioned');

        return $this->status($google);
    }

    public function disconnect(Request $request, WorkspaceConnectionService $service): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        $connection = GoogleWorkspaceConnection::first();
        $connection?->update(['access_token' => null, 'refresh_token' => null, 'status' => 'disconnected', 'enabled' => false]);
        $service->audit($request->user(), 'workspace.disconnected');

        return response()->json(['data' => ['status' => 'disconnected']]);
    }
}
