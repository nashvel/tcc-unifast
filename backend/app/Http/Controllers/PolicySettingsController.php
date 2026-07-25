<?php

namespace App\Http\Controllers;

use App\Models\PolicySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicySettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'max_failed_subjects_per_semester' => PolicySetting::maxFailedSubjects(),
                'auto_approve_risk_threshold' => (int) PolicySetting::getValue('auto_approve_risk_threshold', '20'),
                'default_pass_grade' => round(PolicySetting::defaultPassGrade(), 1),
                'default_pass_grade_display' => number_format(PolicySetting::defaultPassGrade(), 1, '.', ''),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'max_failed_subjects_per_semester' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'auto_approve_risk_threshold' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'default_pass_grade' => ['sometimes', 'numeric', 'min:1', 'max:5'],
        ]);

        foreach ($validated as $key => $value) {
            if ($key === 'default_pass_grade') {
                $value = number_format(round((float) $value, 1), 1, '.', '');
            }
            PolicySetting::setValue($key, $value);
        }

        return $this->show();
    }
}
