<?php

namespace App\Http\Controllers;

use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermController extends Controller
{
    private function getOrCreateDefaultDocument(string $documentType = 'terms'): Term
    {
        $term = Term::query()
            ->where('document_type', $documentType)
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->first();

        if (! $term) {
            $defaults = $documentType === 'privacy'
                ? [
                    'title' => 'PRIVACY POLICY FOR TCC-UNIFAST TES PORTAL',
                    'version' => 'v1.0',
                    'content' => "PRIVACY POLICY FOR TCC-UNIFAST TES PORTAL\n\n1. SCOPE\nThis policy explains how Tagoloan Community College administers personal information through the UniFAST TES Portal.\n\n2. INFORMATION WE PROCESS\nThe portal may process account, academic, eligibility, submitted-document, identity-verification, security, device, and audit information needed to administer TES services.\n\n3. PURPOSES OF PROCESSING\nInformation is processed for TES administration, identity and eligibility verification, document validation, service communications, security, audit, and lawful reporting.\n\n4. YOUR RIGHTS\nYou may contact Tagoloan Community College through its official channels to ask questions or exercise applicable rights under Republic Act No. 10173, the Data Privacy Act of 2012.",
                ]
                : [
                    'title' => 'TERMS AND CONDITIONS FOR TCC-UNIFAST TES PORTAL',
                    'version' => 'v2.1.0',
                    'content' => "TERMS AND CONDITIONS FOR TCC-UNIFAST TES PORTAL\n\n1. ACCEPTANCE OF TERMS\nBy accessing and utilizing the Tagoloan Community College (TCC) UniFAST Tertiary Education Subsidy (TES) Portal, students and administrators agree to adhere to all terms, policies, and regulations governed by UniFAST guidelines.\n\n2. ACCURACY OF SUBMITTED DOCUMENTS\nAll documents uploaded (Certificate of Indigency, Transcript of Records, Student IDs, and Proof of Income) must be authentic. Falsification of documents will lead to immediate disqualification and legal escalation under RA 10931.",
                ];

            $term = Term::create([
                ...$defaults,
                'document_type' => $documentType,
                'is_active' => true,
            ]);
        }

        return $term;
    }

    public function index(): JsonResponse
    {
        $term = $this->getOrCreateDefaultDocument();

        return response()->json(['data' => [$term]]);
    }

    public function active(): JsonResponse
    {
        $term = $this->getOrCreateDefaultDocument();

        return response()->json(['data' => $term]);
    }

    public function privacyPolicy(): JsonResponse
    {
        return response()->json(['data' => $this->getOrCreateDefaultDocument('privacy')]);
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
            Term::where('document_type', 'terms')->where('is_active', true)->update(['is_active' => false]);
        }

        $term = Term::create([...$validated, 'document_type' => 'terms']);

        return response()->json(['data' => $term], 201);
    }

    public function show(Term $term): JsonResponse
    {
        return response()->json(['data' => $term]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $term = Term::find($id);
        if (! $term) {
            $term = $this->getOrCreateDefaultDocument();
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'version' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        if (! empty($validated['is_active']) && $validated['is_active']) {
            Term::where('document_type', $term->document_type)
                ->where('is_active', true)
                ->where('id', '!=', $term->id)
                ->update(['is_active' => false]);
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
