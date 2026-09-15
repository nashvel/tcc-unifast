<?php

namespace App\Services\Continuity;

use App\Models\AuditLog;
use App\Models\GoogleWorkspaceConnection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceConnectionService
{
    public function __construct(private GoogleWorkspaceClient $google) {}

    public function authorize(Request $request): string
    {
        abort_unless($this->google->configured(), 503, 'Configure the Google Workspace client credentials first.');
        $state = Str::random(64);
        $request->session()->put('continuity_oauth', ['state' => $state, 'user_id' => $request->user()->id, 'expires_at' => now()->addMinutes(10)->timestamp]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('continuity.google.client_id'),
            'redirect_uri' => config('continuity.google.redirect_uri'),
            'response_type' => 'code', 'access_type' => 'offline', 'prompt' => 'consent',
            'scope' => implode(' ', GoogleWorkspaceClient::SCOPES), 'state' => $state,
        ]);
    }

    public function callback(Request $request): void
    {
        $pending = $request->session()->pull('continuity_oauth');
        abort_unless(is_array($pending) && is_string($request->query('state')) && hash_equals($pending['state'], $request->query('state')) && $pending['expires_at'] >= now()->timestamp, 422, 'The connection request expired. Start again.');
        $user = User::find($pending['user_id']);
        $roles = $user?->roles;
        abort_unless($user && $user->account_status === 'active' && ($roles->isNotEmpty() ? $roles->whereIn('name', ['admin', 'developer'])->isNotEmpty() : in_array($user->role, ['admin', 'developer'], true)), 403);
        $request->validate(['code' => ['required', 'string', 'max:4096']]);
        Cache::lock('continuity:connection', 90)->block(5, function () use ($request, $user): void {
            $token = $this->google->exchange(['grant_type' => 'authorization_code', 'code' => $request->query('code'), 'redirect_uri' => config('continuity.google.redirect_uri')]);
            if (! is_string($token['access_token'] ?? null) || ! is_string($token['refresh_token'] ?? null) || ! is_numeric($token['expires_in'] ?? null)) {
                throw ValidationException::withMessages(['google' => 'Google must grant offline access. Reconnect and approve all requested permissions.']);
            }
            $granted = explode(' ', (string) ($token['scope'] ?? ''));
            if (array_diff(GoogleWorkspaceClient::SCOPES, $granted)) {
                throw ValidationException::withMessages(['google' => 'Approve all requested Workspace permissions.']);
            }
            $profile = $this->google->profile($token['access_token']);
            abort_unless(is_string($profile['id'] ?? null) && filter_var($profile['email'] ?? '', FILTER_VALIDATE_EMAIL) && ($profile['verified_email'] ?? false) === true, 422, 'Google must return a verified account.');
            DB::transaction(function () use ($token, $profile, $user): void {
                $existing = GoogleWorkspaceConnection::first();
                abort_if($existing?->google_subject && $existing->google_subject !== $profile['id'], 409, 'Disconnect the existing organization account before replacing it.');
                GoogleWorkspaceConnection::updateOrCreate(['singleton' => 'organization'], [
                    'google_subject' => $profile['id'], 'email' => $profile['email'],
                    'access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'],
                    'expires_at' => now()->addSeconds((int) $token['expires_in']), 'status' => 'connected',
                    'connected_by' => $user->id, 'validated_at' => now(),
                ]);
                $this->audit($user, 'workspace.connected');
            });
        });
    }

    public function audit(User $user, string $action, ?string $target = null): void
    {
        AuditLog::create(['actor' => (string) $user->id, 'role' => $user->role, 'action' => $action, 'module' => 'continuity', 'target' => $target]);
    }
}
