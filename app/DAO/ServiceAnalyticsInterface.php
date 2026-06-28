<?php

namespace App\DAO;

use Carbon\Carbon;

interface ServiceAnalyticsInterface
{
    /**
     * @return array{
     *     total_revenue: int,
     *     total_bookings: int,
     *     completed_bookings: int,
     *     cancelled_bookings: int,
     *     active_employees: int,
     *     total_customers: int,
     *     average_rating: float|null,
     *     ratings_count: int,
     *     previous_period_revenue: int,
     *     bookings_over_time: array<int, array{date: string, bookings: int}>,
     *     revenue_over_time: array<int, array{date: string, revenue: int}>,
     *     bookings_by_status: array<int, array{status: string, count: int}>,
     *     top_services: array<int, array{
     *         service_item_id: int,
     *         name: string,
     *         bookings_count: int,
     *         revenue: int
     *     }>,
     *     least_requested_services: array<int, array{
     *         service_item_id: int,
     *         name: string,
     *         bookings_count: int,
     *         revenue: int
     *     }>,
     *     employee_performance: array<int, array{
     *         employee_id: int|null,
     *         name: string,
     *         bookings_count: int,
     *         completed_bookings: int,
     *         revenue: int
     *     }>
     * }
     */
    public function getDashboardData(int $serviceId, Carbon $from, Carbon $to): array;
}
