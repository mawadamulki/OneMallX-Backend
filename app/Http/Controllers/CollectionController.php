<?php

namespace App\Http\Controllers;

use App\Services\CollectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CollectionController extends Controller
{
    public function __construct(
        protected CollectionService $collectionService,
    ) {}

    public function index()
    {
        $result = $this->collectionService->listForOwner((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function show($collectionId)
    {
        $result = $this->collectionService->showForOwner((int) Auth::id(), (int) $collectionId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|max:5120',
            'productIds' => 'sometimes|array',
            'productIds.*' => 'integer|min:1',
        ]);

        $image = $validated['image'];
        unset($validated['image']);

        $result = $this->collectionService->createForOwner((int) Auth::id(), $validated, $image);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result, $result['http_status'] ?? 201);
    }

    public function update(Request $request, $collectionId)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'sometimes|image|max:5120',
            'productIds' => 'sometimes|array',
            'productIds.*' => 'integer|min:1',
        ]);

        $image = $validated['image'] ?? null;
        unset($validated['image']);

        $result = $this->collectionService->updateForOwner(
            (int) Auth::id(),
            (int) $collectionId,
            $validated,
            $image,
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function destroy($collectionId)
    {
        $result = $this->collectionService->deleteForOwner((int) Auth::id(), (int) $collectionId);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }
}
