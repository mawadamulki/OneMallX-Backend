<?php

namespace App\Services;

use App\DAO\AdminAnalyticsInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AdminAnalyticsService
{
    private const DEFAULT_PERIOD_DAYS = 30;

    public function __construct(private AdminAnalyticsInterface $adminAnalytics) {}

    public function getDashboard(?string $fromInput, ?string $toInput): array
    {
        if (Auth::id() === null) {
            return $this->fail('Unauthenticated', 401);
        }

        [$from, $to] = $this->resolvePeriod($fromInput, $toInput);

        if ($from->gt($to)) {
            return $this->fail('The from date must be before or equal to the to date', 422);
        }

        $data = $this->adminAnalytics->getDashboardData($from, $to);
        $usersByRole = collect($data['users_by_role'])->keyBy('role');

        return $this->success('OK', [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->copy()->startOfDay()->toDateString(),
                'days' => $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1,
            ],
            'kpis' => [
                'total_users' => $data['total_users'],
                'active_users' => $data['active_users'],
                'total_customers' => (int) ($usersByRole->get('Customer')['count'] ?? 0),
                'total_store_owners' => (int) ($usersByRole->get('Store Owner')['count'] ?? 0),
                'total_service_providers' => (int) ($usersByRole->get('Service Provider')['count'] ?? 0),
                'total_stores' => $data['total_stores'],
                'total_services' => $data['total_services'],
                'active_stores' => $data['active_stores'],
                'active_services' => $data['active_services'],
                'total_orders' => $data['total_orders'],
                'total_bookings' => $data['total_bookings'],
                'orders_in_period' => $data['orders_in_period'],
                'bookings_in_period' => $data['bookings_in_period'],
                'platform_revenue' => $data['platform_revenue'],
                'platform_revenue_growth_percent' => $this->calculateGrowthPercent(
                    $data['platform_revenue'],
                    $data['previous_platform_revenue']
                ),
                'average_platform_rating' => $data['average_platform_rating'],
                'ratings_count' => $data['ratings_count'],
            ],
            'user_analytics' => [
                'registrations_over_time' => $data['user_registrations_over_time'],
                'users_by_role' => $data['users_by_role'],
            ],
            'transaction_analytics' => [
                'orders_over_time' => $data['orders_over_time'],
                'bookings_over_time' => $data['bookings_over_time'],
                'orders_by_status' => $data['orders_by_status'],
                'bookings_by_status' => $data['bookings_by_status'],
            ],
            'revenue_analytics' => [
                'platform_revenue_over_time' => $data['platform_revenue_over_time'],
                'note' => 'Platform revenue is subscription payments (store + service). Commission tracking is not yet available.',
            ],
            'business_analytics' => [
                'top_stores' => $data['top_stores'],
                'top_services' => $data['top_services'],
                'stores_by_area_category' => $data['stores_by_area_category'],
                'services_by_area_category' => $data['services_by_area_category'],
            ],
            'moderation_analytics' => [
                'summary' => $data['moderation_summary'],
                'reports_by_status' => $data['reports_by_status'],
            ],
            'platform_health' => [
                'active_stores' => $data['active_stores'],
                'inactive_stores' => $data['inactive_stores'],
                'active_services' => $data['active_services'],
                'inactive_services' => $data['inactive_services'],
                'average_platform_rating' => $data['average_platform_rating'],
                'ratings_count' => $data['ratings_count'],
            ],
        ]);
    }

    public function getOverview(string $month): array
    {
        if (Auth::id() === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $from = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $to = $from->copy()->endOfMonth()->endOfDay();

        return array_merge(
            ['success' => true, 'http_status' => 200],
            $this->adminAnalytics->getOverviewStats($from, $to),
        );
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
