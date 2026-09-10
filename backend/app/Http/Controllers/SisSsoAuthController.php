<?php

namespace App\Http\Controllers;

use App\Models\OidcConnection;
use App\Services\Sso\OidcAuthorizationService;
use App\Services\Sso\SsoAudit;
use App\Services\Sso\SsoException;
use App\Services\Sso\SsoIdentityReviewService;
use App\Services\Sso\SsoLoginService;
use App\Services\StudentOnboardingNavigator;
use Illuminate\Http\Request;

class SisSsoAuthController extends Controller
{
    public function status(Request $request, SsoIdentityReviewService $reviews)
    {
        return response()->json(['under_verification' => $reviews->restricted($request->user())])->header('Cache-Control', 'no-store');
    }

    public function capabilities()
    {
        $connection = OidcConnection::first();

        return response()->json(['sis' => ['available' => $connection?->state === 'all_students' && $connection->available(),
            'display_name' => $connection?->state === 'all_students' ? $connection->display_name : null,
            'maintenance_message' => null]])->header('Cache-Control', 'no-store');
    }

    public function redirect(Request $request, ?string $token = null)
    {
        $request->validate(['remember_me' => ['sometimes', 'boolean'], 'return_intent' => ['sometimes', 'in:portal']]);
        $connection = OidcConnection::first();
        if (! $connection || ! $connection->available() || ($token === null && $connection->state !== 'all_students')) {
            return response()->json(['code' => 'sso_unavailable'], 503);
        }
        try {
            return response()->json(['authorization_url' => app(OidcAuthorizationService::class)->start($connection, $request, $token)])
                ->header('Cache-Control', 'no-store');
        } catch (SsoException $error) {
            return response()->json(['code' => $error->safeCode], 503);
        }
    }

    public function callback(Request $request, SsoLoginService $login)
    {
        try {
            $result = $login->callback($request);
        } catch (SsoException $error) {
            SsoAudit::record('login_failed', ['reason' => $error->safeCode]);
            $result = in_array($error->safeCode, ['sso_not_eligible', 'sso_identity_review', 'sso_account_blocked'], true) ? $error->safeCode : 'sso_unavailable';
        } catch (\Throwable) {
            SsoAudit::record('login_failed', ['reason' => 'sso_unavailable']);
            $result = 'sso_unavailable';
        }

        return redirect(rtrim((string) config('services.auth.frontend_url'), '/').'/login?sso_result='.$result)
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function verifyTwoFactor(Request $request, SsoLoginService $login, StudentOnboardingNavigator $navigator)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        try {
            $user = $login->verifyTwoFactor($request, $data['code']);
        } catch (\Throwable) {
            return response()->json(['code' => 'sso_not_eligible', 'message' => 'Unable to verify the sign-in. Check your code or start again.'], 422);
        }
        $next = $navigator->nextStep($user);

        return response()->json(['user' => [...$user->only(['id', 'name', 'email', 'role', 'student_id', 'account_status']),
            'onboarding_next_step' => $next, 'onboarding_path' => $navigator->frontendPath($next)]]);
    }
}
