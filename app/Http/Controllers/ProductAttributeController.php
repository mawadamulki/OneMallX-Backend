<?php

namespace App\Http\Controllers;

use App\Services\ProductAttributeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductAttributeController extends Controller
{
    public function __construct(
        protected ProductAttributeService $productAttributeService
    ) {}

    public function index()
    {
        $result = $this->productAttributeService->listForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'sortOrder' => 'sometimes|integer|min:0',
            'value' => 'sometimes|array',
            'values' => 'sometimes|array|min:1',
            'value.value' => 'required_with:value|string|max:255',
            'value.sortOrder' => 'sometimes|integer|min:0',
            'values.*.value' => 'required|string|max:255',
            'values.*.sortOrder' => 'sometimes|integer|min:0',
        ]);

        $result = $this->productAttributeService->createAttributeForOwner((int) Auth::id(), $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function update(Request $request, $attributeId)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:100',
            'sortOrder' => 'sometimes|integer|min:0',
        ]);

        $result = $this->productAttributeService->updateAttributeForOwner((int) Auth::id(), (int) $attributeId, $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function destroy($attributeId)
    {
        $result = $this->productAttributeService->deleteAttributeForOwner((int) Auth::id(), (int) $attributeId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function storeValue(Request $request, $attributeId)
    {
        $validated = $request->validate([
            'value' => 'required|string|max:255',
            'sortOrder' => 'sometimes|integer|min:0',
        ]);

        $result = $this->productAttributeService->createValueForOwner((int) Auth::id(), (int) $attributeId, $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function updateValue(Request $request, $valueId)
    {
        $validated = $request->validate([
            'value' => 'sometimes|string|max:255',
            'sortOrder' => 'sometimes|integer|min:0',
        ]);

        $result = $this->productAttributeService->updateValueForOwner((int) Auth::id(), (int) $valueId, $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function destroyValue($valueId)
    {
        $result = $this->productAttributeService->deleteValueForOwner((int) Auth::id(), (int) $valueId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}
