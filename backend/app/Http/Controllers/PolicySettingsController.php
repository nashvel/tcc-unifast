<?php

namespace App\Http\Controllers;

use App\Models\PolicySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PolicySettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $passMax = PolicySetting::facePassMax();
        $reviewMax = PolicySetting::faceReviewMax();

        return response()->json([
            'data' => [
                'max_failed_subjects_per_semester' => PolicySetting::maxFailedSubjects(),
                'auto_approve_risk_threshold' => (int) PolicySetting::getValue('auto_approve_risk_threshold', '20'),
                'default_pass_grade' => round(PolicySetting::defaultPassGrade(), 1),
                'default_pass_grade_display' => number_format(PolicySetting::defaultPassGrade(), 1, '.', ''),
                'identity_face_pass_max' => round($passMax, 4),
                'identity_face_review_max' => round($reviewMax, 4),
                'organization_academic_year' => PolicySetting::organizationAcademicYear(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'max_failed_subjects_per_semester' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'auto_approve_risk_threshold' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'default_pass_grade' => ['sometimes', 'numeric', 'min:1', 'max:5'],
            'identity_face_pass_max' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'identity_face_review_max' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'organization_academic_year' => ['sometimes', 'string', 'max:32', 'regex:/^20\d{2}\s*[-–\/]\s*(?:20)?\d{2,4}$/'],
        ]);

        $passMax = array_key_exists('identity_face_pass_max', $validated)
            ? (float) $validated['identity_face_pass_max']
            : PolicySetting::facePassMax();
        $reviewMax = array_key_exists('identity_face_review_max', $validated)
            ? (float) $validated['identity_face_review_max']
            : PolicySetting::faceReviewMax();

        if ($reviewMax < $passMax) {
            throw ValidationException::withMessages([
                'identity_face_review_max' => 'Face review max must be greater than or equal to pass max.',
            ]);
        }

        foreach ($validated as $key => $value) {
            if ($key === 'default_pass_grade') {
                $value = number_format(round((float) $value, 1), 1, '.', '');
            }
            if ($key === 'identity_face_pass_max' || $key === 'identity_face_review_max') {
                $value = number_format((float) $value, 4, '.', '');
            }
            if ($key === 'organization_academic_year') {
                $value = preg_replace('/\s+/', '', str_replace(['–', '/'], '-', (string) $value));
                if (preg_match('/^(20\d{2})-(\d{2})$/', (string) $value, $short)) {
                    $value = $short[1].'-'.substr($short[1], 0, 2).$short[2];
                }
            }
            PolicySetting::setValue($key, $value);
        }

        return $this->show();
    }
}
