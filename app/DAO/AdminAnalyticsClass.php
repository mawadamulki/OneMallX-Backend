<?php

namespace App\DAO;

use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Rate;
use App\Models\RateReport;
use App\Models\Service;
use App\Models\ServiceSubscriptionPayment;
use App\Models\Store;
use App\Models\StoreSubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AdminAnalyticsClass implements AdminAnalyticsInterface
{
    public function getDashboardData(Carbon $from, Carbon $to): array
    {
        $periodDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $previousFrom = $from->copy()->subDays($periodDays)->startOfDay();
        $previousTo = $from->copy()->subDay()->endOfDay();

        $platformRevenue = $this->sumPlatformRevenue($from, $to);
        $previousPlatformRevenue = $this->sumPlatformRevenue($previousFrom, $previousTo);

        return [
            'users_by_role' => $this->getUsersByRole(),
            'total_users' => User::query()->count(),
            'active_users' => User::query()->where('status', 'active')->count(),
            'total_stores' => Store::query()->count(),
            'total_services' => Service::query()->count(),
            'active_stores' => Store::query()->where('accountStatus', 'active')->count(),
            'inactive_stores' => Store::query()->where('accountStatus', '!=', 'active')->count(),
            'active_services' => Service::query()->where('accountStatus', 'active')->count(),
            'inactive_services' => Service::query()->where('accountStatus', '!=', 'active')->count(),
            'total_orders' => Order::query()->where('status', '!=', 'cancelled')->count(),
            'total_bookings' => Booking::query()->where('status', '!=', 'cancelled')->count(),
            'orders_in_period' => $this->countOrdersInPeriod($from, $to),
            'bookings_in_period' => $this->countBookingsInPeriod($from, $to),
            'platform_revenue' => $platformRevenue,
            'previous_platform_revenue' => $previousPlatformRevenue,
            'average_platform_rating' => $this->getAveragePlatformRating(),
            'ratings_count' => Rate::query()->count(),
            'user_registrations_over_time' => $this->getUserRegistrationsOverTime($from, $to),
            'orders_over_time' => $this->getOrdersOverTime($from, $to),
            'bookings_over_time' => $this->getBookingsOverTime($from, $to),
            'orders_by_status' => $this->getOrdersByStatus($from, $to),
            'bookings_by_status' => $this->getBookingsByStatus($from, $to),
            'platform_revenue_over_time' => $this->getPlatformRevenueOverTime($from, $to),
            'top_stores' => $this->getTopStores($from, $to),
            'top_services' => $this->getTopServices($from, $to),
            'stores_by_area_category' => $this->getStoresByAreaCategory(),
            'services_by_area_category' => $this->getServicesByAreaCategory(),
            'moderation_summary' => $this->getModerationSummary($from, $to),
            'reports_by_status' => $this->getReportsByStatus(),
        ];
    }

    private function getUsersByRole(): array
    {
        return Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'role' => $role->name,
                'count' => (int) $role->users_count,
            ])
            ->values()
            ->all();
    }

    private function ordersInPeriodQuery(Carbon $from, Carbon $to)
    {
        return Order::query()
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled');
    }

    private function bookingsInPeriodQuery(Carbon $from, Carbon $to, bool $excludeCancelled = true)
    {
        return Booking::query()
            ->whereBetween('bookings.created_at', [$from, $to])
            ->when($excludeCancelled, fn ($query) => $query->where('bookings.status', '!=', 'cancelled'));
    }

    private function countOrdersInPeriod(Carbon $from, Carbon $to): int
    {
        return $this->ordersInPeriodQuery($from, $to)->count();
    }

    private function countBookingsInPeriod(Carbon $from, Carbon $to): int
    {
        return $this->bookingsInPeriodQuery($from, $to)->count();
    }

    private function sumPlatformRevenue(Carbon $from, Carbon $to): int
    {
        $storeRevenue = (int) StoreSubscriptionPayment::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('price');

        $serviceRevenue = (int) ServiceSubscriptionPayment::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('price');

        return $storeRevenue + $serviceRevenue;
    }

    private function getAveragePlatformRating(): ?float
    {
        $average = Rate::query()->avg('score');

        return $average !== null ? round((float) $average, 2) : null;
    }

    private function getUserRegistrationsOverTime(Carbon $from, Carbon $to): array
    {
        $rows = User::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as registrations')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillDailySeries($from, $to, $rows, 'registrations');
    }

    private function getOrdersOverTime(Carbon $from, Carbon $to): array
    {
        $rows = $this->ordersInPeriodQuery($from, $to)
            ->selectRaw('DATE(orders.created_at) as date, COUNT(*) as orders')
            ->groupByRaw('DATE(orders.created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillDailySeries($from, $to, $rows, 'orders');
    }

    private function getBookingsOverTime(Carbon $from, Carbon $to): array
    {
        $rows = $this->bookingsInPeriodQuery($from, $to)
            ->selectRaw('DATE(bookings.created_at) as date, COUNT(*) as bookings')
            ->groupByRaw('DATE(bookings.created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillDailySeries($from, $to, $rows, 'bookings');
    }

    private function getOrdersByStatus(Carbon $from, Carbon $to): array
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
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

    private function getBookingsByStatus(Carbon $from, Carbon $to): array
    {
        return Booking::query()
            ->whereBetween('created_at', [$from, $to])
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

    private function getPlatformRevenueOverTime(Carbon $from, Carbon $to): array
    {
        $storeRows = StoreSubscriptionPayment::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, SUM(price) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->pluck('revenue', 'date');

        $serviceRows = ServiceSubscriptionPayment::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, SUM(price) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->pluck('revenue', 'date');

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $series[] = [
                'date' => $date,
                'revenue' => (int) ($storeRows[$date] ?? 0) + (int) ($serviceRows[$date] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    private function getTopStores(Carbon $from, Carbon $to): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.orderID')
            ->join('stores', 'stores.id', '=', 'order_items.storeID')
            ->where('order_items.lineType', OrderItem::LINE_TYPE_PRODUCT)
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->whereNull('stores.deleted_at')
            ->selectRaw('
                stores.id as store_id,
                stores.name as name,
                SUM(order_items.lineTotal) as revenue,
                COUNT(DISTINCT orders.id) as orders_count
            ')
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'store_id' => (int) $row->store_id,
                'name' => (string) $row->name,
                'revenue' => (int) $row->revenue,
                'orders_count' => (int) $row->orders_count,
            ])
            ->values()
            ->all();
    }

    private function getTopServices(Carbon $from, Carbon $to): array
    {
        return DB::table('bookings')
            ->join('services', 'services.id', '=', 'bookings.serviceID')
            ->whereBetween('bookings.created_at', [$from, $to])
            ->where('bookings.status', '!=', 'cancelled')
            ->whereNull('bookings.deleted_at')
            ->whereNull('services.deleted_at')
            ->selectRaw('
                services.id as service_id,
                services.name as name,
                SUM(bookings.totalPrice) as revenue,
                COUNT(*) as bookings_count
            ')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'service_id' => (int) $row->service_id,
                'name' => (string) $row->name,
                'revenue' => (int) $row->revenue,
                'bookings_count' => (int) $row->bookings_count,
            ])
            ->values()
            ->all();
    }

    private function getStoresByAreaCategory(): array
    {
        return DB::table('stores')
            ->join('areas', 'areas.id', '=', 'stores.areaID')
            ->whereNull('stores.deleted_at')
            ->selectRaw('areas.category as category, COUNT(*) as count')
            ->groupBy('areas.category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function getServicesByAreaCategory(): array
    {
        return DB::table('services')
            ->join('areas', 'areas.id', '=', 'services.areaID')
            ->whereNull('services.deleted_at')
            ->selectRaw('areas.category as category, COUNT(*) as count')
            ->groupBy('areas.category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function getModerationSummary(Carbon $from, Carbon $to): array
    {
        return [
            'pending_reports' => RateReport::query()->where('status', RateReport::STATUS_PENDING)->count(),
            'resolved_reports' => RateReport::query()
                ->whereIn('status', [RateReport::STATUS_DISMISSED, RateReport::STATUS_ACTION_TAKEN])
                ->count(),
            'reports_in_period' => RateReport::query()
                ->whereBetween('created_at', [$from, $to])
                ->count(),
        ];
    }

    private function getReportsByStatus(): array
    {
        return RateReport::query()
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
