<?php

namespace App\Http\Controllers;

use App\Models\AcademicProgram;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\KycProfile;
use App\Services\MasterlistTruthService;
use App\Services\StudentOnboardingNavigator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentKycController extends Controller
{
    /** @var list<string> */
    private const YEAR_LEVEL_OPTIONS = ['1', '2', '3', '4'];

    public function show(
        Request $request,
        MasterlistTruthService $truth,
        StudentOnboardingNavigator $navigator,
    ): JsonResponse {
        $grantee = $this->grantee($request);
        $reference = $truth->forGrantee($grantee);
        $profile = $request->user()->kycProfile;
        $nameParts = $profile
            ? $this->splitStoredName($truth, $profile->full_name)
            : ['first_name' => null, 'middle_name' => null, 'last_name' => null];

        return response()->json([
            'data' => [
                'status' => $profile?->status ?? 'not_submitted',
                'mismatches' => $profile?->mismatches ?? [],
                // Masked hint only — never a full masterlist cheat sheet.
                'hint' => [
                    'student_id_last4' => $this->last4($reference['student_id']),
                ],
                'programs' => $this->activePrograms(),
                'year_level_options' => self::YEAR_LEVEL_OPTIONS,
                'profile' => [
                    'first_name' => $nameParts['first_name'],
                    'middle_name' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                    'full_name' => $profile?->full_name,
                    'student_id' => $profile?->student_id,
                    'program' => $profile?->program,
                    'year_level' => $profile?->year_level,
                    'birthdate' => $profile?->birthdate?->format('Y-m-d'),
                    'contact' => $profile?->contact,
                    'address' => $profile?->address,
                    'guardian_name' => $profile?->guardian_name,
                    'household_income' => $profile?->household_income,
                    'status' => $profile?->status,
                ],
                'next_step' => $navigator->nextStep($request->user(), $grantee),
            ],
        ]);
    }

    public function store(
        Request $request,
        MasterlistTruthService $truth,
        StudentOnboardingNavigator $navigator,
    ): JsonResponse {
        $user = $request->user();
        $grantee = $this->grantee($request);
        $reference = $truth->forGrantee($grantee);
        $programs = $this->activePrograms();
        $programValues = collect($programs)
            ->flatMap(fn (array $row) => [$row['code'], $row['name']])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'student_id' => ['required', 'string', 'max:100'],
            'program' => ['required', 'string', 'max:255', Rule::in($programValues)],
            // Optional for display only — not part of masterlist cross-check.
            'year_level' => ['nullable', 'string', 'max:40', Rule::in(self::YEAR_LEVEL_OPTIONS)],
            'birthdate' => ['nullable', 'date'],
            'contact' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:1000'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'household_income' => ['nullable', 'numeric', 'min:0'],
        ]);

        $mismatches = [];
        if (! $truth->namesMatch(
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name'],
            $reference['full_name'],
        )) {
            $mismatches['full_name'] = 'Name does not match the masterlist record for this account.';
        }
        if (! $truth->studentIdsMatch($validated['student_id'], $reference['student_id'])) {
            $mismatches['student_id'] = 'Student ID does not match the masterlist record for this account.';
        }
        if (! $truth->programsMatch($validated['program'], $reference['program'], $programs)) {
            $mismatches['program'] = 'Program does not match the masterlist record for this account.';
        }

        if ($mismatches !== []) {
            throw ValidationException::withMessages($mismatches);
        }

        $yearLevel = filled($validated['year_level'] ?? null)
            ? (string) $validated['year_level']
            : null;

        // Persist canonical masterlist identity after successful cross-check.
        $bound = [
            'full_name' => $reference['full_name'],
            'student_id' => $reference['student_id'],
            'program' => $reference['program'],
            'year_level' => $yearLevel,
            'birthdate' => $validated['birthdate'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'address' => $validated['address'] ?? null,
            'guardian_name' => $validated['guardian_name'] ?? null,
            'household_income' => $validated['household_income'] ?? null,
        ];

        $profile = KycProfile::updateOrCreate(
            ['user_id' => $user->id, 'grantee_id' => $grantee->id],
            [
                ...$bound,
                'status' => 'verified',
                'mismatches' => [],
            ],
        );

        // Identity onboarding (ID scan + liveness) must complete before account is fully active.
        $user->forceFill(['account_status' => 'pending_identity'])->save();
        $granteePayload = ['status' => 'kyc_verified'];
        if ($yearLevel !== null) {
            $granteePayload['year_level'] = $yearLevel;
        }
        $grantee->update($granteePayload);
        GranteeIdentityProfile::firstOrCreate(
            ['grantee_id' => $grantee->id],
            ['user_id' => $user->id, 'status' => 'pending_id_scan'],
        );

        return response()->json([
            'data' => [
                'status' => $profile->status,
                'mismatches' => [],
                'profile' => $profile,
                'account_status' => $user->account_status,
                'next_step' => 'id_scan',
                'onboarding_path' => $navigator->frontendPath('id_scan'),
            ],
        ]);
    }

    private function grantee(Request $request): Grantee
    {
        return Grantee::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    private function activePrograms(): array
    {
        return AcademicProgram::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (AcademicProgram $program) => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
            ])
            ->values()
            ->all();
    }

    private function last4(string $studentId): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]+/', '', $studentId) ?: $studentId;

        return strlen($clean) <= 4 ? $clean : substr($clean, -4);
    }

    /**
     * @return array{first_name: string, middle_name: string, last_name: string}
     */
    private function splitStoredName(MasterlistTruthService $truth, ?string $fullName): array
    {
        $parts = $truth->parseNameParts($fullName ?? '');

        return [
            'first_name' => $parts['first'] !== '' ? Str::title($parts['first']) : '',
            'middle_name' => $parts['middle'] !== '' ? Str::title($parts['middle']) : '',
            'last_name' => $parts['last'] !== '' ? Str::title($parts['last']) : '',
        ];
    }
}
