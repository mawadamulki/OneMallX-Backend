<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\User;
use App\Support\WorkingWeekday;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ServiceDemoSeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'onemallx.local';

    /** @var int Services per service-type area (same scale idea as store seeder) */
    private const SERVICES_PER_AREA = 10;

    /** @var int Staff members per service */
    private const EMPLOYEES_PER_SERVICE = 4;

    /** @var int Demo customers used for sample bookings */
    private const DEMO_CUSTOMER_COUNT = 12;

    /** Opening hours (service + item availability logic) */
    private const OPEN_TIME = '09:00';

    private const CLOSE_TIME = '21:00';

    /**
     * Sat–Thu as ISO weekdays (1 = Mon … 7 = Sun).
     *
     * @var list<int>
     */
    private const SERVICE_ISO_WEEKDAYS = [6, 7, 1, 2, 3, 4];

    /**
     * One Service Provider user per service (like one store owner per store).
     * Many services per service-type area, many items + employees per service, sample bookings. No media.
     * Re-run safe: purges demo-svc-* users and legacy owner@test / customer@test.
     */
    public function run(): void
    {
        $serviceAreas = Area::query()
            ->where('usageType', 'service')
            ->with('floor')
            ->orderBy('floorID')
            ->orderBy('number')
            ->get();

        if ($serviceAreas->isEmpty()) {
            $this->command?->warn('ServiceDemoSeeder skipped: no service areas. Run FloorAreaSeeder first.');

            return;
        }

        $this->purgeSeededServiceUsers();

        DB::transaction(function () use ($serviceAreas): void {
            $customers = $this->createDemoCustomers();

            $serviceCount = 0;
            $itemCount = 0;
            $employeeCount = 0;
            $bookingCount = 0;
            $ownerCount = 0;

            foreach ($serviceAreas as $area) {
                $floorLabel = $area->floor?->name ?? 'Floor';

                for ($unit = 1; $unit <= self::SERVICES_PER_AREA; $unit++) {
                    $owner = $this->createServiceOwner($area->id, $unit);
                    $ownerCount++;

                    $service = Service::query()->create([
                        'name' => "{$area->name} — Service {$unit} ({$floorLabel})",
                        'serviceOwnerID' => $owner->id,
                        'price' => 0,
                        'areaID' => $area->id,
                        'description' => "Seeded service {$unit} in {$area->name} ({$area->category}).",
                        'paymentAccount' => null,
                        'openTime' => self::OPEN_TIME,
                        'closeTime' => self::CLOSE_TIME,
                        'duration' => null,
                        'locationID' => null,
                        'status' => 'active',
                        'accountStatus' => 'active',
                    ]);
                    WorkingWeekday::syncForService($service, self::SERVICE_ISO_WEEKDAYS);
                    $serviceCount++;

                    $employees = [];
                    for ($e = 1; $e <= self::EMPLOYEES_PER_SERVICE; $e++) {
                        $employee = Employee::query()->create([
                            'name' => "Staff A{$area->id}-S{$unit}-E{$e}",
                            'serviceID' => $service->id,
                        ]);
                        WorkingWeekday::syncForEmployee($employee, self::SERVICE_ISO_WEEKDAYS);
                        $employees[] = $employee;
                        $employeeCount++;
                    }

                    $items = [];
                    foreach ($this->itemBlueprints() as $idx => $tpl) {
                        $item = ServiceItem::query()->create([
                            'serviceID' => $service->id,
                            'name' => $tpl['name'].' #'.($idx + 1),
                            'price' => $tpl['price'],
                            'duration' => $tpl['duration'],
                        ]);
                        $items[] = $item;
                        $itemCount++;

                        $syncPayload = [];
                        foreach ($employees as $eIdx => $employee) {
                            // Per-employee price: base + small offset (demo: seniority-style spread)
                            $syncPayload[$employee->id] = ['price' => $tpl['price'] + ($eIdx * 250)];
                        }
                        $item->employees()->sync($syncPayload);
                    }

                    $bookingCount += $this->seedBookingsForService($service, $items[0], $employees, $customers);
                }
            }

            $this->command?->info(sprintf(
                'ServiceDemoSeeder: %d service area(s), %d provider account(s), %d service(s), %d item(s), %d employee(s), %d booking(s). Owner password: "%s".',
                $serviceAreas->count(),
                $ownerCount,
                $serviceCount,
                $itemCount,
                $employeeCount,
                $bookingCount,
                'password'
            ));
        });
    }

    /**
     * @return list<User>
     */
    private function createDemoCustomers(): array
    {
        $customers = [];

        for ($i = 1; $i <= self::DEMO_CUSTOMER_COUNT; $i++) {
            $user = User::query()->create([
                'name' => "Demo Service Customer {$i}",
                'email' => "demo-svc-customer-{$i}@".self::EMAIL_DOMAIN,
                'password' => Hash::make('password'),
                'phoneNumber' => '08'.str_pad((string) (700000000 + $i), 9, '0', STR_PAD_LEFT),
                'status' => 'active',
                'is_verified' => true,
            ]);
            $user->assignRole('Customer');
            $customers[] = $user;
        }

        return $customers;
    }

    private function createServiceOwner(int $areaId, int $unit): User
    {
        $owner = User::query()->create([
            'name' => "Provider — Area {$areaId} Service {$unit}",
            'email' => "demo-svc-owner-{$areaId}-{$unit}@".self::EMAIL_DOMAIN,
            'password' => Hash::make('password'),
            'phoneNumber' => $this->seededOwnerPhone($areaId, $unit),
            'status' => 'active',
            'is_verified' => true,
        ]);
        $owner->assignRole('Service Provider');

        return $owner;
    }

    private function seededOwnerPhone(int $areaId, int $unit): string
    {
        $n = ($areaId * 1000) + $unit;

        return '07'.str_pad((string) $n, 9, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<Employee>  $employees
     * @param  list<User>  $customers
     */
    private function seedBookingsForService(Service $service, ServiceItem $firstItem, array $employees, array $customers): int
    {
        $count = 0;
        $times = ['09:00', '10:30', '14:00'];

        foreach ([0, 1, 2] as $i) {
            $customer = $customers[$i % count($customers)];
            $employee = $employees[$i % count($employees)];
            $date = Carbon::today()->addDays($i % 3)->toDateString();

            Booking::query()->create([
                'serviceID' => $service->id,
                'serviceItemID' => $firstItem->id,
                'customerID' => $customer->id,
                'employeeID' => $employee->id,
                'date' => $date,
                'time' => $times[$i % count($times)],
                'status' => 'pending',
                'paymentStatus' => 'unpaid',
                'totalPrice' => $firstItem->priceForEmployee((int) $employee->id),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{name: string, duration: int, price: int}>
     */
    private function itemBlueprints(): array
    {
        return [
            ['name' => 'Intro consult', 'duration' => 15, 'price' => 1500],
            ['name' => 'Standard session', 'duration' => 30, 'price' => 3500],
            ['name' => 'Extended session', 'duration' => 45, 'price' => 5200],
            ['name' => 'Premium block', 'duration' => 60, 'price' => 7500],
            ['name' => 'Express slot', 'duration' => 20, 'price' => 2200],
            ['name' => 'Follow-up', 'duration' => 25, 'price' => 2800],
            ['name' => 'Deep dive', 'duration' => 90, 'price' => 9900],
            ['name' => 'Pair session', 'duration' => 40, 'price' => 6400],
            ['name' => 'Assessment', 'duration' => 35, 'price' => 4100],
            ['name' => 'Add-on A', 'duration' => 15, 'price' => 900],
            ['name' => 'Add-on B', 'duration' => 15, 'price' => 1200],
            ['name' => 'After-hours', 'duration' => 30, 'price' => 4500],
        ];
    }

    private function purgeSeededServiceUsers(): void
    {
        User::query()
            ->where(function ($q): void {
                $q->where('email', 'like', 'demo-svc-owner-%@'.self::EMAIL_DOMAIN)
                    ->orWhere('email', 'like', 'demo-svc-customer-%@'.self::EMAIL_DOMAIN)
                    ->orWhere('email', 'owner@test.com')
                    ->orWhere('email', 'customer@test.com');
            })
            ->each(function (User $user): void {
                $user->syncRoles([]);
                $user->delete();
            });
    }
}
