<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ServiceItemService;

class ServiceItemController extends Controller
{
    public function __construct(private ServiceItemService $service) {}

    public function show($id, Request $request)
    {
        $date = $request->get('date');

        return response()->json(
            $this->service->getItemWithAvailability($id, $date)
        );
    }

    public function index($serviceId)
{
    return response()->json(
        $this->service->getItemsByService($serviceId)
    );
}
public function days($id)
{
    return response()->json(
        $this->service->getAvailableDays($id)
    );
}
}
