<?php

namespace App\DAO;

use App\Models\Employee;
use App\Support\WorkingWeekday;
use Illuminate\Support\Collection;

class ServiceProviderEmployeeClass implements ServiceProviderEmployeeInterface
{
    public function listForService(int $serviceId): Collection
    {
        return Employee::query()
            ->where('serviceID', $serviceId)
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'serviceItems' => fn ($q) => $q->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();
    }

    public function findForService(int $employeeId, int $serviceId): ?Employee
    {
        return Employee::query()
            ->whereKey($employeeId)
            ->where('serviceID', $serviceId)
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'workingDays',
                'serviceItems' => fn ($q) => $q->orderBy('name'),
                'service',
            ])
            ->first();
    }

    public function createForService(int $serviceId, array $data): Employee
    {
        return Employee::query()->create([
            ...$data,
            'serviceID' => $serviceId,
        ]);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee->fresh([
            'media',
            'workingDays',
            'serviceItems',
        ]);
    }

    public function delete(Employee $employee): bool
    {
        return (bool) $employee->delete();
    }

    public function syncWorkingDaySchedule(Employee $employee, array $workingDays): Employee
    {
        WorkingWeekday::syncScheduleForEmployee($employee, $workingDays);

        return $employee->fresh(['workingDays']);
    }

    public function syncWorkingDays(Employee $employee, array $isoWeekdays, ?string $startsAt, ?string $endsAt): Employee
    {
        WorkingWeekday::syncForEmployee($employee, $isoWeekdays, $startsAt, $endsAt);

        return $employee->fresh(['workingDays']);
    }

    public function allBelongToService(int $serviceId, array $employeeIds): bool
    {
        if ($employeeIds === []) {
            return true;
        }

        $count = Employee::query()
            ->where('serviceID', $serviceId)
            ->whereIn('id', $employeeIds)
            ->count();

        return $count === count(array_unique($employeeIds));
    }
}
