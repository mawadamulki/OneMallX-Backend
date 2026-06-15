<?php

namespace App\Http\Controllers;

use App\Services\ServiceProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceProviderController extends Controller
{
    public function __construct(
        protected ServiceProviderService $serviceProviderService
    ) {}

    public function show()
    {
        $result = $this->serviceProviderService->showForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'openTime' => 'nullable|date_format:H:i',
            'closeTime' => 'nullable|date_format:H:i',
            'locationID' => 'nullable|integer|exists:locations,id',
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

    public function destroyMedia($mediaId)
    {
        $result = $this->serviceProviderService->deleteMediaForOwner((int) Auth::id(), (int) $mediaId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}
