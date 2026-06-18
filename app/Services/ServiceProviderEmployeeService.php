<?php

namespace App\Services;

use App\DAO\ServiceProviderEmployeeInterface;
use App\DAO\ServiceProviderInterface;
use App\Models\Employee;
use App\Models\Media;
use App\Support\ServiceEmployeeSchedule;
use App\Support\WorkingWeekday;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
            ->map(fn (Employee $employee) => $this->toSummaryArray($employee));

        return [
            'success' => true,
            'service' => ['id' => $service->id, 'name' => $service->name],
            'employees' => $employees,
        ];
    }

    public function showForProvider(int $userId, int $employeeId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employee = $this->serviceProviderEmployeeClass->findForService($employeeId, (int) $service->id);

        if ($employee === null) {
            return $this->fail('Employee not found.', 404);
        }

        return [
            'success' => true,
            'employee' => $this->toDetailArray($employee),
        ];
    }

    public function createForProvider(int $userId, array $payload, ?UploadedFile $photo = null): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $workingDays = $this->parseWorkingDaySchedule($payload['workingDays'] ?? null, required: true);
        if (isset($workingDays['success']) && $workingDays['success'] === false) {
            return $workingDays;
        }

        $employee = $this->serviceProviderEmployeeClass->createForService((int) $service->id, [
            'name' => $payload['name'],
            'phoneNumber' => $payload['phoneNumber'] ?? null,
            'email' => $payload['email'] ?? null,
        ]);

        $employee = $this->serviceProviderEmployeeClass->syncWorkingDaySchedule($employee, $workingDays);

        if ($photo !== null) {
            $this->attachPhoto($employee, $photo);
            $employee = $this->serviceProviderEmployeeClass->findForService((int) $employee->id, (int) $service->id);
        }

        return [
            'success' => true,
            'message' => 'Employee created.',
            'employee' => $this->toDetailArray($employee),
            'http_status' => 201,
        ];
    }

    public function updateForProvider(int $userId, int $employeeId, array $payload, ?UploadedFile $photo = null): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employee = $this->serviceProviderEmployeeClass->findForService($employeeId, (int) $service->id);

        if ($employee === null) {
            return $this->fail('Employee not found.', 404);
        }

        $data = [];

        foreach (['name', 'phoneNumber', 'email'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        $workingDays = null;
        if (array_key_exists('workingDays', $payload)) {
            $workingDays = $this->parseWorkingDaySchedule($payload['workingDays'], required: true);
            if (isset($workingDays['success']) && $workingDays['success'] === false) {
                return $workingDays;
            }
        }

        if ($data === [] && $workingDays === null && $photo === null) {
            return $this->fail('No fields to update.', 422);
        }

        if ($data !== []) {
            $employee = $this->serviceProviderEmployeeClass->update($employee, $data);
        }

        if ($workingDays !== null) {
            $employee = $this->serviceProviderEmployeeClass->syncWorkingDaySchedule($employee, $workingDays);
        }

        if ($photo !== null) {
            $this->replacePhoto($employee, $photo);
        }

        $employee = $this->serviceProviderEmployeeClass->findForService($employeeId, (int) $service->id);

        return [
            'success' => true,
            'message' => 'Employee updated.',
            'employee' => $this->toDetailArray($employee),
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

        $workingDays = $this->parseWorkingDaySchedule($payload['workingDays'] ?? null, required: true);
        if (isset($workingDays['success']) && $workingDays['success'] === false) {
            return $workingDays;
        }

        $updated = $this->serviceProviderEmployeeClass->syncWorkingDaySchedule($employee, $workingDays);
        $updated = $this->serviceProviderEmployeeClass->findForService($employeeId, (int) $service->id);

        return [
            'success' => true,
            'message' => 'Employee working days updated.',
            'employee' => $this->toDetailArray($updated),
        ];
    }

    public function uploadPhotoForProvider(int $userId, int $employeeId, UploadedFile $file): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employee = $this->serviceProviderEmployeeClass->findForService($employeeId, (int) $service->id);

        if ($employee === null) {
            return $this->fail('Employee not found.', 404);
        }

        $media = $this->replacePhoto($employee, $file);

        return [
            'success' => true,
            'message' => 'Photo uploaded.',
            'media' => $this->mapMedia($media),
            'image' => $media->url,
            'http_status' => 201,
        ];
    }

    public function deletePhotoForProvider(int $userId, int $employeeId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employee = $this->serviceProviderEmployeeClass->findForService($employeeId, (int) $service->id);

        if ($employee === null) {
            return $this->fail('Employee not found.', 404);
        }

        $employee->load('media');

        if ($employee->media->isEmpty()) {
            return $this->fail('Photo not found.', 404);
        }

        foreach ($employee->media as $oldMedia) {
            $relative = $oldMedia->publicDiskRelativePath();
            if ($relative !== null) {
                Storage::disk('public')->delete($relative);
            }
            $oldMedia->forceDelete();
        }

        return [
            'success' => true,
            'message' => 'Photo deleted.',
            'deleted' => true,
        ];
    }

    /** @param  mixed  $raw */
    private function parseWorkingDaySchedule(mixed $raw, bool $required): array
    {
        if ($raw === null || $raw === []) {
            if ($required) {
                return $this->fail('workingDays must be a non-empty array.', 422);
            }

            return [];
        }

        if (! is_array($raw)) {
            return $this->fail('workingDays must be an array.', 422);
        }

        $schedule = [];
        $seen = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                return $this->fail('Each working day entry must be an object.', 422);
            }

            $weekday = (int) ($row['weekday'] ?? 0);
            if ($weekday < 1 || $weekday > 7) {
                return $this->fail('Each weekday must be between 1 (Monday) and 7 (Sunday).', 422);
            }

            if (isset($seen[$weekday])) {
                return $this->fail('Duplicate weekday in workingDays.', 422);
            }
            $seen[$weekday] = true;

            $startsAt = $row['startsAt'] ?? null;
            $endsAt = $row['endsAt'] ?? null;

            if ($startsAt === null || $endsAt === null) {
                return $this->fail('Each working day requires startsAt and endsAt.', 422);
            }

            $schedule[] = [
                'weekday' => $weekday,
                'startsAt' => $startsAt,
                'endsAt' => $endsAt,
            ];
        }

        return $schedule;
    }

    private function toSummaryArray(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'phoneNumber' => $employee->phoneNumber,
            'email' => $employee->email,
            'image' => $this->profileImageUrl($employee),
            'serviceItems' => $employee->relationLoaded('serviceItems')
                ? $employee->serviceItems->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])->values()->all()
                : [],
        ];
    }

    private function toDetailArray(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'phoneNumber' => $employee->phoneNumber,
            'email' => $employee->email,
            'image' => $this->profileImageUrl($employee),
            'serviceItems' => $employee->relationLoaded('serviceItems')
                ? $employee->serviceItems->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->pivot->price !== null
                        ? (int) $item->pivot->price
                        : (int) $item->price,
                    'duration' => (int) $item->duration,
                ])->values()->all()
                : [],
            'workingDays' => $this->mapWorkingDays($employee),
        ];
    }

    private function mapWorkingDays(Employee $employee): array
    {
        if (! $employee->relationLoaded('workingDays')) {
            return [];
        }

        return $employee->workingDays
            ->sortBy('weekday')
            ->map(fn ($day) => [
                'weekday' => (int) $day->weekday,
                'day' => WorkingWeekday::isoToAbbrev((int) $day->weekday),
                'startsAt' => $this->formatTime($day->starts_at),
                'endsAt' => $this->formatTime($day->ends_at),
            ])
            ->values()
            ->all();
    }

    private function profileImageUrl(Employee $employee): ?string
    {
        if (! $employee->relationLoaded('media') || $employee->media->isEmpty()) {
            return null;
        }

        return $employee->media->first()->url;
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $normalized = ServiceEmployeeSchedule::normalizeTimeString($value);

        return substr($normalized, 0, 5);
    }

    private function attachPhoto(Employee $employee, UploadedFile $file): Media
    {
        $path = $file->store("employees/{$employee->id}", 'public');

        return $employee->media()->create([
            'fileType' => $file->getClientMimeType(),
            'url' => $path,
        ]);
    }

    private function replacePhoto(Employee $employee, UploadedFile $file): Media
    {
        $employee->load('media');

        foreach ($employee->media as $oldMedia) {
            $relative = $oldMedia->publicDiskRelativePath();
            if ($relative !== null) {
                Storage::disk('public')->delete($relative);
            }
            $oldMedia->forceDelete();
        }

        return $this->attachPhoto($employee, $file);
    }

    private function mapMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'fileType' => $media->fileType,
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
