<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $result = $this->productService->listForOwner((int) Auth::id(), $perPage);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function show($productId)
    {
        $result = $this->productService->showForOwner((int) Auth::id(), (int) $productId);

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
            'detail' => 'nullable|string',
            'shortDetail' => 'nullable|string|max:500',
            'status' => 'required|in:draft,active,archived',
            'isFeatured' => 'sometimes|boolean',
            'publishedAt' => 'nullable|date',
            'categoryIds' => 'sometimes|array',
            'categoryIds.*' => 'integer',
            'variant' => 'required_without:variants|array',
            'variants' => 'required_without:variant|array|min:1',
            'variant.sku' => 'required_with:variant|string|max:100',
            'variant.price' => 'required_with:variant|integer|min:0',
            'variant.quantity' => 'required_with:variant|integer|min:0',
            'variant.barcode' => 'nullable|string|max:100',
            'variant.name' => 'nullable|string|max:255',
            'variant.compareAtPrice' => 'nullable|integer|min:0',
            'variant.discountPercentage' => 'sometimes|integer|min:0|max:100',
            'variant.costPrice' => 'nullable|integer|min:0',
            'variant.weight' => 'nullable|integer|min:0',
            'variant.isDefault' => 'sometimes|boolean',
            'variant.status' => 'sometimes|in:active,inactive,out_of_stock',
            'variant.attributeValueIds' => 'sometimes|array',
            'variant.attributeValueIds.*' => 'integer',
            'variants.*.sku' => 'required|string|max:100',
            'variants.*.price' => 'required|integer|min:0',
            'variants.*.quantity' => 'required|integer|min:0',
            'variants.*.barcode' => 'nullable|string|max:100',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.compareAtPrice' => 'nullable|integer|min:0',
            'variants.*.discountPercentage' => 'sometimes|integer|min:0|max:100',
            'variants.*.costPrice' => 'nullable|integer|min:0',
            'variants.*.weight' => 'nullable|integer|min:0',
            'variants.*.isDefault' => 'sometimes|boolean',
            'variants.*.status' => 'sometimes|in:active,inactive,out_of_stock',
            'variants.*.attributeValueIds' => 'sometimes|array',
            'variants.*.attributeValueIds.*' => 'integer',
        ]);

        $result = $this->productService->createForOwner((int) Auth::id(), $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function update(Request $request, $productId)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255',
            'detail' => 'nullable|string',
            'shortDetail' => 'nullable|string|max:500',
            'status' => 'sometimes|in:draft,active,archived',
            'isFeatured' => 'sometimes|boolean',
            'publishedAt' => 'nullable|date',
            'categoryIds' => 'sometimes|array',
            'categoryIds.*' => 'integer',
        ]);

        $result = $this->productService->updateForOwner((int) Auth::id(), (int) $productId, $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function destroy($productId)
    {
        $result = $this->productService->deleteForOwner((int) Auth::id(), (int) $productId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function storeVariant(Request $request, $productId)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:100',
            'price' => 'required|integer|min:0',
            'quantity' => 'required|integer|min:0',
            'barcode' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:255',
            'compareAtPrice' => 'nullable|integer|min:0',
            'discountPercentage' => 'sometimes|integer|min:0|max:100',
            'costPrice' => 'nullable|integer|min:0',
            'weight' => 'nullable|integer|min:0',
            'isDefault' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,inactive,out_of_stock',
            'attributeValueIds' => 'sometimes|array',
            'attributeValueIds.*' => 'integer',
        ]);

        $result = $this->productService->createVariantForOwner((int) Auth::id(), (int) $productId, $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function updateVariant(Request $request, $variantId)
    {
        $validated = $request->validate([
            'sku' => 'sometimes|string|max:100',
            'price' => 'sometimes|integer|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'barcode' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:255',
            'compareAtPrice' => 'nullable|integer|min:0',
            'discountPercentage' => 'sometimes|integer|min:0|max:100',
            'costPrice' => 'nullable|integer|min:0',
            'weight' => 'nullable|integer|min:0',
            'isDefault' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,inactive,out_of_stock',
            'attributeValueIds' => 'sometimes|array',
            'attributeValueIds.*' => 'integer',
        ]);

        $result = $this->productService->updateVariantForOwner((int) Auth::id(), (int) $variantId, $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function destroyVariant($variantId)
    {
        $result = $this->productService->deleteVariantForOwner((int) Auth::id(), (int) $variantId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function storeMedia(Request $request, $productId)
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $result = $this->productService->attachMediaForOwner(
            (int) Auth::id(),
            (int) $productId,
            $validated['photo']
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function destroyMedia($mediaId)
    {
        $result = $this->productService->deleteMediaForOwner((int) Auth::id(), (int) $mediaId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}
