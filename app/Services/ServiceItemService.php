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
        return Cache::remember("availability_{$id}_{$date}", 60, function () use ($id, $date) {

            $item = $this->dao->findWithEmployees($id);

            if (! $item) {
                return ['error' => 'Item not found'];
            }

            $bookings = \App\Models\Booking::where('serviceItemID', $item->id)
                ->where('date', $date)
                ->get()
                ->groupBy('employeeID');

            $result = [];

            foreach ($item->employees as $employee) {
                $times = $this->generateTimeSlotsForEmployeeOnDate($item, $employee, $date);

                $employeeBookings = $bookings[$employee->id] ?? collect();

                $slots = [];

                foreach ($times as $time) {
                    $isBooked = $employeeBookings
                        ->where('time', $time)
                        ->isNotEmpty();

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
        $intersection = ServiceEmployeeSchedule::intersectionForDate($item->service, $employee, $date);
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
        $item = $this->dao->findWithEmployees($itemId);

        if (! $item) {
            return ['error' => 'Item not found'];
        }

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::today()->addDays($i);
            $dayName = strtolower($date->format('D'));
            $iso = (int) $date->dayOfWeekIso;

            if (! $item->service->isOpenOnWeekdayForDisplay($iso)) {
                continue;
            }

            $hasEmployee = $item->employees->first(function ($emp) use ($item, $date) {
                return $emp->canOfferServiceOnDate($item->service, $date);
            });

            if ($hasEmployee) {
                $days[] = [
                    'date' => $date->toDateString(),
                    'day' => $dayName,
                    'weekday' => $iso,
                ];
            }
        }

        return $days;
    }
}
