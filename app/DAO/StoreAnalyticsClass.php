<?php

namespace App\DAO;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StoreAnalyticsClass implements StoreAnalyticsInterface
{
    public function getDashboardData(int $storeId, Carbon $from, Carbon $to): array
    {
        $totalRevenue = $this->sumStoreRevenue($storeId, $from, $to);
        $totalOrders = $this->countStoreOrders($storeId, $from, $to);
        $totalCustomers = $this->countStoreCustomers($storeId, $from, $to);
        $periodDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $previousFrom = $from->copy()->subDays($periodDays)->startOfDay();
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousPeriodRevenue = $this->sumStoreRevenue($storeId, $previousFrom, $previousTo);

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'total_products' => Product::query()->where('storeID', $storeId)->count(),
            'total_customers' => $totalCustomers,
            'average_order_value' => $totalOrders > 0 ? (int) round($totalRevenue / $totalOrders) : 0,
            'previous_period_revenue' => $previousPeriodRevenue,
            'revenue_over_time' => $this->getRevenueOverTime($storeId, $from, $to),
            'orders_over_time' => $this->getOrdersOverTime($storeId, $from, $to),
            'orders_by_status' => $this->getOrdersByStatus($storeId, $from, $to),
            'top_products' => $this->getTopProducts($storeId, $from, $to),
        ];
    }

    private function storeOrdersBaseQuery(int $storeId, Carbon $from, Carbon $to)
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->whereHas('items', function ($query) use ($storeId) {
                $query->where('storeID', $storeId)
                    ->where('lineType', OrderItem::LINE_TYPE_PRODUCT);
            });
    }

    private function storeItemsBaseQuery(int $storeId, Carbon $from, Carbon $to)
    {
        return OrderItem::query()
            ->where('storeID', $storeId)
            ->where('lineType', OrderItem::LINE_TYPE_PRODUCT)
            ->whereHas('order', function ($query) use ($from, $to) {
                $query->whereBetween('created_at', [$from, $to])
                    ->where('status', '!=', 'cancelled');
            });
    }

    private function sumStoreRevenue(int $storeId, Carbon $from, Carbon $to): int
    {
        return (int) $this->storeItemsBaseQuery($storeId, $from, $to)->sum('lineTotal');
    }

    private function countStoreOrders(int $storeId, Carbon $from, Carbon $to): int
    {
        return $this->storeOrdersBaseQuery($storeId, $from, $to)->count();
    }

    private function countStoreCustomers(int $storeId, Carbon $from, Carbon $to): int
    {
        return (int) $this->storeOrdersBaseQuery($storeId, $from, $to)
            ->distinct()
            ->count('userID');
    }

    private function getRevenueOverTime(int $storeId, Carbon $from, Carbon $to): array
    {
        $dateExpression = $this->dateExpression('orders.created_at');

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.orderID')
            ->where('order_items.storeID', $storeId)
            ->where('order_items.lineType', OrderItem::LINE_TYPE_PRODUCT)
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw("{$dateExpression} as date, SUM(order_items.lineTotal) as revenue")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillDailySeries($from, $to, $rows, 'revenue');
    }

    private function getOrdersOverTime(int $storeId, Carbon $from, Carbon $to): array
    {
        $dateExpression = $this->dateExpression('orders.created_at');

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.orderID')
            ->where('order_items.storeID', $storeId)
            ->where('order_items.lineType', OrderItem::LINE_TYPE_PRODUCT)
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw("{$dateExpression} as date, COUNT(DISTINCT orders.id) as orders")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillDailySeries($from, $to, $rows, 'orders');
    }

    private function getOrdersByStatus(int $storeId, Carbon $from, Carbon $to): array
    {
        return $this->storeOrdersBaseQuery($storeId, $from, $to)
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
     *     product_variant_id: int|null,
     *     name: string,
     *     quantity_sold: int,
     *     revenue: int
     * }>
     */
    private function getTopProducts(int $storeId, Carbon $from, Carbon $to): array
    {
        return $this->storeItemsBaseQuery($storeId, $from, $to)
            ->selectRaw('itemID, itemName, SUM(quantity) as quantity_sold, SUM(lineTotal) as revenue')
            ->groupBy('itemID', 'itemName')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'product_variant_id' => $row->itemID !== null ? (int) $row->itemID : null,
                'name' => (string) $row->itemName,
                'quantity_sold' => (int) $row->quantity_sold,
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

    private function dateExpression(string $column): string
    {
        return "DATE({$column})";
    }
}
