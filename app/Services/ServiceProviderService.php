<?php

namespace App\Services;

use App\DAO\ServiceProviderInterface;
use App\Models\Media;
use App\Models\Service;
use App\Support\WorkingWeekday;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ServiceProviderService
{
    public function __construct(
        protected ServiceProviderInterface $serviceProviderClass,
    ) {}

    public function showForProvider(int $userId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        return [
            'success' => true,
            'service' => $this->toFullArray($service),
        ];
    }

    public function updateForProvider(int $userId, array $payload): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $data = [];

        foreach (['name', 'description', 'openTime', 'closeTime', 'locationID', 'paymentAccount'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if ($data === []) {
            return $this->fail('No fields to update.', 422);
        }

        $updated = $this->serviceProviderClass->updateService($service, $data);

        return [
            'success' => true,
            'message' => 'Service updated.',
            'service' => $this->toFullArray($updated),
        ];
    }

    public function syncWorkingDaysForProvider(int $userId, array $payload): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $weekdays = array_values(array_unique(array_map('intval', $payload['weekdays'] ?? [])));

        foreach ($weekdays as $iso) {
            if ($iso < 1 || $iso > 7) {
                return $this->fail('Each weekday must be between 1 (Monday) and 7 (Sunday).', 422);
            }
        }

        $updated = $this->serviceProviderClass->syncWorkingDays($service, $weekdays);

        return [
            'success' => true,
            'message' => 'Working days updated.',
            'service' => $this->toFullArray($updated),
        ];
    }

    public function attachMediaForProvider(int $userId, UploadedFile $file): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $path = $file->store("services/{$service->id}", 'public');

        $media = $service->media()->create([
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
            ->where('mediableType', Service::class)
            ->where('mediableID', $service->id)
            ->first();

        if ($media === null) {
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

    private function toFullArray(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'areaID' => $service->areaID,
            'locationID' => $service->locationID,
            'openTime' => $service->openTime,
            'closeTime' => $service->closeTime,
            'status' => $service->status,
            'accountStatus' => $service->accountStatus,
            'paymentAccount' => $service->paymentAccount,
            'weekdays' => $service->relationLoaded('workingDays')
                ? $service->workingDays->sortBy('weekday')->pluck('weekday')->values()->all()
                : [],
            'days' => $service->relationLoaded('workingDays')
                ? $service->workingDays->sortBy('weekday')->map(fn ($d) => WorkingWeekday::isoToAbbrev($d->weekday))->values()->all()
                : [],
            'media' => $this->mapMediaCollection($service),
            'area' => $service->relationLoaded('area') && $service->area
                ? [
                    'id' => $service->area->id,
                    'name' => $service->area->name,
                    'category' => $service->area->category,
                    'floor' => $service->area->relationLoaded('floor') && $service->area->floor
                        ? [
                            'id' => $service->area->floor->id,
                            'name' => $service->area->floor->name,
                        ]
                        : null,
                ]
                : null,
            'location' => $service->relationLoaded('location') && $service->location
                ? [
                    'id' => $service->location->id,
                    'location' => $service->location->location,
                ]
                : null,
        ];
    }

    private function mapMediaCollection(Service $service): array
    {
        if (! $service->relationLoaded('media')) {
            return [];
        }

        return $service->media->map(fn ($media) => $this->mapMedia($media))->values()->all();
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
