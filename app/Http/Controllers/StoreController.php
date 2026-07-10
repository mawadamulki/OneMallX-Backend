<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Services\CategoryService;
use App\Services\CollectionService;
use App\Services\ProductService;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService,
        protected ProductService $productService,
        protected CategoryService $categoryService,
        protected CollectionService $collectionService,
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

    public function listByArea(Request $request, $areaId)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        return response()->json(
            $this->storeService->listForCustomer($perPage, (int) $areaId)
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

    public function customizationForMobile($storeId)
    {
        return $this->storeCustomizationJsonResponse((int) $storeId, 'customization');
    }

    public function customizationDataForMobile($storeId)
    {
        return $this->storeCustomizationJsonResponse((int) $storeId, 'customizationData');
    }

    public function detailCustomizationForMobile($storeId)
    {
        return $this->storeCustomizationJsonResponse((int) $storeId, 'detailCustomization');
    }

    public function detailCustomizationDataForMobile($storeId)
    {
        return $this->storeCustomizationJsonResponse((int) $storeId, 'detailCustomizationData');
    }

    private function storeCustomizationJsonResponse(int $storeId, string $field)
    {
        $store = $this->storeService->findVisibleStoreForCustomer($storeId);

        if ($store === null) {
            abort(404);
        }

        return response()->json($store->{$field});
    }

    public function products(Request $request, $storeId)
    {
        $perPage = $this->customerProductsPerPage($request);

        $products = $this->productService->listForCustomerByStore((int) $storeId, $perPage);

        if ($products === null) {
            abort(404);
        }

        return response()->json($products);
    }

    public function productsWithOffers(Request $request, $storeId)
    {
        $products = $this->productService->listOffersForCustomerByStore(
            (int) $storeId,
            $this->customerProductsPerPage($request)
        );

        if ($products === null) {
            abort(404);
        }

        return response()->json($products);
    }

    public function collections(Request $request, $storeId)
    {
        $payload = $this->collectionService->listForCustomerByStore((int) $storeId);

        if ($payload === null) {
            abort(404);
        }

        return response()->json($payload);
    }

    public function productsInCollection(Request $request, $collectionId)
    {
        $products = $this->productService->listForCustomerByCollection(
            (int) $collectionId,
            $this->customerProductsPerPage($request)
        );

        if ($products === null) {
            abort(404);
        }

        return response()->json($products);
    }

    public function categories(Request $request, $storeId)
    {
        $payload = $this->categoryService->listForCustomerByStore((int) $storeId);

        if ($payload === null) {
            abort(404);
        }

        return response()->json($payload);
    }

    public function productsInCategory(Request $request, $categoryId)
    {
        $products = $this->productService->listForCustomerByCategory(
            (int) $categoryId,
            $this->customerProductsPerPage($request)
        );

        if ($products === null) {
            abort(404);
        }

        return response()->json($products);
    }

    private function customerProductsPerPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 15), 1), 50);
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

    public function customizationForOwner()
    {
        $result = $this->storeService->customizationForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function updateCustomizationForOwner(Request $request)
    {
        $validated = $request->validate([
            'customization' => 'sometimes|array',
            'customizationData' => 'sometimes|array',
        ]);

        $result = $this->storeService->updateCustomizationForOwner((int) Auth::id(), $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function updateDetailCustomizationForOwner(Request $request)
    {
        $validated = $request->validate([
            'detailCustomization' => 'sometimes|array',
            'detailCustomizationData' => 'sometimes|array',
        ]);

        $result = $this->storeService->updateDetailCustomizationForOwner((int) Auth::id(), $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function detailCustomizationForOwner()
    {
        $result = $this->storeService->detailCustomizationForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
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

    public function indexMedia()
    {
        $result = $this->storeService->getMediaForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
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
