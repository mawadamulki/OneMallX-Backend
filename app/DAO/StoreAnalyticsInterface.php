<?php

namespace App\DAO;

use Carbon\Carbon;

interface StoreAnalyticsInterface
{
    /**
     * @return array{
     *     total_revenue: int,
     *     total_orders: int,
     *     total_products: int,
     *     total_customers: int,
     *     average_order_value: int,
     *     previous_period_revenue: int,
     *     revenue_over_time: array<int, array{date: string, revenue: int}>,
     *     orders_over_time: array<int, array{date: string, orders: int}>,
     *     orders_by_status: array<int, array{status: string, count: int}>,
     *     top_products: array<int, array{
     *         product_variant_id: int|null,
     *         name: string,
     *         quantity_sold: int,
     *         revenue: int
     *     }>
     * }
     */
    public function getDashboardData(int $storeId, Carbon $from, Carbon $to): array;
}
