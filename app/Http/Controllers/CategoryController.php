<?php

namespace App\Http\Controllers;

use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index()
    {
        $result = $this->categoryService->listForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'parentID' => 'nullable|integer',
            'sortOrder' => 'sometimes|integer|min:0',
        ]);

        $result = $this->categoryService->createForOwner((int) Auth::id(), $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function update(Request $request, $categoryId)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255',
            'parentID' => 'nullable|integer',
            'sortOrder' => 'sometimes|integer|min:0',
        ]);

        $result = $this->categoryService->updateForOwner((int) Auth::id(), (int) $categoryId, $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function destroy($categoryId)
    {
        $result = $this->categoryService->deleteForOwner((int) Auth::id(), (int) $categoryId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}
