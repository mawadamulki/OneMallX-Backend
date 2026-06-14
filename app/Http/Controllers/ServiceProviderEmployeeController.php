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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'weekdays' => 'sometimes|array|min:1',
            'weekdays.*' => 'integer|min:1|max:7',
            'startsAt' => 'nullable|date_format:H:i',
            'endsAt' => 'nullable|date_format:H:i',
        ]);

        $result = $this->ServiceProviderEmployeeService->createForProvider((int) Auth::id(), $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function update(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $result = $this->ServiceProviderEmployeeService->updateForProvider((int) Auth::id(), (int) $employeeId, $validated);

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
            'weekdays' => 'required|array|min:1',
            'weekdays.*' => 'integer|min:1|max:7',
            'startsAt' => 'nullable|date_format:H:i',
            'endsAt' => 'nullable|date_format:H:i',
        ]);

        $result = $this->ServiceProviderEmployeeService->syncWorkingDaysForProvider((int) Auth::id(), (int) $employeeId, $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }
}

