<?php

namespace App\Services;

use App\DAO\ServiceProviderInterface;
use App\Models\Location;
use App\Models\Media;
use App\Models\Service;
use App\Models\ServiceSubscription;
use App\Support\BusinessCategoryFormatter;
use App\Support\WorkingWeekday;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServiceProviderService
{
    public function __construct(
        protected ServiceProviderInterface $serviceProviderClass,
    ) {}

    public function showForOwner(int $userId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        return [
            'success' => true,
            'service' => $this->toOwnerArray($service),
        ];
    }

    public function planForOwner(int $userId): array
    {
        $subscription = ServiceSubscription::query()
            ->with(['serviceSubscriptionPlan.floor', 'servicePlanPrice'])
            ->whereHas('service', fn ($q) => $q->where('serviceOwnerID', $userId))
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();

        if ($subscription === null) {
            return $this->fail('No subscription found for this account.', 404);
        }

        return [
            'success' => true,
            'plan' => $this->toOwnerPlanArray($subscription),
        ];
    }

    public function updateForOwner(int $userId, array $payload): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $data = [];

        foreach (['name', 'description', 'openTime', 'closeTime', 'paymentAccount'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        $hasLocation = array_key_exists('location', $payload);

        if ($data === [] && ! $hasLocation) {
            return $this->fail('No fields to update.', 422);
        }

        $updated = DB::transaction(function () use ($service, $data, $payload, $hasLocation, $userId) {
            if ($hasLocation && $payload['location'] !== null) {
                if ($service->locationID !== null && $service->location !== null) {
                    $service->location->update(['location' => $payload['location']]);
                } else {
                    $location = Location::query()->create([
                        'location' => $payload['location'],
                    ]);
                    $data['locationID'] = $location->id;
                }
            }

            if ($data !== []) {
                $this->serviceProviderClass->updateService($service, $data);
            }

            return $this->serviceProviderClass->findServiceByProviderId($userId);
        });

        return [
            'success' => true,
            'message' => 'Service updated.',
            'service' => $this->toOwnerArray($updated),
        ];
    }

    public function updateCustomizationForOwner(int $userId, array $payload): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $data = [];

        foreach (['customization', 'customizationData'] as $field) {
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
            'message' => 'Customization saved.',
            'customization' => $updated->customization,
            'customizationData' => $updated->customizationData,
        ];
    }

    public function syncWorkingDaysForOwner(int $userId, array $payload): array
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
            'service' => $this->toOwnerArray($updated),
        ];
    }

    public function attachMediaForOwner(int $userId, UploadedFile $file): array
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

    public function attachLogoForOwner(int $userId, UploadedFile $file): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        if ($service->logo) {
            Storage::disk('public')->delete($service->logo);
        }

        $path = $file->store("services/{$service->id}/logo", 'public');
        $updated = $this->serviceProviderClass->updateService($service, ['logo' => $path]);

        return [
            'success' => true,
            'message' => 'Logo uploaded.',
            'logo' => $this->resolvePublicUrl($updated->logo),
            'http_status' => 201,
        ];
    }

    public function deleteLogoForOwner(int $userId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        if ($service->logo) {
            Storage::disk('public')->delete($service->logo);
            $this->serviceProviderClass->updateService($service, ['logo' => null]);
        }

        return [
            'success' => true,
            'message' => 'Logo deleted.',
            'deleted' => true,
        ];
    }

    public function deleteMediaForOwner(int $userId, int $mediaId): array
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

    private function toOwnerArray(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'logo' => $this->resolvePublicUrl($service->logo),
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
                    'category' => BusinessCategoryFormatter::toArray($service->area->category),
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
            'customization' => $service->customization,
            'customizationData' => $service->customizationData,
        ];
    }

    private function toOwnerPlanArray(ServiceSubscription $subscription): array
    {
        $plan = $subscription->serviceSubscriptionPlan;
        $price = $subscription->servicePlanPrice;
        $floor = $plan?->floor;

        return [
            'id' => $plan?->id,
            'name' => $plan?->name,
            'serviceSpace' => $plan?->serviceSpace,
            'adsNumber' => $plan?->adsNumber,
            'adsDuration' => $plan?->adsDuration,
            'adsPlacement' => $plan?->adsPlacement,
            'startDate' => $this->formatSubscriptionDate($subscription->startDate),
            'endDate' => $this->formatSubscriptionDate($subscription->endDate),
            'autoRenew' => (bool) $subscription->autoRenew,
            'durationMonths' => $price ? (int) $price->duration : null,
            'price' => $price?->price,
            'floor' => $floor
                ? [
                    'id' => $floor->id,
                    'name' => $floor->name,
                    'number' => $floor->number,
                ]
                : null,
        ];
    }

    private function formatSubscriptionDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
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

    private function resolvePublicUrl(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        return (new Media(['url' => $stored]))->url;
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
