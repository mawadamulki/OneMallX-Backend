<?php

namespace App\Services;

use App\DAO\ServiceItemDAO;
use App\Models\Employee;
use App\Models\ServiceItem;
use App\Support\ServiceEmployeeSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ServiceItemService
{
    public function __construct(private ServiceItemDAO $dao) {}

    public function getItemWithAvailability($id, $date)
    {
        $item = $this->resolveBookableItem($id);
        if (isset($item['error'])) {
            return $item;
        }

        return Cache::remember("availability_{$id}_{$date}", 60, function () use ($item, $date) {

            $bookings = \App\Models\Booking::with('serviceItem')
                ->where('serviceItemID', $item->id)
                ->where('date', $date)
                ->where('status', '!=', 'cancelled')
                ->get()
                ->groupBy('employeeID');

            $result = [];

            foreach ($item->employees as $employee) {
                if (($employee->status ?? 'active') !== 'active') {
                    continue;
                }

                $times = $this->generateTimeSlotsForEmployeeOnDate($item, $employee, $date);

                $employeeBookings = $bookings[$employee->id] ?? collect();

                $slots = [];

                foreach ($times as $time) {
                    $slotStart = ServiceEmployeeSchedule::parseAppointmentDateTime($date, $time);
                    $slotEnd = (clone $slotStart)->addMinutes((int) $item->duration);

                    $isBooked = $employeeBookings->contains(function ($booking) use ($slotStart, $slotEnd) {
                        $existingStart = ServiceEmployeeSchedule::parseAppointmentDateTime($booking->date, $booking->time);
                        $existingEnd = (clone $existingStart)
                            ->addMinutes((int) ($booking->serviceItem?->duration ?? 0));

                        return $slotStart < $existingEnd && $slotEnd > $existingStart;
                    });

                    $slots[] = [
                        'time' => $time,
                        'available' => ! $isBooked,
                    ];
                }

                $result[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'price' => $item->priceForEmployee((int) $employee->id),
                    'slots' => $slots,
                ];
            }

            return [
                'id' => $item->id,
                'name' => $item->name,
                'employees' => $result,
            ];
        });
    }

    public function getItemsByService($serviceId)
    {
        $items = $this->dao->getByService($serviceId);

        return $items->map(function ($item) {
            $employeePrices = $item->employees->mapWithKeys(fn ($e) => [
                $e->id => $item->priceForEmployee((int) $e->id),
            ])->all();

            $prices = array_values($employeePrices);
            $fromPrice = $prices !== [] ? min($prices) : (int) $item->price;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (int) $item->price,
                'from_price' => $fromPrice,
                'employee_prices' => $employeePrices,
                'image' => $item->media->first()?->url,
                'rating' => round($item->rates->avg('score'), 1),
                'rating_count' => $item->rates->count(),
            ];
        });
    }

    /**
     * Slots for this item length inside (service open ∩ employee shift) on $dateYmd.
     *
     * @return list<string> 'H:i' start times
     */
    private function generateTimeSlotsForEmployeeOnDate(ServiceItem $item, Employee $employee, string $dateYmd): array
    {
        $date = Carbon::parse($dateYmd);
        $intersection = ServiceEmployeeSchedule::intersectionForBooking($item->service, $employee, $date);
        if ($intersection === null) {
            return [];
        }

        [$windowStart, $windowEnd] = $intersection;
        $duration = (int) $item->duration;
        if ($duration <= 0) {
            return [];
        }

        $times = [];
        $current = $windowStart->copy();

        while ($current->copy()->addMinutes($duration) <= $windowEnd) {
            $times[] = $current->format('H:i');
            $current->addMinutes($duration);
        }

        return $times;
    }

    public function getAvailableDays($itemId)
    {
        $item = $this->resolveBookableItem($itemId);
        if (isset($item['error'])) {
            return $item;
        }

        if ($item->employees->isEmpty()) {
            return ['error' => 'No employees assigned to this service item'];
        }

        if (! ServiceEmployeeSchedule::hasValidServiceWindowForService($item->service)) {
            return ['error' => 'Service opening hours are not configured'];
        }

        $days = [];
        $duration = (int) $item->duration;

        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::today()->addDays($i);
            $dayName = strtolower($date->format('D'));
            $iso = (int) $date->dayOfWeekIso;

            if (! $item->service->allowsBookingOnWeekday($iso)) {
                continue;
            }

            $hasEmployee = $item->employees->first(function ($emp) use ($item, $date, $duration) {
                if (($emp->status ?? 'active') !== 'active') {
                    return false;
                }

                return ServiceEmployeeSchedule::hasBookableWindowOnDate(
                    $item->service,
                    $emp,
                    $date,
                    $duration
                );
            });

            if ($hasEmployee) {
                $days[] = [
                    'date' => $date->toDateString(),
                    'day' => $dayName,
                    'weekday' => $iso,
                ];
            }
        }

        if ($days === [] && ! $item->service->hasWorkingDaySchedule()) {
            return ['error' => 'Service working days are not configured'];
        }

        return $days;
    }

    /**
     * @return ServiceItem|array{error: string}
     */
    private function resolveBookableItem(int $id): ServiceItem|array
    {
        $item = $this->dao->findWithEmployees($id);

        if (! $item) {
            return ['error' => 'Item not found'];
        }

        if (! $item->isActive()) {
            return ['error' => 'This service item is not available for booking'];
        }

        return $item;
    }
}
