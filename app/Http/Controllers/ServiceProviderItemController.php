<?php

namespace App\Http\Controllers;

use App\Services\ServiceProviderItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ServiceProviderItemController extends Controller
{
    public function __construct(
        protected ServiceProviderItemService $ServiceProviderItemService
    ) {}

    public function index()
    {
        $result = $this->ServiceProviderItemService->listForProvider((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function names()
    {
        $result = $this->ServiceProviderItemService->listNamesForProvider((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function show($itemId)
    {
        $result = $this->ServiceProviderItemService->showForProvider((int) Auth::id(), (int) $itemId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'duration' => 'required|integer|min:1',
            'employees' => 'sometimes|array',
            'employees.*.employeeID' => 'required_with:employees|integer',
            'employees.*.price' => 'nullable|integer|min:0',
        ]);

        $result = $this->ServiceProviderItemService->createForProvider((int) Auth::id(), $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function update(Request $request, $itemId)
    {
        $input = $this->normalizeItemUpdatePayload($request);

        $validated = validator($input, [
            'name' => ['sometimes', 'string', 'max:255', Rule::notIn(['0'])],
            'price' => 'sometimes|integer|min:0',
            'duration' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string|in:active,inactive',
        ])->validate();

        $result = $this->ServiceProviderItemService->updateForProvider((int) Auth::id(), (int) $itemId, $validated);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    /** @return array<string, mixed> */
    private function normalizeItemUpdatePayload(Request $request): array
    {
        $input = $request->only(['name', 'price', 'duration', 'status']);

        if (isset($input['price']) && is_array($input['price'])) {
            $input['price'] = $input['price']['min'] ?? $input['price']['max'] ?? null;
        }

        if (array_key_exists('name', $input)) {
            $input['name'] = trim((string) $input['name']);
        }

        return $input;
    }

    public function destroy($itemId)
    {
        $result = $this->ServiceProviderItemService->deleteForProvider((int) Auth::id(), (int) $itemId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function storeMedia(Request $request, $itemId)
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $result = $this->ServiceProviderItemService->attachMediaForProvider((int) Auth::id(), (int) $itemId, $validated['photo']);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function destroyMedia($mediaId)
    {
        $result = $this->ServiceProviderItemService->deleteMediaForProvider((int) Auth::id(), (int) $mediaId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}

