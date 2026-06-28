<?php

namespace App\Services;

use App\DAO\StoreAnalyticsInterface;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StoreAnalyticsService
{
    private const DEFAULT_PERIOD_DAYS = 30;

    public function __construct(private StoreAnalyticsInterface $storeAnalytics) {}

    public function getDashboard(?string $fromInput, ?string $toInput): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $store = $this->findOwnedStore((int) $userId);
        if ($store === null) {
            return $this->fail('Store not found', 404);
        }

        [$from, $to] = $this->resolvePeriod($fromInput, $toInput);

        if ($from->gt($to)) {
            return $this->fail('The from date must be before or equal to the to date', 422);
        }

        $data = $this->storeAnalytics->getDashboardData($store->id, $from, $to);

        return $this->success('OK', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->copy()->startOfDay()->toDateString(),
                'days' => $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1,
            ],
            'kpis' => [
                'total_revenue' => $data['total_revenue'],
                'total_orders' => $data['total_orders'],
                'total_products' => $data['total_products'],
                'total_customers' => $data['total_customers'],
                'average_order_value' => $data['average_order_value'],
                'sales_growth_percent' => $this->calculateGrowthPercent(
                    $data['total_revenue'],
                    $data['previous_period_revenue']
                ),
            ],
            'sales_overview' => [
                'revenue_over_time' => $data['revenue_over_time'],
                'orders_over_time' => $data['orders_over_time'],
            ],
            'orders_by_status' => $data['orders_by_status'],
            'top_products' => $data['top_products'],
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(?string $fromInput, ?string $toInput): array
    {
        $to = $toInput !== null
            ? Carbon::parse($toInput)->endOfDay()
            : Carbon::now()->endOfDay();

        $from = $fromInput !== null
            ? Carbon::parse($fromInput)->startOfDay()
            : $to->copy()->subDays(self::DEFAULT_PERIOD_DAYS - 1)->startOfDay();

        return [$from, $to];
    }

    private function calculateGrowthPercent(int $currentRevenue, int $previousRevenue): ?float
    {
        if ($previousRevenue === 0) {
            return $currentRevenue > 0 ? 100.0 : 0.0;
        }

        return round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2);
    }

    private function findOwnedStore(int $userId): ?Store
    {
        return Store::query()
            ->where('storeOwnerID', $userId)
            ->first();
    }

    private function success(string $message, array $extra = [], int $httpStatus = 200): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'http_status' => $httpStatus,
        ], $extra);
    }

    private function fail(string $message, int $httpStatus = 422): array
    {
        return [
            'success' => false,
            'message' => $message,
            'http_status' => $httpStatus,
        ];
    }
}
