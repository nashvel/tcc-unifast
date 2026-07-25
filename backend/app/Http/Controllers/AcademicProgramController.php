<?php

namespace App\Http\Controllers;

use App\Models\AcademicProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicProgramController extends Controller
{
    public function index(): JsonResponse
    {
        $programs = AcademicProgram::query()
            ->orderBy('code')
            ->get()
            ->map(fn (AcademicProgram $program) => $this->present($program));

        return response()->json(['data' => $programs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:academic_programs,code'],
            'name' => ['required', 'string', 'max:180'],
            'pass_grade' => ['required', 'numeric', 'min:1', 'max:5'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $program = AcademicProgram::create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'pass_grade' => round((float) $validated['pass_grade'], 1),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $this->present($program)], 201);
    }

    public function update(Request $request, AcademicProgram $academicProgram): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:academic_programs,code,'.$academicProgram->id],
            'name' => ['sometimes', 'string', 'max:180'],
            'pass_grade' => ['sometimes', 'numeric', 'min:1', 'max:5'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }
        if (isset($validated['pass_grade'])) {
            $validated['pass_grade'] = round((float) $validated['pass_grade'], 1);
        }

        $academicProgram->fill($validated)->save();

        return response()->json(['data' => $this->present($academicProgram->fresh())]);
    }

    public function destroy(AcademicProgram $academicProgram): JsonResponse
    {
        $academicProgram->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AcademicProgram $program): array
    {
        return [
            'id' => $program->id,
            'code' => $program->code,
            'name' => $program->name,
            'pass_grade' => round((float) $program->pass_grade, 1),
            'pass_grade_display' => number_format((float) $program->pass_grade, 1, '.', ''),
            'is_active' => (bool) $program->is_active,
        ];
    }
}
