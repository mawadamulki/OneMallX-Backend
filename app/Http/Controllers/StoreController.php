<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\Request;

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
}
