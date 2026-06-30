<?php

namespace App\Http\Controllers;

use App\Services\ServiceProviderService;
use App\Services\ServiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function __construct(
        private ServiceService $serviceService,
        protected ServiceProviderService $serviceProviderService,
    ) {}

    public function index(Request $request, $areaId)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        return response()->json(
            $this->serviceService->listForCustomer($perPage, (int) $areaId)
        );
    }

    public function list(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        return response()->json(
            $this->serviceService->listForCustomer($perPage, null)
        );
    }

    public function show($id)
    {
        $payload = $this->serviceService->getServiceDetails($id);

        if (isset($payload['error'])) {
            return response()->json($payload, 404);
        }

        return response()->json($payload);
    }

    public function adminServicesSummary(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 20);

        return response()->json(
            $this->serviceService->adminServicesSummaryList($perPage)
        );
    }

    public function adminServiceDetails($serviceId)
    {
        return response()->json(
            $this->serviceService->adminServiceDetails($serviceId)
        );
    }

    public function adminServiceItems($serviceId)
    {
        return response()->json(
            $this->serviceService->adminServiceItems($serviceId)
        );
    }

    public function adminServiceItemDetails($serviceItemId)
    {
        return response()->json(
            $this->serviceService->adminServiceItemDetails($serviceItemId)
        );
    }

    public function adminServiceRate(Request $request, $serviceId)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $result = $this->serviceService->getServiceRate((int) $serviceId, $perPage);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function adminServiceItemRate(Request $request, $serviceItemId)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $result = $this->serviceService->getServiceItemRate((int) $serviceItemId, $perPage);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function showForOwner()
    {
        $result = $this->serviceProviderService->showForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function planForOwner()
    {
        $result = $this->serviceProviderService->planForOwner((int) Auth::id());

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
            'openTime' => 'nullable|date_format:H:i',
            'closeTime' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:1000',
            'paymentAccount' => 'nullable|string|max:255',
        ]);

        $result = $this->serviceProviderService->updateForOwner((int) Auth::id(), $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function syncWorkingDays(Request $request)
    {
        $validated = $request->validate([
            'weekdays' => 'required|array|min:1',
            'weekdays.*' => 'integer|min:1|max:7',
        ]);

        $result = $this->serviceProviderService->syncWorkingDaysForOwner((int) Auth::id(), $validated);

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

        $result = $this->serviceProviderService->attachMediaForOwner((int) Auth::id(), $validated['photo']);

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

        $result = $this->serviceProviderService->attachLogoForOwner((int) Auth::id(), $validated['photo']);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function destroyLogo()
    {
        $result = $this->serviceProviderService->deleteLogoForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function destroyMedia($mediaId)
    {
        $result = $this->serviceProviderService->deleteMediaForOwner((int) Auth::id(), (int) $mediaId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}
