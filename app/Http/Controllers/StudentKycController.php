<?php

namespace App\Http\Controllers;

use App\Models\Grantee;
use App\Models\KycProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudentKycController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $grantee = $this->grantee($request);
        $profile = $request->user()->kycProfile;

        return response()->json([
            'data' => [
                'status' => $profile?->status ?? 'not_submitted',
                'mismatches' => $profile?->mismatches ?? [],
                'reference' => [
                    'full_name' => $grantee->full_name,
                    'student_id' => $grantee->student_id,
                    'program' => $grantee->program,
                    'year_level' => $grantee->year_level,
                ],
                'profile' => $profile,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'student_id' => ['required', 'string', 'max:100'],
            'program' => ['required', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
            'contact' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:1000'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'household_income' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $grantee = $this->grantee($request);
        $mismatches = $this->mismatches($validated, $grantee);
        $matched = $mismatches === [];

        $profile = KycProfile::updateOrCreate(
            ['user_id' => $user->id, 'grantee_id' => $grantee->id],
            [
                ...$validated,
                'status' => $matched ? 'verified' : 'mismatch',
                'mismatches' => $mismatches,
            ],
        );

        $user->forceFill(['account_status' => $matched ? 'active' : 'blocked'])->save();
        $grantee->update(['status' => $matched ? 'verified' : 'kyc_mismatch']);

        return response()->json([
            'data' => [
                'status' => $profile->status,
                'mismatches' => $mismatches,
                'profile' => $profile,
                'account_status' => $user->account_status,
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
     * @param array<string, mixed> $submitted
     * @return array<string, string>
     */
    private function mismatches(array $submitted, Grantee $grantee): array
    {
        $mismatches = [];
        if ($this->key($submitted['full_name']) !== $this->key($grantee->full_name)) {
            $mismatches['full_name'] = 'Submitted name does not match the CHED masterlist record.';
        }
        if ($this->key($submitted['student_id']) !== $this->key($grantee->student_id)) {
            $mismatches['student_id'] = 'Submitted student ID does not match the CHED masterlist record.';
        }
        if ($this->key($submitted['program']) !== $this->key($grantee->program)) {
            $mismatches['program'] = 'Submitted program does not match the CHED masterlist record.';
        }

        return $mismatches;
    }

    private function key(mixed $value): string
    {
        return Str::of((string) $value)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
    }
}
