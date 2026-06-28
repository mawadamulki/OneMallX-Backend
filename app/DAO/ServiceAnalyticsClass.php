<?php

namespace App\DAO;

use App\Models\Booking;
use App\Models\Employee;
use App\Models\Rate;
use App\Models\Service;
use Carbon\Carbon;

class ServiceAnalyticsClass implements ServiceAnalyticsInterface
{
    public function getDashboardData(int $serviceId, Carbon $from, Carbon $to): array
    {
        $periodDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $previousFrom = $from->copy()->subDays($periodDays)->startOfDay();
        $previousTo = $from->copy()->subDay()->endOfDay();

        $totalBookings = $this->countBookings($serviceId, $from, $to, excludeCancelled: true);
        $totalRevenue = $this->sumRevenue($serviceId, $from, $to);
        $completedBookings = $this->countBookingsByStatus($serviceId, $from, $to, 'completed');
        $cancelledBookings = $this->countBookingsByStatus($serviceId, $from, $to, 'cancelled');
        [$averageRating, $ratingsCount] = $this->getServiceRatingSummary($serviceId);

        return [
            'total_revenue' => $totalRevenue,
            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'active_employees' => $this->countActiveEmployees($serviceId),
            'total_customers' => $this->countCustomers($serviceId, $from, $to),
            'average_rating' => $averageRating,
            'ratings_count' => $ratingsCount,
            'previous_period_revenue' => $this->sumRevenue($serviceId, $previousFrom, $previousTo),
            'bookings_over_time' => $this->getBookingsOverTime($serviceId, $from, $to),
            'revenue_over_time' => $this->getRevenueOverTime($serviceId, $from, $to),
            'bookings_by_status' => $this->getBookingsByStatus($serviceId, $from, $to),
            'top_services' => $this->getServiceItemPerformance($serviceId, $from, $to, 'desc'),
            'least_requested_services' => $this->getServiceItemPerformance($serviceId, $from, $to, 'asc'),
            'employee_performance' => $this->getEmployeePerformance($serviceId, $from, $to),
        ];
    }

    private function bookingsInPeriodQuery(int $serviceId, Carbon $from, Carbon $to, bool $excludeCancelled = false)
    {
        return Booking::query()
            ->where('bookings.serviceID', $serviceId)
            ->whereDate('bookings.date', '>=', $from->toDateString())
            ->whereDate('bookings.date', '<=', $to->toDateString())
            ->when($excludeCancelled, fn ($query) => $query->where('bookings.status', '!=', 'cancelled'));
    }

    private function countBookings(int $serviceId, Carbon $from, Carbon $to, bool $excludeCancelled = false): int
    {
        return $this->bookingsInPeriodQuery($serviceId, $from, $to, $excludeCancelled)->count();
    }

    private function countBookingsByStatus(int $serviceId, Carbon $from, Carbon $to, string $status): int
    {
        return Booking::query()
            ->where('serviceID', $serviceId)
            ->where('status', $status)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->count();
    }

    private function sumRevenue(int $serviceId, Carbon $from, Carbon $to): int
    {
        return (int) $this->bookingsInPeriodQuery($serviceId, $from, $to, excludeCancelled: true)
            ->sum('bookings.totalPrice');
    }

    private function countCustomers(int $serviceId, Carbon $from, Carbon $to): int
    {
        return (int) $this->bookingsInPeriodQuery($serviceId, $from, $to, excludeCancelled: true)
            ->distinct()
            ->count('bookings.customerID');
    }

