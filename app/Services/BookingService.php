<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Employee;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Support\ServiceEmployeeSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function createBooking(array $data): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        return DB::transaction(function () use ($data, $userId) {
            $lockKey = "booking_lock_{$data['employee_id']}_{$data['date']}_{$data['time']}";
            $lock = Cache::lock($lockKey, 10);

            if (! $lock->get()) {
                return $this->fail('Someone is booking this slot, try again', 409);
            }

            try {
                $service = Service::with('workingDays')->find($data['service_id']);
                if (! $service) {
                    return $this->fail('Service not found', 404);
                }

                $item = ServiceItem::with(['employees', 'service.workingDays'])->find($data['service_item_id']);
                if (! $item) {
                    return $this->fail('Service item not found', 404);
                }

                if ((int) $item->serviceID !== (int) $service->id) {
                    return $this->fail('Service item does not belong to this service', 422);
                }

                if (! $item->isActive()) {
                    return $this->fail('Service item is not available', 422);
                }

                $employee = Employee::with('workingDays')->find($data['employee_id']);
                if (! $employee) {
                    return $this->fail('Employee not found', 404);
                }

                if ((int) $employee->serviceID !== (int) $service->id) {
                    return $this->fail('Employee does not belong to this service', 422);
                }

                $newStart = ServiceEmployeeSchedule::parseAppointmentDateTime($data['date'], $data['time']);
                if ($newStart->isPast()) {
                    return $this->fail('Cannot book in the past', 422);
                }

                if (! $item->employees->contains('id', $employee->id)) {
                    return $this->fail('Employee not assigned to this service item', 422);
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

                $newEnd = (clone $newStart)->addMinutes((int) $item->duration);

                $bookings = Booking::with('serviceItem')
                    ->where('employeeID', $data['employee_id'])
                    ->where('date', $data['date'])
                    ->where('status', '!=', 'cancelled')
                    ->get();

                foreach ($bookings as $booking) {
                    if ($this->timesOverlap(
                        $newStart,
                        $newEnd,
                        ServiceEmployeeSchedule::parseAppointmentDateTime($booking->date, $booking->time),
                        (int) ($booking->serviceItem?->duration ?? $item->duration)
                    )) {
                        return $this->fail('Time overlaps with another booking', 409);
                    }
                }

                $duplicate = Booking::query()
                    ->where('customerID', $userId)
                    ->where('date', $data['date'])
                    ->where('time', $data['time'])
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if ($duplicate) {
                    return $this->fail('You already have a booking at this time', 409);
                }

                $linePrice = $item->priceForEmployee((int) $employee->id);

                $booking = Booking::create([
                    'serviceID' => $service->id,
                    'serviceItemID' => $item->id,
                    'customerID' => $userId,
                    'employeeID' => $employee->id,
                    'date' => $data['date'],
                    'time' => $data['time'],
                    'status' => 'pending',
                    'totalPrice' => $linePrice,
                ]);

                Cache::forget("availability_{$item->id}_{$data['date']}");

                return $this->success('Booked successfully', [
                    'booking' => $this->formatBooking($booking->load(['employee', 'serviceItem', 'service'])),
                ], 201);
            } finally {
                $lock->release();
            }
        });
    }

    public function cancelBooking(int $id): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        return DB::transaction(function () use ($id, $userId) {
            $booking = Booking::with('service')->find($id);

            if (! $booking) {
                return $this->fail('Booking not found', 404);
            }

            if ($booking->status === 'cancelled') {
                return $this->fail('Booking is already cancelled', 422);
            }

            if (! $this->userCanManageBooking($userId, $booking)) {
                return $this->fail('Forbidden', 403);
            }

            $bookingDateTime = ServiceEmployeeSchedule::parseAppointmentDateTime($booking->date, $booking->time);
            $now = Carbon::now();

            if ($now->diffInHours($bookingDateTime, false) < 24) {
                return $this->fail('Cannot cancel booking within 24 hours', 422);
            }

            $booking->update(['status' => 'cancelled']);

            Cache::forget("availability_{$booking->serviceItemID}_{$booking->date}");

            return $this->success('Booking cancelled successfully');
        });
    }

    public function getMyBookings(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $bookings = Booking::with(['employee', 'serviceItem', 'service'])
            ->where('customerID', $userId)
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->get()
            ->map(fn (Booking $booking) => $this->formatBooking($booking));

        return $this->success('OK', ['bookings' => $bookings]);
    }

    public function getBooking(int $id): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $booking = Booking::with(['employee', 'serviceItem', 'service'])->find($id);

        if (! $booking) {
            return $this->fail('Booking not found', 404);
        }

        if (! $this->userCanManageBooking($userId, $booking)) {
            return $this->fail('Forbidden', 403);
        }

        return $this->success('OK', [
            'booking' => $this->formatBooking($booking),
        ]);
    }

    public function getServiceBookingsByDay(string $date): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $service = $this->findOwnedService((int) $userId);
        if ($service === null) {
            return $this->fail('Service not found', 404);
        }

        $day = Carbon::parse($date)->toDateString();
        $items = $this->loadServiceItems($service->id);
        $bookings = $this->loadProviderBookings($service->id, $day, $day);

        return $this->success('OK', [
            'date' => $day,
            'service' => $this->formatServiceSummary($service),
            'items' => $this->groupBookingsByServiceItem($items, $bookings),
        ]);
    }

    public function getServiceBookingsByWeek(string $date): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $service = $this->findOwnedService((int) $userId);
        if ($service === null) {
            return $this->fail('Service not found', 404);
        }

        $anchor = Carbon::parse($date);
        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = $anchor->copy()->endOfWeek(Carbon::MONDAY)->toDateString();

        $items = $this->loadServiceItems($service->id);
        $bookings = $this->loadProviderBookings($service->id, $weekStart, $weekEnd)
            ->groupBy(fn (Booking $booking) => $booking->date instanceof Carbon
                ? $booking->date->toDateString()
                : (string) $booking->date);

        $days = [];
        $cursor = Carbon::parse($weekStart);
        $end = Carbon::parse($weekEnd);

        while ($cursor->lte($end)) {
            $dayKey = $cursor->toDateString();
            $dayBookings = $bookings->get($dayKey, collect());

            $days[] = [
                'date' => $dayKey,
                'items' => $this->groupBookingsByServiceItem($items, $dayBookings),
            ];

            $cursor->addDay();
        }

        return $this->success('OK', [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'service' => $this->formatServiceSummary($service),
            'days' => $days,
        ]);
    }

    public function getServiceBookingsByMonth(int $year, int $month): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->fail('Unauthenticated', 401);
        }

        $service = $this->findOwnedService((int) $userId);
        if ($service === null) {
            return $this->fail('Service not found', 404);
        }

        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $countsByEmployee = $this->loadProviderBookings($service->id, $monthStart, $monthEnd)
            ->countBy(fn (Booking $booking) => (int) $booking->employeeID);

        $employees = Employee::query()
            ->where('serviceID', $service->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'booking_count' => (int) ($countsByEmployee[$employee->id] ?? 0),
            ])
            ->values();

        return $this->success('OK', [
            'year' => $year,
            'month' => $month,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'service' => $this->formatServiceSummary($service),
            'employees' => $employees,
        ]);
    }

    private function findOwnedService(int $userId): ?Service
    {
        return Service::query()
            ->where('serviceOwnerID', $userId)
            ->first();
    }

    private function loadServiceItems(int $serviceId)
    {
        return ServiceItem::query()
            ->where('serviceID', $serviceId)
            ->orderBy('name')
            ->get();
    }

    private function loadProviderBookings(int $serviceId, string $fromDate, string $toDate)
    {
        return Booking::with(['employee', 'serviceItem', 'customer'])
            ->where('serviceID', $serviceId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->orderBy('date')
            ->orderBy('time')
            ->get();
    }

    private function groupBookingsByServiceItem($items, $bookings): array
    {
        $bookingsByItem = collect($bookings)->groupBy('serviceItemID');

        return $items->map(function (ServiceItem $item) use ($bookingsByItem) {
            $itemBookings = $bookingsByItem->get($item->id, collect())
                ->map(fn (Booking $booking) => $this->formatBooking($booking, includeCustomer: true))
                ->values();

            return [
                'service_item_id' => $item->id,
                'service_item_name' => $item->name,
                'booking_count' => $itemBookings->count(),
                'bookings' => $itemBookings,
            ];
        })->values()->all();
    }

    private function formatServiceSummary(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
        ];
    }

    private function userCanManageBooking(int $userId, Booking $booking): bool
    {
        if ((int) $booking->customerID === $userId) {
            return true;
        }

        $service = $booking->relationLoaded('service')
            ? $booking->service
            : Service::find($booking->serviceID);

        return $service !== null && (int) $service->serviceOwnerID === $userId;
    }

    private function timesOverlap(Carbon $newStart, Carbon $newEnd, Carbon $existingStart, int $existingDurationMinutes): bool
    {
        $existingEnd = (clone $existingStart)->addMinutes($existingDurationMinutes);

        return $newStart < $existingEnd && $newEnd > $existingStart;
    }

    private function formatBooking(Booking $booking, bool $includeCustomer = false): array
    {
        $payload = [
            'id' => $booking->id,
            'service_id' => $booking->serviceID,
            'service_item_id' => $booking->serviceItemID,
            'date' => $booking->date instanceof Carbon
                ? $booking->date->toDateString()
                : (string) $booking->date,
            'time' => ServiceEmployeeSchedule::formatTimeForApi($booking->time),
            'status' => $booking->status,
            'payment_status' => $booking->paymentStatus,
            'total_price' => (int) $booking->totalPrice,
            'employee' => [
                'id' => $booking->employee?->id,
                'name' => $booking->employee?->name,
            ],
            'service_item' => [
                'id' => $booking->serviceItem?->id,
                'name' => $booking->serviceItem?->name,
            ],
            'service' => [
                'id' => $booking->service?->id,
                'name' => $booking->service?->name,
            ],
        ];

        if ($includeCustomer) {
            $payload['customer'] = [
                'id' => $booking->customer?->id,
                'name' => $booking->customer?->name,
            ];
        }

        return $payload;
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
