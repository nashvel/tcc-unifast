<?php

namespace App\Http\Controllers;

use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\KycProfile;
use App\Services\MasterlistTruthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentKycController extends Controller
{
    public function show(Request $request, MasterlistTruthService $truth): JsonResponse
    {
        $grantee = $this->grantee($request);
        $reference = $truth->forGrantee($grantee);
        $profile = $request->user()->kycProfile;

        return response()->json([
            'data' => [
                'status' => $profile?->status ?? 'not_submitted',
                'mismatches' => $profile?->mismatches ?? [],
                'reference' => [
                    'full_name' => $reference['full_name'],
                    'student_id' => $reference['student_id'],
                    'program' => $reference['program'],
                    'year_level' => $reference['year_level'],
                ],
                'profile' => $profile,
                'next_step' => $this->nextStep($request->user()->account_status, $grantee),
            ],
        ]);
    }

    public function store(Request $request, MasterlistTruthService $truth): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'student_id' => ['required', 'string', 'max:100'],
            'program' => ['required', 'string', 'max:255'],
            'year_level' => ['required', 'string', 'max:40'],
            'birthdate' => ['nullable', 'date'],
            'contact' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:1000'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'household_income' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $grantee = $this->grantee($request);
        $reference = $truth->forGrantee($grantee);
        $mismatches = $this->mismatches($validated, $reference, $truth);
        $matched = $mismatches === [];

        $profile = KycProfile::updateOrCreate(
            ['user_id' => $user->id, 'grantee_id' => $grantee->id],
            [
                ...$validated,
                'status' => $matched ? 'verified' : 'mismatch',
                'mismatches' => $mismatches,
            ],
        );

        // Identity onboarding (ID scan + liveness) must complete before account is fully active.
        $accountStatus = $matched ? 'pending_identity' : 'blocked';
        $user->forceFill(['account_status' => $accountStatus])->save();
        $grantee->update([
            'status' => $matched ? 'kyc_verified' : 'kyc_mismatch',
            'year_level' => $matched ? $validated['year_level'] : $grantee->year_level,
        ]);

        if ($matched) {
            GranteeIdentityProfile::firstOrCreate(
                ['grantee_id' => $grantee->id],
                ['user_id' => $user->id, 'status' => 'pending_id_scan'],
            );
        }

        return response()->json([
            'data' => [
                'status' => $profile->status,
                'mismatches' => $mismatches,
                'profile' => $profile,
                'account_status' => $user->account_status,
                'next_step' => $matched ? 'id_scan' : 'blocked',
            ],
        ], $matched ? 200 : 422);
    }

    private function grantee(Request $request): Grantee
    {
        return Grantee::query()
            ->where('user_id', $request->user()->id)
            ->orWhere('student_id', $request->user()->student_id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @param  array{full_name: string, student_id: string, program: string, year_level: string|null}  $truth
     * @return array<string, string>
     */
    private function mismatches(array $submitted, array $truth, MasterlistTruthService $service): array
    {
        $mismatches = [];
        if ($service->key($submitted['full_name']) !== $service->key($truth['full_name'])) {
            $mismatches['full_name'] = 'Submitted name does not match the CHED masterlist record.';
        }
        if ($service->key($submitted['student_id']) !== $service->key($truth['student_id'])) {
            $mismatches['student_id'] = 'Submitted student ID does not match the CHED masterlist record.';
        }
        if ($service->key($submitted['program']) !== $service->key($truth['program'])) {
            $mismatches['program'] = 'Submitted program does not match the CHED masterlist record.';
        }
        if ($service->key($submitted['year_level']) !== $service->key($truth['year_level'] ?? '')) {
            $mismatches['year_level'] = 'Submitted year level does not match the CHED masterlist record.';
        }

        return $mismatches;
    }

    private function nextStep(?string $accountStatus, Grantee $grantee): string
    {
        if ($accountStatus === 'blocked') {
            return 'blocked';
        }
        if (in_array($accountStatus, ['unverified', 'pending_kyc'], true)) {
            return 'kyc';
        }
        if ($accountStatus === 'pending_identity') {
            $identity = $grantee->identityProfile;
            if (! $identity || $identity->status === 'pending_id_scan') {
                return 'id_scan';
            }
            if ($identity->status === 'pending_liveness') {
                return 'liveness';
            }
        }

        return 'done';
    }
}
