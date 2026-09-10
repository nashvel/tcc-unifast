<?php

namespace App\Http\Controllers;

use App\Models\ExternalIdentity;
use App\Models\OidcConnection;
use App\Models\SsoIdentityReview;
use App\Models\User;
use App\Services\Sso\OidcDiscoveryService;
use App\Services\Sso\SsoAudit;
use App\Services\Sso\SsoException;
use App\Services\Sso\SsoIdentityReviewService;
use App\Services\Sso\SsoPilotService;
use App\Services\Sso\SsoRolloutService;
use App\Support\PaginatedJson;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SisSsoSettingsController extends Controller
{
    public function show()
    {
        return response()->json(['data' => $this->summary(OidcConnection::first())]);
    }

    public function update(Request $request, SsoRolloutService $rollout)
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'issuer' => ['required', 'url:https', 'max:500'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => [OidcConnection::exists() ? 'sometimes' : 'required', 'string', 'max:4096'],
            'student_id_claim' => ['required', 'string', 'max:255'],
            'confirm' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $this->summary($rollout->save($data))]);
    }

    public function rollout(Request $request, SsoRolloutService $rollout)
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['disabled', 'pilot', 'all_students'])], 'confirm' => ['sometimes', 'boolean'], 'session_policy' => ['sometimes', Rule::in(['keep', 'revoke'])]]);

        return response()->json(['data' => $this->summary($rollout->rollout($data))]);
    }

    public function validateConnection(OidcDiscoveryService $discovery)
    {
        $connection = OidcConnection::firstOrFail();
        try {
            $discovery->validate($connection);
        } catch (SsoException) {
            return response()->json(['code' => 'sso_unavailable', 'message' => 'Configuration validation failed. Check the provider contract and try again.'], 422);
        }

        return response()->json(['data' => $this->summary($connection->fresh())]);
    }

    public function pilots(Request $request)
    {
        $request->validate(['search' => ['sometimes', 'string', 'max:100']]);
        $query = User::query()->where('role', 'student')
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%')->orWhere('student_id', 'like', '%'.$request->string('search').'%')))
            ->select(['users.id', 'name', 'student_id', 'email', 'account_status'])
            ->leftJoin('sso_pilot_users as pilots', 'pilots.user_id', '=', 'users.id')
            ->addSelect(['pilots.id as pilot_id', 'pilots.successes', 'pilots.failures'])->orderBy('users.id');
        $page = $query->paginate(20);

        return PaginatedJson::from($page, collect($page->items()));
    }

    public function addPilot(Request $request, SsoPilotService $pilots)
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $pilots->select(User::findOrFail($data['user_id']), $request->user());

        return response()->json(['ok' => true]);
    }

    public function removePilot(User $user, SsoPilotService $pilots)
    {
        $pilots->remove($user);

        return response()->json(['ok' => true]);
    }

    public function invite(Request $request, User $user, SsoPilotService $pilots)
    {
        return response()->json(['data' => $pilots->invite($user, $request->user())])->header('Cache-Control', 'no-store');
    }

    public function reviews()
    {
        $page = SsoIdentityReview::orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->latest('id')->paginate(20);

        return PaginatedJson::from($page, collect($page->items()));
    }

    public function decide(Request $request, SsoIdentityReview $review, SsoIdentityReviewService $reviews)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'confirm' => ['required', 'accepted'], 'notes' => ['required', 'string', 'max:2000']]);
        $reviews->decide($review, $request->user(), $data['decision'], $data['notes']);

        return response()->json(['ok' => true]);
    }

    public function disconnect(Request $request, SsoRolloutService $rollout)
    {
        $data = $request->validate(['confirm' => ['required', 'accepted'], 'session_policy' => ['required', Rule::in(['keep', 'revoke'])]]);
        $connection = $rollout->rollout([...$data, 'mode' => 'disabled']);
        ExternalIdentity::where('connection_id', $connection->id)->update(['enabled' => false]);
        SsoAudit::record('connection_unlinked', ['session_policy' => $data['session_policy']]);

        return response()->json(['data' => $this->summary($connection)]);
    }

    private function summary(?OidcConnection $connection): array
    {
        return [...($connection?->toArray() ?? ['state' => 'draft']), 'has_client_secret' => $connection !== null,
            'callback_uri' => url('/api/auth/sis/callback'), 'pending_reviews' => SsoIdentityReview::where('status', 'pending')->count()];
    }
}
