<?php

namespace App\Http\Controllers;

use App\Services\ServiceProviderEmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceProviderEmployeeController extends Controller
{
    public function __construct(
        protected ServiceProviderEmployeeService $ServiceProviderEmployeeService
    ) {}

    public function index()
    {
        $result = $this->ServiceProviderEmployeeService->listForProvider((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function show($employeeId)
    {
        $result = $this->ServiceProviderEmployeeService->showForProvider((int) Auth::id(), (int) $employeeId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phoneNumber' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'workingDays' => 'required|array|min:1',
            'workingDays.*.weekday' => 'required|integer|min:1|max:7',
            'workingDays.*.startsAt' => 'required|date_format:H:i',
            'workingDays.*.endsAt' => 'required|date_format:H:i',
            'serviceItemIds' => 'sometimes|array',
            'serviceItemIds.*' => 'integer|min:1',
            'status' => 'nullable|string|in:active,inactive',
            'photo' => 'nullable|image|max:5120',
        ]);

        $result = $this->ServiceProviderEmployeeService->createForProvider(
            (int) Auth::id(),
            $validated,
            $request->file('photo')
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function update(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phoneNumber' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'workingDays' => 'sometimes|array|min:1',
            'workingDays.*.weekday' => 'required_with:workingDays|integer|min:1|max:7',
            'workingDays.*.startsAt' => 'required_with:workingDays|date_format:H:i',
            'workingDays.*.endsAt' => 'required_with:workingDays|date_format:H:i',
            'serviceItemIds' => 'sometimes|array',
            'serviceItemIds.*' => 'integer|min:1',
            'status' => 'sometimes|string|in:active,inactive',
            'photo' => 'nullable|image|max:5120',
        ]);

        $result = $this->ServiceProviderEmployeeService->updateForProvider(
            (int) Auth::id(),
            (int) $employeeId,
            $validated,
            $request->file('photo')
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function destroy($employeeId)
    {
        $result = $this->ServiceProviderEmployeeService->deleteForProvider((int) Auth::id(), (int) $employeeId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function syncWorkingDays(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'workingDays' => 'required|array|min:1',
            'workingDays.*.weekday' => 'required|integer|min:1|max:7',
            'workingDays.*.startsAt' => 'required|date_format:H:i',
            'workingDays.*.endsAt' => 'required|date_format:H:i',
        ]);

        $result = $this->ServiceProviderEmployeeService->syncWorkingDaysForProvider(
            (int) Auth::id(),
            (int) $employeeId,
            $validated
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function storePhoto(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $result = $this->ServiceProviderEmployeeService->uploadPhotoForProvider(
            (int) Auth::id(),
            (int) $employeeId,
            $validated['photo']
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function destroyPhoto($employeeId)
    {
        $result = $this->ServiceProviderEmployeeService->deletePhotoForProvider(
            (int) Auth::id(),
            (int) $employeeId
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}
