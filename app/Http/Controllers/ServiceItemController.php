<?php

namespace App\Http\Controllers;

use App\Services\ServiceItemService;
use Illuminate\Http\Request;

class ServiceItemController extends Controller
{
    public function __construct(private ServiceItemService $service) {}

    public function getAvailability($id, Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
        ]);

        $result = $this->service->getItemWithAvailability((int) $id, $request->get('date'));

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 404);
        }

        return response()->json($result);
    }

    public function getItemsInService($serviceId)
    {
        return response()->json(
            $this->service->getItemsByService((int) $serviceId)
        );
    }

    public function days($id)
    {
        $result = $this->service->getAvailableDays((int) $id);

        if (isset($result['error'])) {
            $status = $result['error'] === 'Item not found' ? 404 : 422;

            return response()->json(['message' => $result['error']], $status);
        }

        return response()->json($result);
    }
}
