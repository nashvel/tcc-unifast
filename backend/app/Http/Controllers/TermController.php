<?php

namespace App\Http\Controllers;

use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermController extends Controller
{
    private function getOrCreateDefaultTerm(): Term
    {
        $term = Term::query()->orderByDesc('is_active')->orderByDesc('updated_at')->first();
        if (!$term) {
            $term = Term::create([
                'title' => 'TERMS AND CONDITIONS FOR TCC-UNIFAST TES PORTAL',
                'version' => 'v2.1.0',
                'content' => "TERMS AND CONDITIONS FOR TCC-UNIFAST TES PORTAL\n\n1. ACCEPTANCE OF TERMS\nBy accessing and utilizing the Tagoloan Community College (TCC) UniFAST Tertiary Education Subsidy (TES) Portal, students and administrators agree to adhere to all terms, policies, and regulations governed by UniFAST guidelines.\n\n2. ACCURACY OF SUBMITTED DOCUMENTS\nAll documents uploaded (Certificate of Indigency, Transcript of Records, Student IDs, and Proof of Income) must be authentic. Falsification of documents will lead to immediate disqualification and legal escalation under RA 10931.\n\n3. DATA PRIVACY COMPLIANCE\nIn compliance with Republic Act 10173 (Data Privacy Act of 2012), all student records collected through this portal will be processed exclusively for subsidy qualification verification and reporting.",
                'is_active' => true,
            ]);
        }
        return $term;
    }

    public function index(): JsonResponse
    {
        $term = $this->getOrCreateDefaultTerm();
        return response()->json(['data' => [$term]]);
    }

    public function active(): JsonResponse
    {
        $term = $this->getOrCreateDefaultTerm();
        return response()->json(['data' => $term]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'version' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validated['is_active'] ?? false) {
            Term::where('is_active', true)->update(['is_active' => false]);
        }

        $term = Term::create($validated);
        return response()->json(['data' => $term], 201);
    }

    public function show(Term $term): JsonResponse
    {
        return response()->json(['data' => $term]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $term = Term::find($id);
        if (!$term) {
            $term = $this->getOrCreateDefaultTerm();
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'version' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_active']) && $validated['is_active']) {
            Term::where('is_active', true)->where('id', '!=', $term->id)->update(['is_active' => false]);
        }

        $term->update($validated);
        return response()->json(['data' => $term]);
    }

    public function destroy(Term $term): JsonResponse
    {
        $term->delete();
        return response()->json(['message' => 'Term deleted.']);
    }
}
