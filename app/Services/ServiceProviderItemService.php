<?php

namespace App\Services;

use App\DAO\ServiceProviderEmployeeInterface;
use App\DAO\ServiceProviderItemInterface;
use App\DAO\ServiceProviderInterface;
use App\Models\Media;
use App\Models\ServiceItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ServiceProviderItemService
{
    public function __construct(
        protected ServiceProviderInterface $serviceProviderClass,
        protected ServiceProviderItemInterface $serviceProviderItemClass,
        protected ServiceProviderEmployeeInterface $serviceProviderEmployeeClass,
    ) {}

    public function listForProvider(int $userId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $items = $this->serviceProviderItemClass
            ->listForService((int) $service->id)
            ->map(fn (ServiceItem $item) => $this->toSummaryArray($item));

        return [
            'success' => true,
            'service' => ['id' => $service->id, 'name' => $service->name],
            'items' => $items,
        ];
    }

    public function listNamesForProvider(int $userId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $items = $this->serviceProviderItemClass->listNamesForService((int) $service->id);

        return [
            'success' => true,
            'items' => $items->map(fn (ServiceItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (int) $item->price,
            ])->values()->all(),
        ];
    }

    public function showForProvider(int $userId, int $itemId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $item = $this->serviceProviderItemClass->findForService($itemId, (int) $service->id);

        if ($item === null) {
            return $this->fail('Service item not found.', 404);
        }

        return [
            'success' => true,
            'item' => $this->toFullArray($item),
        ];
    }

    public function createForProvider(int $userId, array $payload): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $employees = $payload['employees'] ?? [];
        $employeeError = $this->validateEmployeesForService((int) $service->id, $employees);
        if ($employeeError !== null) {
            return $this->fail($employeeError, 422);
        }

        $item = $this->serviceProviderItemClass->createForService((int) $service->id, [
            'name' => $payload['name'],
            'price' => (int) $payload['price'],
            'duration' => (int) $payload['duration'],
            'employees' => $employees,
        ]);

        return [
            'success' => true,
            'message' => 'Service item created.',
            'item' => $this->toFullArray($item),
            'http_status' => 201,
        ];
    }

    public function updateForProvider(int $userId, int $itemId, array $payload): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $item = $this->serviceProviderItemClass->findForService($itemId, (int) $service->id);

        if ($item === null) {
            return $this->fail('Service item not found.', 404);
        }

        $data = [];

        foreach (['name', 'price', 'duration'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = (int) $payload[$field];
            }
        }

        if ($data === []) {
            return $this->fail('No fields to update.', 422);
        }

        $updated = $this->serviceProviderItemClass->update($item, $data);

        return [
            'success' => true,
            'message' => 'Service item updated.',
            'item' => $this->toFullArray($updated),
        ];
    }

    public function deleteForProvider(int $userId, int $itemId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $item = $this->serviceProviderItemClass->findForService($itemId, (int) $service->id);

        if ($item === null) {
            return $this->fail('Service item not found.', 404);
        }

        $this->serviceProviderItemClass->delete($item);

        return [
            'success' => true,
            'message' => 'Service item deleted.',
            'deleted' => true,
        ];
    }

    public function attachMediaForProvider(int $userId, int $itemId, UploadedFile $file): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $item = $this->serviceProviderItemClass->findForService($itemId, (int) $service->id);

        if ($item === null) {
            return $this->fail('Service item not found.', 404);
        }

        $path = $file->store("service-items/{$item->id}", 'public');

        $media = $item->media()->create([
            'fileType' => $file->getClientMimeType(),
            'url' => $path,
        ]);

        return [
            'success' => true,
            'message' => 'Photo uploaded.',
            'media' => $this->mapMedia($media),
            'http_status' => 201,
        ];
    }

    public function deleteMediaForProvider(int $userId, int $mediaId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $media = Media::query()
            ->whereKey($mediaId)
            ->where('mediableType', ServiceItem::class)
            ->first();

        if ($media === null) {
            return $this->fail('Media not found.', 404);
        }

        $item = $this->serviceProviderItemClass->findForService((int) $media->mediableID, (int) $service->id);

        if ($item === null) {
            return $this->fail('Media not found.', 404);
        }

        $relative = $media->publicDiskRelativePath();
        if ($relative !== null) {
            Storage::disk('public')->delete($relative);
        }

        $media->forceDelete();

        return [
            'success' => true,
            'message' => 'Media deleted.',
            'deleted' => true,
        ];
    }

    /** @param  array<int, array{employeeID: int, price?: int|null}>  $employees */
    private function validateEmployeesForService(int $serviceId, array $employees): ?string
    {
        $ids = array_values(array_unique(array_map(
            fn ($row) => (int) ($row['employeeID'] ?? 0),
            $employees
        )));

        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));

        if (! $this->serviceProviderEmployeeClass->allBelongToService($serviceId, $ids)) {
            return 'One or more employees do not belong to your service.';
        }

        return null;
    }

    private function toSummaryArray(ServiceItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $this->priceRangeForItem($item),
            'duration' => (int) $item->duration,
            'employeeCount' => $item->relationLoaded('employees') ? $item->employees->count() : 0,
            'media' => $this->mapMediaCollection($item),
        ];
    }

    private function toFullArray(ServiceItem $item): array
    {
        return [
            'id' => $item->id,
            'serviceID' => $item->serviceID,
            'name' => $item->name,
            'price' => $this->priceRangeForItem($item),
            'duration' => (int) $item->duration,
            'media' => $this->mapMediaCollection($item),
            'employees' => $item->relationLoaded('employees')
                ? $item->employees->map(fn ($employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'price' => $employee->pivot->price !== null
                        ? (int) $employee->pivot->price
                        : (int) $item->price,
                ])->values()->all()
                : [],
        ];
    }

    /** @return array{min: int, max: int} */
    private function priceRangeForItem(ServiceItem $item): array
    {
        if ($item->relationLoaded('employees') && $item->employees->isNotEmpty()) {
            $prices = $item->employees
                ->map(fn ($employee) => $employee->pivot->price !== null
                    ? (int) $employee->pivot->price
                    : (int) $item->price)
                ->values()
                ->all();

            return [
                'min' => min($prices),
                'max' => max($prices),
            ];
        }

        $base = (int) $item->price;

        return [
            'min' => $base,
            'max' => $base,
        ];
    }

    private function mapMediaCollection(ServiceItem $item): array
    {
        if (! $item->relationLoaded('media')) {
            return [];
        }

        return $item->media->map(fn ($media) => $this->mapMedia($media))->values()->all();
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

