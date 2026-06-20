<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;

class StoreOrderService
{
    public function getStoreOrders(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $store = $this->findOwnedStore((int) $userId);
        if ($store === null) {
            return $this->fail('Store not found', 404);
        }

        $orders = Order::query()
            ->whereHas('items', function ($query) use ($store) {
                $query->where('storeID', $store->id)
                    ->where('lineType', OrderItem::LINE_TYPE_PRODUCT);
            })
            ->with(['user', 'items' => function ($query) use ($store) {
                $query->where('storeID', $store->id)
                    ->where('lineType', OrderItem::LINE_TYPE_PRODUCT);
            }])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order) => $this->formatStoreOrderSummary($order, $store));

        return $this->success('OK', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
            ],
            'orders' => $orders,
        ]);
    }

    public function getStoreOrder(int $orderId): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $store = $this->findOwnedStore((int) $userId);
        if ($store === null) {
            return $this->fail('Store not found', 404);
        }

        $order = Order::query()
            ->whereKey($orderId)
            ->whereHas('items', function ($query) use ($store) {
                $query->where('storeID', $store->id)
                    ->where('lineType', OrderItem::LINE_TYPE_PRODUCT);
            })
            ->with(['user', 'items' => function ($query) use ($store) {
                $query->where('storeID', $store->id)
                    ->where('lineType', OrderItem::LINE_TYPE_PRODUCT);
            }])
            ->first();

        if ($order === null) {
            return $this->fail('Order not found', 404);
        }

        return $this->success('OK', [
            'order' => $this->formatStoreOrderDetail($order, $store),
        ]);
    }

    private function findOwnedStore(int $userId): ?Store
    {
        return Store::query()
            ->where('storeOwnerID', $userId)
            ->first();
    }

    private function formatStoreOrderSummary(Order $order, Store $store): array
    {
        $items = $order->items;
        $storeSubtotal = (int) $items->sum('lineTotal');

        return [
            'id' => $order->id,
            'status' => $order->status,
            'store_subtotal' => $storeSubtotal,
            'item_count' => $items->count(),
            'order_total_price' => (int) $order->totalPrice,
            'created_at' => $order->created_at?->toIso8601String(),
            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
            ],
            'items' => $items->map(fn (OrderItem $item) => $this->formatOrderItem($item))->values(),
        ];
    }

    private function formatStoreOrderDetail(Order $order, Store $store): array
    {
        $items = $order->items->map(fn (OrderItem $item) => $this->formatOrderItem($item))->values();
        $storeSubtotal = (int) $order->items->sum('lineTotal');

        return [
            'id' => $order->id,
            'status' => $order->status,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'store_subtotal' => $storeSubtotal,
            'order_total_price' => (int) $order->totalPrice,
            'item_count' => $items->count(),
            'created_at' => $order->created_at?->toIso8601String(),
            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'phone_number' => $order->user?->phoneNumber,
            ],
            'items' => $items,
        ];
    }

    private function formatOrderItem(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'line_type' => $item->lineType,
            'quantity' => (int) $item->quantity,
            'unit_price' => (int) $item->unitPrice,
            'line_total' => (int) $item->lineTotal,
            'item_name' => $item->itemName,
            'variant_name' => $item->variantName,
            'sku' => $item->sku,
            'product_variant_id' => $item->itemID,
            'store_id' => $item->storeID,
        ];
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
