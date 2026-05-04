<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AreaService;

class AreaController extends Controller
{
    protected $areaService;

    public function __construct(AreaService $areaService)
    {
        $this->areaService = $areaService;
    }

    public function getStoreAreas($floorId)
    {
        return response()->json(
            $this->areaService->getStoreAreasByFloor($floorId)
        );
    }

    public function getServiceAreas($floorId)
    {
        return response()->json(
            $this->areaService->getServiceAreasByFloor($floorId)
        );
    }

    public function index($floorId)
    {
        return response()->json(
            $this->areaService->getAreasByFloor($floorId)
        );
    }

    public function show($id)
    {
        return response()->json(
            $this->areaService->getArea($id)
        );
    }

    public function store(Request $request, $floorId)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'number' => 'required|integer',
            'usageType' => 'required|in:store,service',
            'category' => 'required|string',
            'maxCapacity' => 'required|integer|min:1',
        ]);

        return response()->json(
            $this->areaService->createArea($floorId, $validated)
        );
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'maxCapacity' => 'required|integer|min:1',
        ]);

        return response()->json(
            $this->areaService->updateArea($id, $validated)
        );
    }

    public function destroy($id)
    {
        return response()->json([
            'deleted' => $this->areaService->deleteArea($id)
        ]);
    }

    public function media($id)
    {
        $result = $this->areaService->getAreaMedia((int) $id);

        if ($result === null) {
            return response()->json(['message' => 'Area not found'], 404);
        }

        return response()->json($result);
    }

    public function storeMedia(Request $request, $id)
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $result = $this->areaService->attachMedia((int) $id, $validated['photo']);

        if (isset($result['error'])) {
            return response()->json($result, 404);
        }

        return response()->json($result, 201);
    }
}
