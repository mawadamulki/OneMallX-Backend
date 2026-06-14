<?php

namespace App\Services;

use App\DAO\ServiceProviderEmployeeInterface;
use App\DAO\ServiceProviderInterface;
use App\Models\Employee;
use App\Support\WorkingWeekday;

class ServiceProviderEmployeeService
{
    public function __construct(
        protected ServiceProviderInterface $serviceProviderClass,
        protected ServiceProviderEmployeeInterface $serviceProviderEmployeeClass,
    ) {}

    public function listForProvider(int $userId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employees = $this->serviceProviderEmployeeClass
            ->listForService((int) $service->id)
            ->map(fn (Employee $employee) => $this->toArray($employee));

        return [
            'success' => true,
            'service' => ['id' => $service->id, 'name' => $service->name],
            'employees' => $employees,
        ];
    }

    public function createForProvider(int $userId, array $payload): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employee = $this->serviceProviderEmployeeClass->createForService((int) $service->id, [
            'name' => $payload['name'],
        ]);

        if (! empty($payload['weekdays'])) {
            $weekdays = $this->parseWeekdays($payload['weekdays']);
            if (isset($weekdays['success']) && $weekdays['success'] === false) {
                return $weekdays;
            }

            $employee = $this->serviceProviderEmployeeClass->syncWorkingDays(
                $employee,
                $weekdays,
                $payload['startsAt'] ?? null,
                $payload['endsAt'] ?? null
            );
        }

        return [
            'success' => true,
            'message' => 'Employee created.',
            'employee' => $this->toArray($employee),
            'http_status' => 201,
        ];
    }

    public function updateForProvider(int $userId, int $employeeId, array $payload): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employee = $this->serviceProviderEmployeeClass->findForService($employeeId, (int) $service->id);

        if ($employee === null) {
            return $this->fail('Employee not found.', 404);
        }

        if (! array_key_exists('name', $payload)) {
            return $this->fail('No fields to update.', 422);
        }

        $updated = $this->serviceProviderEmployeeClass->update($employee, [
            'name' => $payload['name'],
        ]);

        return [
            'success' => true,
            'message' => 'Employee updated.',
            'employee' => $this->toArray($updated),
        ];
    }

    public function deleteForProvider(int $userId, int $employeeId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employee = $this->serviceProviderEmployeeClass->findForService($employeeId, (int) $service->id);

        if ($employee === null) {
            return $this->fail('Employee not found.', 404);
        }

        $this->serviceProviderEmployeeClass->delete($employee);

        return [
            'success' => true,
            'message' => 'Employee deleted.',
            'deleted' => true,
        ];
    }

    public function syncWorkingDaysForProvider(int $userId, int $employeeId, array $payload): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employee = $this->serviceProviderEmployeeClass->findForService($employeeId, (int) $service->id);

        if ($employee === null) {
            return $this->fail('Employee not found.', 404);
        }

        $weekdays = $this->parseWeekdays($payload['weekdays'] ?? []);
        if (isset($weekdays['success']) && $weekdays['success'] === false) {
            return $weekdays;
        }

        $updated = $this->serviceProviderEmployeeClass->syncWorkingDays(
            $employee,
            $weekdays,
            $payload['startsAt'] ?? null,
            $payload['endsAt'] ?? null
        );

        return [
            'success' => true,
            'message' => 'Employee working days updated.',
            'employee' => $this->toArray($updated),
        ];
    }

    /** @param  list<int>|mixed  $raw */
    private function parseWeekdays(mixed $raw): array
    {
        if (! is_array($raw)) {
            return $this->fail('weekdays must be an array.', 422);
        }

        $weekdays = array_values(array_unique(array_map('intval', $raw)));

        foreach ($weekdays as $iso) {
            if ($iso < 1 || $iso > 7) {
                return $this->fail('Each weekday must be between 1 (Monday) and 7 (Sunday).', 422);
            }
        }

        return $weekdays;
    }

    private function toArray(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'serviceID' => $employee->serviceID,
            'weekdays' => $employee->relationLoaded('workingDays')
                ? $employee->workingDays->sortBy('weekday')->pluck('weekday')->values()->all()
                : [],
            'days' => $employee->relationLoaded('workingDays')
                ? $employee->workingDays->sortBy('weekday')->map(fn ($d) => WorkingWeekday::isoToAbbrev($d->weekday))->values()->all()
                : [],
            'schedule' => $employee->relationLoaded('workingDays')
                ? $employee->workingDays->sortBy('weekday')->map(fn ($d) => [
                    'weekday' => $d->weekday,
                    'day' => WorkingWeekday::isoToAbbrev($d->weekday),
                    'startsAt' => $d->starts_at,
                    'endsAt' => $d->ends_at,
                ])->values()->all()
                : [],
        ];
    }

    /** @return array{success: false, message: string, http_status: int} */
    private function fail(string $message, int $httpStatus): array
    {
        return [
            'success' => false,
            'message' => $message,
            'http_status' => $httpStatus,
        ];
    }
}

