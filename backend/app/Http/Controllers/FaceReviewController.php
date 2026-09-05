<?php

namespace App\Http\Controllers;

use App\Mail\IdentityApprovedSetPasswordMail;
use App\Mail\IdentityRejectedRetryMail;
use App\Models\AuditLog;
use App\Models\GranteeIdentityProfile;
use App\Services\ActivationTokenIssuer;
use App\Services\AuthTokenService;
use App\Support\FaceDescriptorMath;
use App\Support\VaultFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class FaceReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $search = trim((string) $request->query('search', ''));

        $query = GranteeIdentityProfile::query()
            ->with(['grantee.batch', 'user'])
            ->where('status', 'pending_face_review')
            ->orderByDesc('updated_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->whereHas('grantee', function ($grantee) use ($search): void {
                        $grantee
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%")
                            ->orWhere('student_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($user) use ($search): void {
                        $user->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (GranteeIdentityProfile $profile) => $this->present($profile))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(GranteeIdentityProfile $faceReview): JsonResponse
    {
        abort_unless($faceReview->status === 'pending_face_review', 404);
        $faceReview->loadMissing(['grantee.batch', 'user']);

        return response()->json(['data' => $this->present($faceReview)]);
    }

    public function approve(Request $request, GranteeIdentityProfile $faceReview): JsonResponse
    {
        abort_unless($faceReview->status === 'pending_face_review', 404);
        $faceReview->loadMissing(['grantee', 'user']);

        $user = $faceReview->user;
        $grantee = $faceReview->grantee;
        if (! $user || ! $grantee) {
            throw ValidationException::withMessages([
                'profile' => 'Face review is missing linked student or grantee.',
            ]);
        }

        $this->purgeChallengeStills($faceReview);

        $faceReview->update([
            'status' => 'completed',
            'onboarding_completed_at' => now(),
        ]);
        // Identity proven, but the student still has no password — see
        // OnboardingCredentialController. 'active' means verified AND credentialed.
        $user->forceFill(['account_status' => 'identity_verified'])->save();
        $grantee->update(['status' => 'verified']);

        // Staff review can take days, the student holds no password, and their
        // original link has probably expired. Without a fresh link they would be
        // permanently locked out, so approval must carry its own way back in.
        $link = app(ActivationTokenIssuer::class)->issueLinkFor($user);
        $mailed = true;
        try {
            Mail::to($user->email, $user->name)->send(new IdentityApprovedSetPasswordMail($user, $link['url']));
        } catch (\Throwable $exception) {
            report($exception);
            $mailed = false;
        }

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => ucfirst((string) $request->user()->role),
            'action' => 'onboarding_face_review_approved',
            'module' => 'Identity Face Review',
            'target' => "Grantee #{$grantee->id}",
            'context' => [
                'distance' => $faceReview->onboarding_face_distance,
                'profile_id' => $faceReview->id,
                'account_status' => 'identity_verified',
                'challenge_stills_purged' => true,
                'set_password_link_sent' => $mailed,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'identity' => $this->present($faceReview->fresh()->loadMissing(['grantee.batch', 'user'])),
                'account_status' => 'identity_verified',
                'decision' => 'approved',
                'set_password_link_sent' => $mailed,
            ],
        ]);
    }

    public function reject(Request $request, GranteeIdentityProfile $faceReview): JsonResponse
    {
        abort_unless($faceReview->status === 'pending_face_review', 404);
        $faceReview->loadMissing(['grantee', 'user']);

        $user = $faceReview->user;
        $grantee = $faceReview->grantee;
        if (! $user || ! $grantee) {
            throw ValidationException::withMessages([
                'profile' => 'Face review is missing linked student or grantee.',
            ]);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->purgeChallengeStills($faceReview);

        // Recoverable, not terminal. A rejected face match may well be an impostor
        // who used a forwarded link — blocking here would punish the real grantee.
        // Reset the funnel and mail a fresh link to the address of record so the
        // legitimate student can retry. 'blocked' stays reserved for admin action.
        $faceReview->update([
            'status' => 'pending_id_scan',
            'id_scan_completed_at' => null,
            'onboarding_face_distance' => null,
            'onboarding_selfie_descriptor' => null,
        ]);
        $user->forceFill(['account_status' => 'identity_rejected'])->save();
        $grantee->update(['status' => 'identity_mismatch']);

        // Kill the impostor's session and their activation link.
        app(AuthTokenService::class)->revokeAll($user);

        $link = app(ActivationTokenIssuer::class)->issueLinkFor($user);
        $mailed = true;
        try {
            Mail::to($user->email, $user->name)->send(new IdentityRejectedRetryMail(
                $user,
                $link['url'],
                $validated['reason'] ?? null,
            ));
        } catch (\Throwable $exception) {
            report($exception);
            $mailed = false;
        }

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => ucfirst((string) $request->user()->role),
            'action' => 'onboarding_face_review_rejected',
            'module' => 'Identity Face Review',
            'target' => "Grantee #{$grantee->id}",
            'context' => [
                'distance' => $faceReview->onboarding_face_distance,
                'profile_id' => $faceReview->id,
                'reason' => $validated['reason'] ?? null,
                'account_status' => 'identity_rejected',
                'challenge_stills_purged' => true,
                'retry_link_sent' => $mailed,
                // Surfaces repeated takeover attempts against one grantee.
                'rejected_attempt_ip' => $faceReview->last_liveness_ip,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'identity' => $this->present($faceReview->fresh()->loadMissing(['grantee.batch', 'user'])),
                'account_status' => 'identity_rejected',
                'decision' => 'rejected',
                'retry_link_sent' => $mailed,
            ],
        ]);
    }

    /**
     * Delete review-only challenge stills. Keep id_reference_face, onboarding_selfie, and descriptors
     * so Requirements Slot 1 matching continues to work after staff decision.
     */
    private function purgeChallengeStills(GranteeIdentityProfile $profile): void
    {
        foreach (['liveness_challenge_1_path', 'liveness_challenge_2_path'] as $column) {
            $path = $profile->{$column};
            if (is_string($path) && $path !== '') {
                VaultFileStorage::deleteIfOwned($path);
            }
        }

        $profile->forceFill([
            'liveness_challenge_1_path' => null,
            'liveness_challenge_2_path' => null,
            'liveness_challenge_labels' => null,
        ])->save();
    }

    private function present(GranteeIdentityProfile $profile): array
    {
        $grantee = $profile->grantee;
        $granteeId = (int) $profile->grantee_id;
        $distance = $profile->onboarding_face_distance;
        $zone = is_numeric($distance)
            ? FaceDescriptorMath::classify((float) $distance)
            : null;

        $labels = is_array($profile->liveness_challenge_labels)
            ? array_values($profile->liveness_challenge_labels)
            : [];

        return [
            'id' => $profile->id,
            'status' => $profile->status,
            'grantee_id' => $granteeId,
            'student_name' => $grantee?->full_name ?? $profile->user?->name,
            'student_id' => $grantee?->student_id ?? $profile->user?->student_id,
            'student_number' => $grantee?->student_number,
            'email' => $profile->user?->email ?? $grantee?->email,
            'batch_name' => $grantee?->batch?->name,
            'onboarding_face_distance' => $profile->onboarding_face_distance,
            'face_zone' => $zone,
            'pass_max' => FaceDescriptorMath::passMax(),
            'review_max' => FaceDescriptorMath::reviewMax(),
            'id_scan_completed_at' => $profile->id_scan_completed_at,
            'updated_at' => $profile->updated_at,
            'id_reference_face_url' => $profile->id_reference_face_path
                ? VaultFileStorage::authStaffIdentityUrl($granteeId, 'id_reference_face.jpg')
                : null,
            'id_front_frame_url' => is_string(data_get($profile->id_ocr_payload, 'frame_path'))
                ? VaultFileStorage::authStaffIdentityUrl($granteeId, 'id_onboarding_frame.jpg')
                : null,
            'id_back_frame_url' => is_string(data_get($profile->id_ocr_payload, 'back_path'))
                ? VaultFileStorage::authStaffIdentityUrl($granteeId, 'id_onboarding_back.jpg')
                : null,
            'onboarding_selfie_url' => $profile->onboarding_selfie_path
                ? VaultFileStorage::authStaffIdentityUrl($granteeId, 'onboarding_selfie.jpg')
                : null,
            'liveness_challenge_1_url' => $profile->liveness_challenge_1_path
                ? VaultFileStorage::authStaffIdentityUrl($granteeId, 'liveness_challenge_1.jpg')
                : null,
            'liveness_challenge_2_url' => $profile->liveness_challenge_2_path
                ? VaultFileStorage::authStaffIdentityUrl($granteeId, 'liveness_challenge_2.jpg')
                : null,
            'liveness_challenge_labels' => $labels,
            'account_status' => $profile->user?->account_status,
        ];
    }
}
