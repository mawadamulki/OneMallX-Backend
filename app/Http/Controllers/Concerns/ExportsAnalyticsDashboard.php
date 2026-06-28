<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AnalyticsExportService;
use App\Support\Analytics\AnalyticsReportBuilder;
use Illuminate\Http\Request;

trait ExportsAnalyticsDashboard
{
    protected function exportAnalyticsDashboard(
        Request $request,
        array $dashboardResult,
        callable $reportBuilder,
        AnalyticsExportService $exportService,
    ) {
        if (! ($dashboardResult['success'] ?? false)) {
            return $this->respondAnalytics($dashboardResult);
        }

        unset($dashboardResult['success'], $dashboardResult['http_status']);

        $report = $reportBuilder($dashboardResult);

        return $exportService->download($report, $request->string('format')->toString());
    }

    protected function validateAnalyticsExportRequest(Request $request): array
    {
        return $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'format' => 'required|in:pdf,csv,xlsx',
        ]);
    }

    abstract protected function respondAnalytics(array $result);
}
