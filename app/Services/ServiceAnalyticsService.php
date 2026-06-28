<?php

namespace App\Services;

use App\DAO\ServiceAnalyticsInterface;
use App\DAO\ServiceProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ServiceAnalyticsService
{
    private const DEFAULT_PERIOD_DAYS = 30;

    public function __construct(
        private ServiceAnalyticsInterface $serviceAnalytics,
        private ServiceProviderInterface $serviceProvider,
    ) {}

    public function getDashboard(?string $fromInput, ?string $toInput): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $service = $this->serviceProvider->findServiceByProviderId((int) $userId);
        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        [$from, $to] = $this->resolvePeriod($fromInput, $toInput);

        if ($from->gt($to)) {
            return $this->fail('The from date must be before or equal to the to date', 422);
        }

        $data = $this->serviceAnalytics->getDashboardData($service->id, $from, $to);

        return $this->success('OK', [
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->copy()->startOfDay()->toDateString(),
                'days' => $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1,
            ],
            'kpis' => [
                'total_revenue' => $data['total_revenue'],
                'total_bookings' => $data['total_bookings'],
                'completed_bookings' => $data['completed_bookings'],
                'cancelled_bookings' => $data['cancelled_bookings'],
                'active_employees' => $data['active_employees'],
                'total_customers' => $data['total_customers'],
                'average_booking_value' => $data['total_bookings'] > 0
                    ? (int) round($data['total_revenue'] / $data['total_bookings'])
                    : 0,
                'service_completion_rate_percent' => $this->calculateCompletionRate(
                    $data['completed_bookings'],
                    $data['total_bookings']
                ),
                'customer_satisfaction_percent' => $this->calculateSatisfactionPercent($data['average_rating']),
                'average_rating' => $data['average_rating'],
                'ratings_count' => $data['ratings_count'],
                'revenue_growth_percent' => $this->calculateGrowthPercent(
                    $data['total_revenue'],
                    $data['previous_period_revenue']
                ),
            ],
            'booking_analytics' => [
                'bookings_over_time' => $data['bookings_over_time'],
                'bookings_by_status' => $data['bookings_by_status'],
            ],
            'revenue_analytics' => [
                'revenue_over_time' => $data['revenue_over_time'],
                'revenue_by_service' => $data['top_services'],
            ],
            'service_performance' => [
                'most_requested_services' => $data['top_services'],
                'least_requested_services' => $data['least_requested_services'],
            ],
            'employee_performance' => $data['employee_performance'],
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

    private function calculateCompletionRate(int $completedBookings, int $totalBookings): float
    {
        if ($totalBookings === 0) {
            return 0.0;
        }

        return round(($completedBookings / $totalBookings) * 100, 2);
    }

    private function calculateSatisfactionPercent(?float $averageRating): ?float
    {
        if ($averageRating === null) {
            return null;
        }

        return round(($averageRating / 5) * 100, 2);
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
