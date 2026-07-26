<?php

namespace App\Http\Controllers;

use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermController extends Controller
{
    public function index(): JsonResponse
    {
        $terms = Term::orderByDesc('is_active')->orderByDesc('updated_at')->get();
        return response()->json(['data' => $terms]);
    }

    public function active(): JsonResponse
    {
        $term = Term::active()->first();
        if (!$term) {
            return response()->json(['data' => null]);
        }
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

    public function update(Request $request, Term $term): JsonResponse
    {
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
