<?php

namespace App\DAO;

use App\Models\Employee;
use Illuminate\Support\Collection;

interface ServiceProviderEmployeeInterface
{
    public function listForService(int $serviceId): Collection;

    public function findForService(int $employeeId, int $serviceId): ?Employee;

    public function createForService(int $serviceId, array $data): Employee;

    public function update(Employee $employee, array $data): Employee;

    public function delete(Employee $employee): bool;

    /**
     * @param  list<int>  $isoWeekdays
     */
    public function syncWorkingDays(Employee $employee, array $isoWeekdays, ?string $startsAt, ?string $endsAt): Employee;

    /** @param  int[]  $employeeIds */
    public function allBelongToService(int $serviceId, array $employeeIds): bool;
}
