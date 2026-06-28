<?php

namespace App\Http\Controllers;

use App\Services\StoreAnalyticsService;
use Illuminate\Http\Request;

class StoreAnalyticsController extends Controller
{
    public function __construct(private StoreAnalyticsService $service) {}

    public function dashboard(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        return $this->respond($this->service->getDashboard(
            $data['from'] ?? null,
            $data['to'] ?? null,
        ));
    }

    private function respond(array $result)
    {
        $status = $result['http_status'] ?? ($result['success'] ? 200 : 422);
        unset($result['http_status'], $result['success']);

        return response()->json($result, $status);
    }
}
