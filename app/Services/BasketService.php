<?php

namespace App\Services;

use App\Models\Basket;
use App\Models\BasketItem;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Support\ServiceEmployeeSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BasketService
{
    public function getBasket(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $basket = $this->findOpenBasket((int) $userId);

        if ($basket === null) {
            return $this->success('OK', [
                'basket' => $this->emptyBasketPayload(),
            ]);
        }

        $basket->load($this->basketRelations());

        return $this->success('OK', [
            'basket' => $this->formatBasket($basket),
        ]);
    }

    public function addItem(array $data): array
    {
        return match ($data['line_type']) {
            BasketItem::LINE_TYPE_SERVICE => $this->addServiceItem($data),
            BasketItem::LINE_TYPE_PRODUCT => $this->addProductItem($data),
            default => $this->fail('Invalid line type', 422),
        };
    }

    public function updateItem(int $itemId, array $data): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        return DB::transaction(function () use ($userId, $itemId, $data) {
            $basket = $this->getOrCreateOpenBasket($userId);
            $item = $basket->items()->whereKey($itemId)->first();

            if ($item === null) {
                return $this->fail('Basket item not found', 404);
            }

            if ($item->lineType === BasketItem::LINE_TYPE_SERVICE) {
                return $this->fail('Service items cannot change quantity', 422);
            }

            $quantity = (int) $data['quantity'];
            if ($quantity < 1) {
                return $this->fail('Quantity must be at least 1', 422);
            }

            /** @var ProductVariant|null $variant */
            $variant = $item->item;
            if (! $variant instanceof ProductVariant) {
                return $this->fail('Product variant not found', 404);
            }

            if ($variant->availableQuantity() < $quantity) {
                return $this->fail('Insufficient stock for this variant', 422);
            }

            $item->update(['quantity' => $quantity]);
            $this->recalculateTotal($basket);

            $basket->load($this->basketRelations());

            return $this->success('Basket item updated', [
                'basket' => $this->formatBasket($basket),
            ]);
        });
    }

    public function removeItem(int $itemId): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        return DB::transaction(function () use ($userId, $itemId) {
            $basket = $this->findOpenBasket($userId);
            if ($basket === null) {
                return $this->fail('Basket not found', 404);
            }

            $item = $basket->items()->whereKey($itemId)->first();
            if ($item === null) {
                return $this->fail('Basket item not found', 404);
            }

            $item->delete();
            $this->recalculateTotal($basket);

            $basket->load($this->basketRelations());

            return $this->success('Basket item removed', [
                'basket' => $this->formatBasket($basket),
            ]);
        });
    }

    public function clearBasket(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        return DB::transaction(function () use ($userId) {
            $basket = $this->findOpenBasket($userId);
            if ($basket === null) {
                return $this->success('Basket cleared', [
                    'basket' => $this->emptyBasketPayload(),
                ]);
            }

            $basket->items()->delete();
            $basket->update(['totalPrice' => 0]);

            return $this->success('Basket cleared', [
                'basket' => $this->formatBasket($basket->fresh()->load($this->basketRelations())),
            ]);
        });
    }

    private function addServiceItem(array $data): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        return DB::transaction(function () use ($userId, $data) {
            $service = Service::with('workingDays')->find($data['service_id']);
            if (! $service) {
                return $this->fail('Service not found', 404);
            }

            $item = ServiceItem::with(['employees', 'service.workingDays'])->find($data['service_item_id']);
            if (! $item || ! $item->isActive()) {
                return $this->fail('Service item not found', 404);
            }

            if ((int) $item->serviceID !== (int) $service->id) {
                return $this->fail('Service item does not belong to this service', 422);
            }

            $employee = Employee::with('workingDays')->find($data['employee_id']);
            if (! $employee) {
                return $this->fail('Employee not found', 404);
            }

            if ((int) $employee->serviceID !== (int) $service->id) {
                return $this->fail('Employee does not belong to this service', 422);
            }

            if (! $item->employees->contains('id', $employee->id)) {
                return $this->fail('Employee not assigned to this service item', 422);
            }

            $newStart = Carbon::parse($data['date'].' '.$data['time']);
            if ($newStart->isPast()) {
                return $this->fail('Cannot add a past appointment to the basket', 422);
            }

            $rejection = ServiceEmployeeSchedule::bookingRejectionReason(
                $service,
                $employee,
                $data['date'],
                $data['time'],
                (int) $item->duration
            );

            if ($rejection !== null) {
                return $this->fail($rejection, 422);
            }

            if ($this->slotTakenByBooking($employee->id, $data['date'], $data['time'], (int) $item->duration)) {
                return $this->fail('Time overlaps with an existing booking', 409);
            }

            $basket = $this->getOrCreateOpenBasket($userId);

            if ($this->slotTakenInBasket($basket, $employee->id, $data['date'], $data['time'], (int) $item->duration)) {
                return $this->fail('This slot is already in your basket', 409);
            }

            $lineKey = BasketItem::buildLineKey(
                BasketItem::LINE_TYPE_SERVICE,
                ServiceItem::class,
                (int) $item->id,
                (int) $employee->id,
                $data['date'],
                $data['time']
            );

            if ($basket->items()->where('lineKey', $lineKey)->exists()) {
                return $this->fail('This service slot is already in your basket', 409);
            }

            $unitPrice = $item->priceForEmployee((int) $employee->id);

            $basket->items()->create([
                'lineType' => BasketItem::LINE_TYPE_SERVICE,
                'lineKey' => $lineKey,
                'itemType' => ServiceItem::class,
                'itemID' => $item->id,
                'quantity' => 1,
                'unitPrice' => $unitPrice,
                'employeeID' => $employee->id,
                'scheduledDate' => $data['date'],
                'scheduledTime' => $data['time'],
            ]);

            $this->recalculateTotal($basket);
            $basket->load($this->basketRelations());

            return $this->success('Service added to basket', [
                'basket' => $this->formatBasket($basket),
            ], 201);
        });
    }

    private function addProductItem(array $data): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $quantity = (int) $data['quantity'];

        return DB::transaction(function () use ($userId, $quantity, $data) {
            $variant = ProductVariant::with('product.store')->find($data['product_variant_id']);
            if ($variant === null) {
                return $this->fail('Product variant not found', 404);
            }

            if (($variant->status ?? 'active') !== 'active') {
                return $this->fail('Product variant is not available', 422);
            }

            $product = $variant->product;
            if ($product === null || $product->status !== 'active') {
                return $this->fail('Product is not available', 422);
            }

            if ($variant->availableQuantity() < $quantity) {
                return $this->fail('Insufficient stock for this variant', 422);
            }

            $basket = $this->getOrCreateOpenBasket($userId);
            $lineKey = BasketItem::buildLineKey(
                BasketItem::LINE_TYPE_PRODUCT,
                ProductVariant::class,
                (int) $variant->id
            );

            $existing = $basket->items()->where('lineKey', $lineKey)->first();
            $newQuantity = $existing ? ((int) $existing->quantity + $quantity) : $quantity;

            if ($variant->availableQuantity() < $newQuantity) {
                return $this->fail('Insufficient stock for this variant', 422);
            }

            if ($existing) {
                $existing->update(['quantity' => $newQuantity]);
            } else {
                $basket->items()->create([
                    'lineType' => BasketItem::LINE_TYPE_PRODUCT,
                    'lineKey' => $lineKey,
                    'itemType' => ProductVariant::class,
                    'itemID' => $variant->id,
                    'quantity' => $quantity,
                    'unitPrice' => (int) $variant->price,
                ]);
            }

            $this->recalculateTotal($basket);
            $basket->load($this->basketRelations());

            return $this->success('Product added to basket', [
                'basket' => $this->formatBasket($basket),
            ], 201);
        });
    }

    private function slotTakenByBooking(int $employeeId, string $date, string $time, int $duration): bool
    {
        $newStart = Carbon::parse($date.' '.$time);
        $newEnd = (clone $newStart)->addMinutes($duration);

        $bookings = Booking::with('serviceItem')
            ->where('employeeID', $employeeId)
            ->where('date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($bookings as $booking) {
            if ($this->timesOverlap(
                $newStart,
                $newEnd,
                Carbon::parse($booking->date.' '.$booking->time),
                (int) ($booking->serviceItem?->duration ?? $duration)
            )) {
                return true;
            }
        }

        return false;
    }

    private function slotTakenInBasket(Basket $basket, int $employeeId, string $date, string $time, int $duration): bool
    {
        $newStart = Carbon::parse($date.' '.$time);
        $newEnd = (clone $newStart)->addMinutes($duration);

        $items = $basket->items()
            ->with('item')
            ->where('lineType', BasketItem::LINE_TYPE_SERVICE)
            ->where('employeeID', $employeeId)
            ->whereDate('scheduledDate', $date)
            ->get();

        foreach ($items as $line) {
            /** @var ServiceItem|null $serviceItem */
            $serviceItem = $line->item instanceof ServiceItem ? $line->item : null;
            $lineDuration = (int) ($serviceItem?->duration ?? $duration);
            $existingStart = Carbon::parse(
                $line->scheduledDate->format('Y-m-d').' '.
                ServiceEmployeeSchedule::normalizeTimeString($line->scheduledTime)
            );
            $existingEnd = (clone $existingStart)->addMinutes($lineDuration);

            if ($newStart < $existingEnd && $newEnd > $existingStart) {
                return true;
            }
        }

        return false;
    }

    private function timesOverlap(Carbon $newStart, Carbon $newEnd, Carbon $existingStart, int $existingDurationMinutes): bool
    {
        $existingEnd = (clone $existingStart)->addMinutes($existingDurationMinutes);

        return $newStart < $existingEnd && $newEnd > $existingStart;
    }

    private function getOrCreateOpenBasket(int $userId): Basket
    {
        $basket = $this->findOpenBasket($userId);

        if ($basket !== null) {
            return $basket;
        }

        return Basket::create([
            'userID' => $userId,
            'status' => 'open',
            'totalPrice' => 0,
        ]);
    }

    private function findOpenBasket(int $userId): ?Basket
    {
        return Basket::query()
            ->where('userID', $userId)
            ->where('status', 'open')
            ->first();
    }

    private function recalculateTotal(Basket $basket): void
    {
        $total = (int) $basket->items()->sum(DB::raw('quantity * unitPrice'));

        $basket->update(['totalPrice' => $total]);
    }

    private function formatBasket(Basket $basket): array
    {
        $items = $basket->items->map(fn (BasketItem $item) => $this->formatBasketItem($item))->values();

        return [
            'id' => $basket->id,
            'status' => $basket->status,
            'total_price' => (int) $basket->totalPrice,
            'item_count' => $items->count(),
            'items' => $items,
            'product_items' => $items->where('line_type', BasketItem::LINE_TYPE_PRODUCT)->values(),
            'service_items' => $items->where('line_type', BasketItem::LINE_TYPE_SERVICE)->values(),
        ];
    }

    private function formatBasketItem(BasketItem $item): array
    {
        $payload = [
            'id' => $item->id,
            'line_type' => $item->lineType,
            'quantity' => (int) $item->quantity,
            'unit_price' => (int) $item->unitPrice,
            'line_total' => (int) $item->quantity * (int) $item->unitPrice,
        ];

        if ($item->lineType === BasketItem::LINE_TYPE_PRODUCT && $item->item instanceof ProductVariant) {
            $variant = $item->item;
            $product = $variant->relationLoaded('product') ? $variant->product : Product::find($variant->productID);

            $payload['product_variant_id'] = $variant->id;
            $payload['product_id'] = $variant->productID;
            $payload['store_id'] = $variant->storeID;
            $payload['sku'] = $variant->sku;
            $payload['product_name'] = $product?->name;
            $payload['variant_name'] = $variant->name;
        }

        if ($item->lineType === BasketItem::LINE_TYPE_SERVICE && $item->item instanceof ServiceItem) {
            $serviceItem = $item->item;
            $service = $serviceItem->relationLoaded('service')
                ? $serviceItem->service
                : Service::find($serviceItem->serviceID);

            $payload['service_item_id'] = $serviceItem->id;
            $payload['service_id'] = $serviceItem->serviceID;
            $payload['service_name'] = $service?->name;
            $payload['item_name'] = $serviceItem->name;
            $payload['employee_id'] = $item->employeeID;
            $payload['employee_name'] = $item->employee?->name;
            $payload['scheduled_date'] = $item->scheduledDate?->toDateString();
            $payload['scheduled_time'] = ServiceEmployeeSchedule::formatTimeForApi($item->scheduledTime);
        }

        return $payload;
    }

    private function basketRelations(): array
    {
        return [
            'items.employee',
            'items.item' => function ($morphTo) {
                $morphTo->morphWith([
                    ServiceItem::class => ['service'],
                    ProductVariant::class => ['product'],
                ]);
            },
        ];
    }

    private function emptyBasketPayload(): array
    {
        return [
            'id' => null,
            'status' => 'open',
            'total_price' => 0,
            'item_count' => 0,
            'items' => [],
            'product_items' => [],
            'service_items' => [],
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
