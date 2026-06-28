<?php

namespace App\Http\Controllers;

use App\Services\AdminAnalyticsService;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function __construct(private AdminAnalyticsService $service) {}

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
