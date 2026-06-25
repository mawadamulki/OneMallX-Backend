<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {}

    // Customer: paginated list (accountStatus = active only).
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $areaId = $request->filled('areaID') ? $request->integer('areaID') : null;

        return response()->json(
            $this->storeService->listForCustomer($perPage, $areaId)
        );
    }

    // Customer: single store (404 if not active for customers). Route uses {storeId}.
    public function show($storeId)
    {
        $store = Store::query()->find($storeId);

        if (! $store) {
            abort(404);
        }

        $payload = $this->storeService->showForCustomer($store);

        if ($payload === null) {
            abort(404);
        }

        return response()->json($payload);
    }


    // Admin: all stores, 20 per page — id, name, media only.
    public function adminStoresSummary(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 20);

        return response()->json(
            $this->storeService->adminStoresSummaryList($perPage)
        );
    }

    // Admin: full store details by id.
    public function adminStoreDetails($storeId)
    {
        $payload = $this->storeService->adminStoreFull($storeId);
        if ($payload === null) {
            abort(404);
        }
        return response()->json($payload);
    }


    // Admin: products in a store — id, name, media, price (paginated, default 20).
    public function adminStoreProducts(Request $request, $storeId)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        return response()->json(
            $this->storeService->adminStoreProductsSummary($storeId, $perPage)
        );
    }


    // Admin: full product details by id.
    public function adminProductDetails($productId)
    {
        $payload = $this->storeService->adminProductFull($productId);

        if ($payload === null) {
            abort(404);
        }

        return response()->json($payload);
    }

    public function adminStoreRate(Request $request, $storeId)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $result = $this->storeService->getStoreRate((int) $storeId, $perPage);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function adminProductRate(Request $request, $productId)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $result = $this->storeService->getProductRate((int) $productId, $perPage);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function showForOwner()
    {
        $result = $this->storeService->showForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function planForOwner()
    {
        $result = $this->storeService->planForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function updateForOwner(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $result = $this->storeService->updateForOwner((int) Auth::id(), $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function storeMedia(Request $request)
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $result = $this->storeService->attachMediaForOwner((int) Auth::id(), $validated['photo']);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function storeLogo(Request $request)
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $result = $this->storeService->attachLogoForOwner((int) Auth::id(), $validated['photo']);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function destroyLogo()
    {
        $result = $this->storeService->deleteLogoForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function destroyMedia($mediaId)
    {
        $result = $this->storeService->deleteMediaForOwner((int) Auth::id(), (int) $mediaId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}
