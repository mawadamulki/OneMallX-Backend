<?php

namespace App\Services;

use App\Models\Basket;
use App\Models\BasketItem;
use App\Models\Booking;
use App\Models\CustomerPayment;
use App\Models\Location;
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

            $unavailableItems = $this->collectUnavailableBasketItems($basket);
            if ($unavailableItems !== []) {
                return $this->fail(
                    'Some items in your basket are no longer available',
                    422,
                    ['unavailable_items' => $unavailableItems]
                );
            }

            $locationText = trim((string) ($data['location'] ?? ''));

            if ($locationText === '') {
                return $this->fail('Delivery location is required', 422);
            }

            $locationId = Location::query()->create(['location' => $locationText])->id;

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
                'locationID' => $locationId,
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
                'location',
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
            ->with(['location', 'items.store', 'items.service', 'items.employee'])
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
            ->with(['location', 'items.store', 'items.service', 'items.employee', 'customerPayment.method'])
            ->first();

        if ($order === null) {
            return $this->fail('Order not found', 404);
        }

        return $this->success('OK', [
            'order' => $this->formatOrder($order),
        ]);
    }

    /**
     * @return list<array{basket_item_id: int, line_type: string, reason: string, reason_code: string, item: array<string, mixed>}>
     */
    private function collectUnavailableBasketItems(Basket $basket): array
    {
        $unavailable = [];

        foreach ($basket->items as $line) {
            $issue = $line->lineType === BasketItem::LINE_TYPE_PRODUCT
                ? $this->validateProductLine($line)
                : $this->validateServiceLine($line);

            if ($issue !== null) {
                $unavailable[] = [
                    'basket_item_id' => $line->id,
                    'line_type' => $line->lineType,
                    'reason' => $issue['message'],
                    'reason_code' => $issue['code'],
                    'item' => $this->formatBasketLine($line),
                ];
            }
        }

        return $unavailable;
    }

    /**
     * @return array{message: string, code: string}|null
     */
    private function validateProductLine(BasketItem $line): ?array
    {
        if (! $line->item instanceof ProductVariant) {
            return [
                'message' => 'A product in your basket is no longer available',
                'code' => 'unavailable',
            ];
        }

        $variant = $line->item;
        if (($variant->status ?? 'active') !== 'active') {
            return [
                'message' => "Product variant {$variant->sku} is no longer available",
                'code' => 'inactive_variant',
            ];
        }

        $product = $variant->product;
        if ($product === null || $product->status !== 'active') {
            return [
                'message' => "Product {$variant->sku} is no longer available",
                'code' => 'inactive_product',
            ];
        }

        if ($variant->availableQuantity() < (int) $line->quantity) {
            return [
                'message' => "Insufficient stock for {$variant->sku}",
                'code' => 'insufficient_stock',
            ];
        }

        return null;
    }

    /**
     * @return array{message: string, code: string}|null
     */
    private function validateServiceLine(BasketItem $line): ?array
    {
        if (! $line->item instanceof ServiceItem) {
            return [
                'message' => 'A service in your basket is no longer available',
                'code' => 'unavailable',
            ];
        }

        $serviceItem = $line->item;
        if (! $serviceItem->isActive()) {
            return [
                'message' => "Service item {$serviceItem->name} is no longer available",
                'code' => 'inactive_service_item',
            ];
        }

        $service = $serviceItem->service;
        if ($service === null) {
            return [
                'message' => 'Service for a basket item was not found',
                'code' => 'service_not_found',
            ];
        }

        $employee = $line->employee;
        if ($employee === null) {
            return [
                'message' => 'Employee for a service basket item was not found',
                'code' => 'employee_not_found',
            ];
        }

        $date = ServiceEmployeeSchedule::normalizeDateString($line->scheduledDate);
        $time = ServiceEmployeeSchedule::formatTimeForApi($line->scheduledTime);

        if (ServiceEmployeeSchedule::parseAppointmentDateTime($date, $time)->isPast()) {
            return [
                'message' => 'A service appointment in your basket is in the past',
                'code' => 'appointment_past',
            ];
        }

        $rejection = ServiceEmployeeSchedule::bookingRejectionReason(
            $service,
            $employee,
            $date,
            $time,
            (int) $serviceItem->duration
        );

        if ($rejection !== null) {
            return [
                'message' => $rejection,
                'code' => 'schedule_invalid',
            ];
        }

        if ($this->slotTakenByBooking(
            (int) $employee->id,
            $date,
            $time,
            (int) $serviceItem->duration
        )) {
            return [
                'message' => 'This appointment slot was already booked by someone else',
                'code' => 'appointment_taken',
            ];
        }

        return null;
    }

    private function formatBasketLine(BasketItem $line): array
    {
        $payload = [
            'id' => $line->id,
            'line_type' => $line->lineType,
            'quantity' => (int) $line->quantity,
            'unit_price' => (int) $line->unitPrice,
            'line_total' => (int) $line->quantity * (int) $line->unitPrice,
        ];

        if ($line->lineType === BasketItem::LINE_TYPE_PRODUCT && $line->item instanceof ProductVariant) {
            $variant = $line->item;
            $product = $variant->relationLoaded('product') ? $variant->product : Product::find($variant->productID);

            $payload['product_variant_id'] = $variant->id;
            $payload['product_id'] = $variant->productID;
            $payload['store_id'] = $variant->storeID;
            $payload['sku'] = $variant->sku;
            $payload['product_name'] = $product?->name;
            $payload['variant_name'] = $variant->name;
        }

        if ($line->lineType === BasketItem::LINE_TYPE_SERVICE && $line->item instanceof ServiceItem) {
            $serviceItem = $line->item;
            $service = $serviceItem->relationLoaded('service')
                ? $serviceItem->service
                : Service::find($serviceItem->serviceID);

            $payload['service_item_id'] = $serviceItem->id;
            $payload['service_id'] = $serviceItem->serviceID;
            $payload['service_name'] = $service?->name;
            $payload['item_name'] = $serviceItem->name;
            $payload['employee_id'] = $line->employeeID;
            $payload['employee_name'] = $line->employee?->name;
            $payload['scheduled_date'] = $line->scheduledDate?->toDateString();
            $payload['scheduled_time'] = ServiceEmployeeSchedule::formatTimeForApi($line->scheduledTime);
        }

        return $payload;
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
            'location' => $this->formatLocation($order),
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
            'location' => $this->formatLocation($order),
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

    private function formatLocation(Order $order): ?array
    {
        if ($order->location === null) {
            return null;
        }

        return [
            'id' => $order->location->id,
            'location' => $order->location->location,
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

    private function fail(string $message, int $httpStatus = 422, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'message' => $message,
            'http_status' => $httpStatus,
        ], $extra);
    }
}
