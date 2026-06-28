<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsAnalyticsDashboard;
use App\Services\AnalyticsExportService;
use App\Services\StoreAnalyticsService;
use App\Support\Analytics\AnalyticsReportBuilder;
use Illuminate\Http\Request;

class StoreAnalyticsController extends Controller
{
    use ExportsAnalyticsDashboard;

    public function __construct(
        private StoreAnalyticsService $service,
        private AnalyticsExportService $exportService,
    ) {}

    public function dashboard(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        return $this->respondAnalytics($this->service->getDashboard(
            $data['from'] ?? null,
            $data['to'] ?? null,
        ));
    }

    public function export(Request $request)
    {
        $data = $this->validateAnalyticsExportRequest($request);

        return $this->exportAnalyticsDashboard(
            $request,
            $this->service->getDashboard($data['from'] ?? null, $data['to'] ?? null),
            fn (array $payload) => AnalyticsReportBuilder::fromStore($payload),
            $this->exportService,
        );
    }

    protected function respondAnalytics(array $result)
    {
        $status = $result['http_status'] ?? ($result['success'] ? 200 : 422);
        unset($result['http_status'], $result['success']);

        return response()->json($result, $status);
    }
}
