<?php

namespace App\Services;

use App\Models\Basket;
use App\Models\BasketItem;
use App\Models\Booking;
use App\Models\CustomerPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Support\ServiceEmployeeSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function checkout(array $data = []): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        return DB::transaction(function () use ($userId, $data) {
            $basket = Basket::query()
                ->where('userID', $userId)
                ->where('status', 'open')
                ->with([
                    'items.employee.workingDays',
                    'items.item' => function ($morphTo) {
                        $morphTo->morphWith([
                            ServiceItem::class => ['service.workingDays'],
                            ProductVariant::class => ['product'],
                        ]);
                    },
                ])
                ->lockForUpdate()
                ->first();

            if ($basket === null || $basket->items->isEmpty()) {
                return $this->fail('Basket is empty', 422);
            }

            $validationError = $this->validateBasketItems($basket);
            if ($validationError !== null) {
                return $this->fail($validationError, 422);
            }

            $paymentMethodId = isset($data['payment_method_id'])
                ? (int) $data['payment_method_id']
                : null;

            $paymentMethod = null;
            if ($paymentMethodId !== null) {
                $paymentMethod = PaymentMethod::find($paymentMethodId);
                if ($paymentMethod === null) {
                    return $this->fail('Payment method not found', 404);
                }
            }

            $isPaid = $paymentMethod !== null;
            $orderStatus = $isPaid ? 'confirmed' : 'pending';
            $bookingPaymentStatus = $isPaid ? 'paid' : 'unpaid';
            $bookingStatus = $isPaid ? 'confirmed' : 'pending';

            $order = Order::create([
                'basketID' => $basket->id,
                'userID' => $userId,
                'status' => $orderStatus,
                'totalPrice' => (int) $basket->totalPrice,
            ]);

            foreach ($basket->items as $line) {
                if ($line->lineType === BasketItem::LINE_TYPE_PRODUCT) {
                    $this->fulfillProductLine($order, $line);
                } else {
                    $this->fulfillServiceLine(
                        $order,
                        $line,
                        (int) $userId,
                        $bookingStatus,
                        $bookingPaymentStatus
                    );
                }
            }

            if ($isPaid) {
                CustomerPayment::create([
                    'customerID' => $userId,
                    'orderID' => $order->id,
                    'methodID' => $paymentMethod->id,
                    'price' => (int) $order->totalPrice,
                ]);
            }

            $basket->update(['status' => 'ordered']);

            $order->load([
                'items.store',
                'items.service',
                'items.employee',
                'customerPayment.method',
            ]);

            return $this->success('Order placed successfully', [
                'order' => $this->formatOrder($order),
            ], 201);
        });
    }

    public function getMyOrders(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $orders = Order::query()
            ->where('userID', $userId)
            ->with(['items.store', 'items.service', 'items.employee'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order) => $this->formatOrderSummary($order));

        return $this->success('OK', ['orders' => $orders]);
    }

    public function getOrder(int $orderId): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $order = Order::query()
            ->where('id', $orderId)
            ->where('userID', $userId)
            ->with(['items.store', 'items.service', 'items.employee', 'customerPayment.method'])
            ->first();

        if ($order === null) {
            return $this->fail('Order not found', 404);
        }

        return $this->success('OK', [
            'order' => $this->formatOrder($order),
        ]);
    }

    private function validateBasketItems(Basket $basket): ?string
    {
        foreach ($basket->items as $line) {
            if ($line->lineType === BasketItem::LINE_TYPE_PRODUCT) {
                $error = $this->validateProductLine($line);
            } else {
                $error = $this->validateServiceLine($line);
            }

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private function validateProductLine(BasketItem $line): ?string
    {
        if (! $line->item instanceof ProductVariant) {
            return 'A product in your basket is no longer available';
        }

        $variant = $line->item;
        if (($variant->status ?? 'active') !== 'active') {
            return "Product variant {$variant->sku} is no longer available";
        }

        $product = $variant->product;
        if ($product === null || $product->status !== 'active') {
            return "Product {$variant->sku} is no longer available";
        }

        if ($variant->availableQuantity() < (int) $line->quantity) {
            return "Insufficient stock for {$variant->sku}";
        }

        return null;
    }

    private function validateServiceLine(BasketItem $line): ?string
    {
        if (! $line->item instanceof ServiceItem) {
            return 'A service in your basket is no longer available';
        }

        $serviceItem = $line->item;
        if (! $serviceItem->isActive()) {
            return "Service item {$serviceItem->name} is no longer available";
        }

        $service = $serviceItem->service;
        if ($service === null) {
            return 'Service for a basket item was not found';
        }

        $employee = $line->employee;
        if ($employee === null) {
            return 'Employee for a service basket item was not found';
        }

        $date = ServiceEmployeeSchedule::normalizeDateString($line->scheduledDate);
        $time = ServiceEmployeeSchedule::formatTimeForApi($line->scheduledTime);

        if (ServiceEmployeeSchedule::parseAppointmentDateTime($date, $time)->isPast()) {
            return 'A service appointment in your basket is in the past';
        }

        $rejection = ServiceEmployeeSchedule::bookingRejectionReason(
            $service,
            $employee,
            $date,
            $time,
            (int) $serviceItem->duration
        );

        if ($rejection !== null) {
            return $rejection;
        }

        if ($this->slotTakenByBooking(
            (int) $employee->id,
            $date,
            $time,
            (int) $serviceItem->duration
        )) {
            return 'A service slot in your basket is no longer available';
        }

        return null;
    }

    private function fulfillProductLine(Order $order, BasketItem $line): void
    {
        /** @var ProductVariant $variant */
        $variant = $line->item;
        $product = $variant->product ?? Product::find($variant->productID);
        $quantity = (int) $line->quantity;
        $unitPrice = (int) $line->unitPrice;

        OrderItem::create([
            'orderID' => $order->id,
            'lineType' => OrderItem::LINE_TYPE_PRODUCT,
            'itemType' => ProductVariant::class,
            'itemID' => $variant->id,
            'storeID' => $variant->storeID,
            'quantity' => $quantity,
            'unitPrice' => $unitPrice,
            'lineTotal' => $quantity * $unitPrice,
            'sku' => $variant->sku,
            'itemName' => $product?->name ?? $variant->name,
            'variantName' => $variant->name,
        ]);

        $variant->decrement('quantity', $quantity);
    }

    private function fulfillServiceLine(
        Order $order,
        BasketItem $line,
        int $userId,
        string $bookingStatus,
        string $bookingPaymentStatus
    ): void {
        /** @var ServiceItem $serviceItem */
        $serviceItem = $line->item;
        $date = ServiceEmployeeSchedule::normalizeDateString($line->scheduledDate);
        $time = ServiceEmployeeSchedule::formatTimeForApi($line->scheduledTime);
        $unitPrice = (int) $line->unitPrice;

        $booking = Booking::create([
            'serviceID' => $serviceItem->serviceID,
            'serviceItemID' => $serviceItem->id,
            'customerID' => $userId,
            'employeeID' => $line->employeeID,
            'date' => $date,
            'time' => $time,
            'status' => $bookingStatus,
            'paymentStatus' => $bookingPaymentStatus,
            'totalPrice' => $unitPrice,
        ]);

        OrderItem::create([
            'orderID' => $order->id,
            'lineType' => OrderItem::LINE_TYPE_SERVICE,
            'itemType' => ServiceItem::class,
            'itemID' => $serviceItem->id,
            'serviceID' => $serviceItem->serviceID,
            'quantity' => 1,
            'unitPrice' => $unitPrice,
            'lineTotal' => $unitPrice,
            'itemName' => $serviceItem->name,
            'employeeID' => $line->employeeID,
            'scheduledDate' => $date,
            'scheduledTime' => $time,
        ]);

        Cache::forget("availability_{$serviceItem->id}_{$date}");
    }

    private function slotTakenByBooking(int $employeeId, string $date, string $time, int $duration): bool
    {
        $newStart = ServiceEmployeeSchedule::parseAppointmentDateTime($date, $time);
        $newEnd = (clone $newStart)->addMinutes($duration);

        $bookings = Booking::with('serviceItem')
            ->where('employeeID', $employeeId)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($bookings as $booking) {
            $existingStart = ServiceEmployeeSchedule::parseAppointmentDateTime($booking->date, $booking->time);
            $existingEnd = (clone $existingStart)
                ->addMinutes((int) ($booking->serviceItem?->duration ?? $duration));

            if ($newStart < $existingEnd && $newEnd > $existingStart) {
                return true;
            }
        }

        return false;
    }

    private function formatOrderSummary(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'total_price' => (int) $order->totalPrice,
            'item_count' => $order->items->count(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    private function formatOrder(Order $order): array
    {
        $items = $order->items->map(fn (OrderItem $item) => $this->formatOrderItem($item))->values();

        return [
            'id' => $order->id,
            'basket_id' => $order->basketID,
            'status' => $order->status,
            'total_price' => (int) $order->totalPrice,
            'item_count' => $items->count(),
            'created_at' => $order->created_at?->toIso8601String(),
            'payment' => $order->relationLoaded('customerPayment') && $order->customerPayment
                ? [
                    'method_id' => $order->customerPayment->methodID,
                    'method_name' => $order->customerPayment->method?->providerName,
                    'price' => (int) $order->customerPayment->price,
                ]
                : null,
            'items' => $items,
            'product_items' => $items->where('line_type', OrderItem::LINE_TYPE_PRODUCT)->values(),
            'service_items' => $items->where('line_type', OrderItem::LINE_TYPE_SERVICE)->values(),
            'store_groups' => $this->groupProductItems($items),
            'service_groups' => $this->groupServiceItems($items),
        ];
    }

    private function formatOrderItem(OrderItem $item): array
    {
        $payload = [
            'id' => $item->id,
            'line_type' => $item->lineType,
            'quantity' => (int) $item->quantity,
            'unit_price' => (int) $item->unitPrice,
            'line_total' => (int) $item->lineTotal,
            'item_name' => $item->itemName,
        ];

        if ($item->lineType === OrderItem::LINE_TYPE_PRODUCT) {
            $payload['store_id'] = $item->storeID;
            $payload['store_name'] = $item->store?->name;
            $payload['sku'] = $item->sku;
            $payload['variant_name'] = $item->variantName;
            $payload['product_variant_id'] = $item->itemID;
        }

        if ($item->lineType === OrderItem::LINE_TYPE_SERVICE) {
            $payload['service_id'] = $item->serviceID;
            $payload['service_name'] = $item->service?->name;
            $payload['service_item_id'] = $item->itemID;
            $payload['employee_id'] = $item->employeeID;
            $payload['employee_name'] = $item->employee?->name;
            $payload['scheduled_date'] = $item->scheduledDate?->toDateString();
            $payload['scheduled_time'] = ServiceEmployeeSchedule::formatTimeForApi($item->scheduledTime);
        }

        return $payload;
    }

    private function groupProductItems(Collection $items): array
    {
        return $items
            ->where('line_type', OrderItem::LINE_TYPE_PRODUCT)
            ->groupBy('store_id')
            ->map(function (Collection $group, $storeId) {
                $first = $group->first();

                return [
                    'store_id' => (int) $storeId,
                    'store_name' => $first['store_name'] ?? null,
                    'item_count' => $group->count(),
                    'subtotal' => (int) $group->sum('line_total'),
                    'items' => $group->values(),
                ];
            })
            ->values()
            ->all();
    }

    private function groupServiceItems(Collection $items): array
    {
        return $items
            ->where('line_type', OrderItem::LINE_TYPE_SERVICE)
            ->groupBy('service_id')
            ->map(function (Collection $group, $serviceId) {
                $first = $group->first();

                return [
                    'service_id' => (int) $serviceId,
                    'service_name' => $first['service_name'] ?? null,
                    'item_count' => $group->count(),
                    'subtotal' => (int) $group->sum('line_total'),
                    'items' => $group->values(),
                ];
            })
            ->values()
            ->all();
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
