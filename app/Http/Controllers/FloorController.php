<?php

namespace App\Http\Controllers;

use App\Services\FloorService;
use Illuminate\Http\Request;

class FloorController extends Controller
{
    protected $floorService;

    public function __construct(FloorService $floorService)
    {
        $this->floorService = $floorService;
    }

    public function index()
    {
        return response()->json(
            $this->floorService->getAllFloors()
        );
    }

    public function show($id)
    {
        return response()->json(
            $this->floorService->getFloor($id)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'number' => 'required|integer',
            'mallID' => 'required|exists:malls,id',
        ]);

        return response()->json(
            $this->floorService->createFloor($validated)
        );
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'number' => 'sometimes|integer',
        ]);

        return response()->json(
            $this->floorService->updateFloor($id, $validated)
        );
    }

    public function destroy($id)
    {
        return response()->json([
            'deleted' => $this->floorService->deleteFloor($id)
        ]);
    }

    public function media($id)
    {
        $result = $this->floorService->getFloorMedia((int) $id);

        if ($result === null) {
            return response()->json(['message' => 'Floor not found'], 404);
        }

        return response()->json($result);
    }

    public function storeMedia(Request $request, $id)
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $result = $this->floorService->attachMedia((int) $id, $validated['photo']);

        if (isset($result['error'])) {
            return response()->json($result, 404);
        }

        return response()->json($result, 201);
    }

    public function AdminFloors()
    {
        return response()->json(
            $this->floorService->getAdminFloors()
        );
    }

    public function ShortFloors()
    {
        return response()->json(
            $this->floorService->getShortFloors()
        );
    }

    public function AdminFloorsOverviewCounts()
    {
        return response()->json(
            $this->floorService->getAdminFloorsOverviewCounts()
        );
    }
}