    private function countActiveEmployees(int $serviceId): int
    {
        return Employee::query()
            ->where('serviceID', $serviceId)
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhereNull('status');
            })
            ->count();
    }

    /**
     * @return array{0: float|null, 1: int}
     */
    private function getServiceRatingSummary(int $serviceId): array
    {
        $summary = Rate::query()
            ->where('rateableType', Service::class)
            ->where('rateableID', $serviceId)
            ->selectRaw('AVG(score) as average_rating, COUNT(*) as ratings_count')
            ->first();

        $count = (int) ($summary->ratings_count ?? 0);

        return [
            $count > 0 ? round((float) $summary->average_rating, 2) : null,
            $count,
        ];
    }

    private function getBookingsOverTime(int $serviceId, Carbon $from, Carbon $to): array
    {
        $rows = $this->bookingsInPeriodQuery($serviceId, $from, $to, excludeCancelled: true)
            ->selectRaw('DATE(bookings.date) as date, COUNT(*) as bookings')
            ->groupByRaw('DATE(bookings.date)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillDailySeries($from, $to, $rows, 'bookings');
    }

    private function getRevenueOverTime(int $serviceId, Carbon $from, Carbon $to): array
    {
        $rows = $this->bookingsInPeriodQuery($serviceId, $from, $to, excludeCancelled: true)
            ->selectRaw('DATE(bookings.date) as date, SUM(bookings.totalPrice) as revenue')
            ->groupByRaw('DATE(bookings.date)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillDailySeries($from, $to, $rows, 'revenue');
    }

    private function getBookingsByStatus(int $serviceId, Carbon $from, Carbon $to): array
    {
        return Booking::query()
            ->where('serviceID', $serviceId)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'status' => (string) $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     service_item_id: int,
     *     name: string,
     *     bookings_count: int,
     *     revenue: int
     * }>
     */
    private function getServiceItemPerformance(int $serviceId, Carbon $from, Carbon $to, string $direction): array
    {
        $rows = $this->bookingsInPeriodQuery($serviceId, $from, $to, excludeCancelled: true)
            ->join('service_items', 'service_items.id', '=', 'bookings.serviceItemID')
            ->selectRaw('
                bookings.serviceItemID as service_item_id,
                service_items.name as name,
                COUNT(*) as bookings_count,
                SUM(bookings.totalPrice) as revenue
            ')
            ->groupBy('bookings.serviceItemID', 'service_items.name')
            ->orderBy('bookings_count', $direction)
            ->limit(5)
            ->get();

        return $rows->map(fn ($row) => [
            'service_item_id' => (int) $row->service_item_id,
            'name' => (string) $row->name,
            'bookings_count' => (int) $row->bookings_count,
            'revenue' => (int) $row->revenue,
        ])->values()->all();
    }

    /**
     * @return array<int, array{
     *     employee_id: int|null,
     *     name: string,
     *     bookings_count: int,
     *     completed_bookings: int,
     *     revenue: int
     * }>
     */
    private function getEmployeePerformance(int $serviceId, Carbon $from, Carbon $to): array
    {
        return $this->bookingsInPeriodQuery($serviceId, $from, $to, excludeCancelled: true)
            ->leftJoin('employees', 'employees.id', '=', 'bookings.employeeID')
            ->selectRaw('
                bookings.employeeID as employee_id,
                COALESCE(employees.name, ?) as name,
                COUNT(*) as bookings_count,
                SUM(CASE WHEN bookings.status = ? THEN 1 ELSE 0 END) as completed_bookings,
                SUM(bookings.totalPrice) as revenue
            ', ['Unassigned', 'completed'])
            ->groupBy('bookings.employeeID', 'employees.name')
            ->orderByDesc('bookings_count')
            ->get()
            ->map(fn ($row) => [
                'employee_id' => $row->employee_id !== null ? (int) $row->employee_id : null,
                'name' => (string) $row->name,
                'bookings_count' => (int) $row->bookings_count,
                'completed_bookings' => (int) $row->completed_bookings,
                'revenue' => (int) $row->revenue,
            ])
            ->values()
            ->all();
    }

    private function fillDailySeries(Carbon $from, Carbon $to, $rows, string $valueKey): array
    {
        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);

            $series[] = [
                'date' => $date,
                $valueKey => $row !== null ? (int) $row->{$valueKey} : 0,
            ];

            $cursor->addDay();
        }

        return $series;
    }
}
