<?php

namespace App\Http\Controllers;

use App\Models\ContinuityGrant;
use App\Models\ContinuityResource;
use App\Models\User;
use App\Services\Continuity\WorkspaceConnectionService;
use App\Services\Continuity\WorkspaceGrantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContinuityGrantController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ContinuityGrant::with('resource')->latest()->paginate(25));
    }

    public function store(Request $request, WorkspaceGrantService $grants, WorkspaceConnectionService $audit): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'resource_id' => ['required', 'integer', 'exists:continuity_resources,id'], 'access' => ['required', 'in:read,write']]);
        $user = User::findOrFail($data['user_id']);
        abort_unless($user->google_id && $user->google_email_verified_at, 422, 'This user must first link a verified Google account using Google sign-in.');
        $resource = ContinuityResource::findOrFail($data['resource_id']);
        abort_unless($resource->status === 'ready', 409, 'Prepare this workbook first.');
        $grant = ContinuityGrant::updateOrCreate(['user_id' => $user->id, 'resource_id' => $resource->id], ['email' => $user->email, 'access' => $data['access'], 'granted_by' => $request->user()->id, 'status' => 'pending']);
        $grants->reconcile($grant);
        $audit->audit($request->user(), 'workspace.access_granted', (string) $grant->id);

        return response()->json(['data' => $grant->fresh()]);
    }

    public function destroy(Request $request, ContinuityGrant $grant, WorkspaceGrantService $grants, WorkspaceConnectionService $audit): JsonResponse
    {
        $grant->update(['access' => 'revoked', 'status' => 'pending']);
        $grants->reconcile($grant);
        $audit->audit($request->user(), 'workspace.access_revoked', (string) $grant->id);

        return response()->json(['data' => $grant->fresh()]);
    }
}
