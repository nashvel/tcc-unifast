<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(): JsonResponse
    {
        $faqs = Faq::active()->ordered()->get();
        return response()->json(['data' => $faqs]);
    }

    public function all(): JsonResponse
    {
        $faqs = Faq::orderBy('sort_order')->orderBy('id')->get();
        return response()->json(['data' => $faqs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $faq = Faq::create($validated);
        return response()->json(['data' => $faq], 201);
    }

    public function show(Faq $faq): JsonResponse
    {
        return response()->json(['data' => $faq]);
    }

    public function update(Request $request, Faq $faq): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'sometimes|required|string|max:500',
            'answer' => 'sometimes|required|string',
            'category' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $faq->update($validated);
        return response()->json(['data' => $faq]);
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $faq->update(['is_active' => false]);
        return response()->json(['message' => 'FAQ deactivated (soft deleted).']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:faqs,id',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            Faq::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'FAQs reordered.']);
    }
}
