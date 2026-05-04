<?php

namespace App\Http\Controllers;

use App\Services\ServiceService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(private ServiceService $serviceService) {}

    public function index($areaId)
    {
        return response()->json(
            $this->serviceService->getServicesByArea($areaId)
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
}
