<?php

namespace App\Http\Controllers;

use App\Services\AdvertisementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdvertisementController extends Controller
{
    public function __construct(
        protected AdvertisementService $advertisementService
    ) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->advertisementService->listPublic($request->query('placement'))
        );
    }

    public function storeAdsIndex()
    {
        $result = $this->advertisementService->listForStoreOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function storeAdsProducts()
    {
        $result = $this->advertisementService->listProductsForStoreOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function storeAdsShow($adId)
    {
        $result = $this->advertisementService->showForStoreOwner((int) Auth::id(), (int) $adId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function storeAdsStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|max:5120',
            'targetType' => 'required|in:store,product',
            'productId' => 'required_if:targetType,product|integer|min:1',
            'startDate' => 'required|date|after_or_equal:today',
            'endDate' => 'nullable|date|after_or_equal:startDate',
        ]);

        $image = $validated['image'];
        unset($validated['image']);

        $result = $this->advertisementService->createForStoreOwner(
            (int) Auth::id(),
            $validated,
            $image
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function storeAdsUpdate(Request $request, $adId)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'image' => 'sometimes|image|max:5120',
            'targetType' => 'sometimes|in:store,product',
            'productId' => 'required_if:targetType,product|integer|min:1',
            'startDate' => 'sometimes|date',
            'endDate' => 'sometimes|date',
        ]);

        $image = $validated['image'] ?? null;
        unset($validated['image']);

        $result = $this->advertisementService->updateForStoreOwner(
            (int) Auth::id(),
            (int) $adId,
            $validated,
            $image
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function storeAdsDestroy($adId)
    {
        $result = $this->advertisementService->deleteForStoreOwner((int) Auth::id(), (int) $adId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function serviceAdsIndex()
    {
        $result = $this->advertisementService->listForServiceOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function serviceAdsItems()
    {
        $result = $this->advertisementService->listItemsForServiceOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function serviceAdsShow($adId)
    {
        $result = $this->advertisementService->showForServiceOwner((int) Auth::id(), (int) $adId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function serviceAdsStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|max:5120',
            'targetType' => 'required|in:service,service_item',
            'serviceItemId' => 'required_if:targetType,service_item|integer|min:1',
            'startDate' => 'required|date|after_or_equal:today',
            'endDate' => 'nullable|date|after_or_equal:startDate',
        ]);

        $image = $validated['image'];
        unset($validated['image']);

        $result = $this->advertisementService->createForServiceOwner(
            (int) Auth::id(),
            $validated,
            $image
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function serviceAdsUpdate(Request $request, $adId)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'image' => 'sometimes|image|max:5120',
            'targetType' => 'sometimes|in:service,service_item',
            'serviceItemId' => 'required_if:targetType,service_item|integer|min:1',
            'startDate' => 'sometimes|date',
            'endDate' => 'sometimes|date',
        ]);

        $image = $validated['image'] ?? null;
        unset($validated['image']);

        $result = $this->advertisementService->updateForServiceOwner(
            (int) Auth::id(),
            (int) $adId,
            $validated,
            $image
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function serviceAdsDestroy($adId)
    {
        $result = $this->advertisementService->deleteForServiceOwner((int) Auth::id(), (int) $adId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}
