<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Employee;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class BookingDemoSeeder extends Seeder
{
    /** Demo bookings are tagged with entryNumber in this range (safe to purge on re-run). */
    private const ENTRY_NUMBER_MIN = 900000;

    private const ENTRY_NUMBER_MAX = 900999;

    private const SLOT_TIMES = ['09:00', '10:30', '12:00', '14:00', '15:30'];

    /**
     * Seed sample bookings across today, the current week (Sun–Sat), and this month.
     * Requires services with items + employees, and at least one customer user.
     *
     * Run after ServiceDemoSeeder (or your own service catalog data):
     *   php artisan db:seed --class=BookingDemoSeeder
     */
    public function run(): void
    {
        $customers = User::query()->role('Customer')->orderBy('id')->limit(12)->get();

        if ($customers->isEmpty()) {
            $this->command?->warn('BookingDemoSeeder skipped: no Customer users. Run ServiceDemoSeeder or create customers first.');

            return;
        }

        $services = Service::query()
            ->whereHas('serviceItems')
            ->whereHas('employees')
            ->with([
                'serviceItems' => fn ($q) => $q->orderBy('id'),
                'serviceItems.employees',
                'employees',
            ])
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($services->isEmpty()) {
            $this->command?->warn('BookingDemoSeeder skipped: no services with items and employees. Run ServiceDemoSeeder first.');

            return;
        }

        $deleted = Booking::query()
            ->whereBetween('entryNumber', [self::ENTRY_NUMBER_MIN, self::ENTRY_NUMBER_MAX])
            ->forceDelete();

        $entryNumber = self::ENTRY_NUMBER_MIN;
        $created = 0;

        foreach ($services as $service) {
            $created += $this->seedForService($service, $customers, $entryNumber);
        }

        $this->command?->info(sprintf(
            'BookingDemoSeeder: removed %d old demo booking(s), created %d booking(s) (entryNumber %d–%d).',
            $deleted,
            $created,
            self::ENTRY_NUMBER_MIN,
            self::ENTRY_NUMBER_MAX
        ));
    }

    private function seedForService(Service $service, Collection $customers, int &$entryNumber): int
    {
        $count = 0;
        $weekStart = Carbon::today()->startOfWeek(Carbon::SUNDAY);
        $weekEnd = Carbon::today()->endOfWeek(Carbon::SATURDAY);
        $monthEnd = Carbon::today()->endOfMonth();

        $employeeSlots = [];

        /** @var ServiceItem $item */
        foreach ($service->serviceItems as $itemIndex => $item) {
            $employees = $this->employeesForItem($item, $service);

            if ($employees->isEmpty()) {
                continue;
            }

            $cursor = $weekStart->copy();
            while ($cursor->lte($weekEnd)) {
                if (($cursor->dayOfWeekIso + $itemIndex) % 2 === 0) {
                    $cursor->addDay();

                    continue;
                }

                $employee = $employees[$cursor->dayOfWeekIso % $employees->count()];
                $customer = $customers[($itemIndex + $cursor->dayOfWeekIso) % $customers->count()];
                $time = $this->nextSlotTime($employeeSlots, (int) $employee->id, $cursor->toDateString());

                if ($time === null) {
                    $cursor->addDay();

                    continue;
                }

                $this->createBooking(
                    $service,
                    $item,
                    $customer,
                    $employee,
                    $cursor->toDateString(),
                    $time,
                    $entryNumber
                );
                $count++;
                $entryNumber++;

                $cursor->addDay();
            }

            if ($itemIndex === 0) {
                $extraDay = $monthEnd->copy()->subDays(3);
                if ($extraDay->gt($weekEnd)) {
                    $employee = $employees->first();
                    $customer = $customers->first();
                    $time = self::SLOT_TIMES[0];

                    $this->createBooking(
                        $service,
                        $item,
                        $customer,
                        $employee,
                        $extraDay->toDateString(),
                        $time,
                        $entryNumber,
                        status: 'pending'
                    );
                    $count++;
                    $entryNumber++;
                }
            }
        }

        $today = Carbon::today();
        $firstItem = $service->serviceItems->first();
        $firstEmployee = $service->employees->first();

        if ($firstItem !== null && $firstEmployee !== null) {
            $this->createBooking(
                $service,
                $firstItem,
                $customers->first(),
                $firstEmployee,
                $today->toDateString(),
                '09:00',
                $entryNumber
            );
            $count++;
            $entryNumber++;

            if ($customers->count() > 1) {
                $this->createBooking(
                    $service,
                    $firstItem,
                    $customers->get(1),
                    $firstEmployee,
                    $today->toDateString(),
                    '10:30',
                    $entryNumber
                );
                $count++;
                $entryNumber++;
            }
        }

        return $count;
    }

    private function employeesForItem(ServiceItem $item, Service $service): Collection
    {
        if ($item->relationLoaded('employees') && $item->employees->isNotEmpty()) {
            return $item->employees->values();
        }

        return $service->employees ?? collect();
    }

    /** @param  array<int, array<string, true>>  $employeeSlots */
    private function nextSlotTime(array &$employeeSlots, int $employeeId, string $date): ?string
    {
        foreach (self::SLOT_TIMES as $time) {
            if (! isset($employeeSlots[$employeeId][$date.'|'.$time])) {
                $employeeSlots[$employeeId][$date.'|'.$time] = true;

                return $time;
            }
        }

        return null;
    }

    private function createBooking(
        Service $service,
        ServiceItem $item,
        User $customer,
        Employee $employee,
        string $date,
        string $time,
        int $entryNumber,
        string $status = 'pending',
        string $paymentStatus = 'unpaid',
    ): void {
        if ($entryNumber > self::ENTRY_NUMBER_MAX) {
            return;
        }

        Booking::query()->create([
            'serviceID' => $service->id,
            'serviceItemID' => $item->id,
            'customerID' => $customer->id,
            'employeeID' => $employee->id,
            'date' => $date,
            'time' => $time,
            'entryNumber' => $entryNumber,
            'status' => $status,
            'paymentStatus' => $paymentStatus,
            'totalPrice' => $item->priceForEmployee((int) $employee->id),
        ]);
    }
}
