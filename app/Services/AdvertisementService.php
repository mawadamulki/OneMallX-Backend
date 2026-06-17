<?php

namespace App\Services;

use App\DAO\AdvertisementInterface;
use App\DAO\ProductInterface;
use App\DAO\ServiceProviderInterface;
use App\DAO\ServiceProviderItemInterface;
use App\Models\Advertisement;
use App\Models\Media;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceSubscription;
use App\Models\Store;
use App\Models\StoreSubscription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AdvertisementService
{
    private const CAMPAIGN_DURATION_DAYS = 30;

    public function __construct(
        protected AdvertisementInterface $advertisementClass,
        protected ProductInterface $productClass,
        protected ServiceProviderInterface $serviceProviderClass,
        protected ServiceProviderItemInterface $serviceProviderItemClass,
    ) {}

    public function listPublic(?string $placement): array
    {
        $placement = $this->normalizePlacement($placement ?? 'home');

        $ads = $this->advertisementClass
            ->listActivePublic($placement)
            ->map(fn (Advertisement $ad) => $this->toPublicArray($ad));

        return [
            'success' => true,
            'ads' => $ads->values()->all(),
        ];
    }

    public function listForStoreOwner(int $userId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $planInfo = $this->resolvePlanInfoForStore((int) $store->id);
        $allowed = $planInfo['allowed'];
        $active = $this->advertisementClass->countActiveForStore((int) $store->id);

        $ads = $this->advertisementClass
            ->listForStore((int) $store->id)
            ->map(fn (Advertisement $ad) => $this->toOwnerArray($ad));

        return [
            'success' => true,
            'quota' => [
                'allowed' => $allowed,
                'active' => $active,
                'remaining' => max(0, $allowed - $active),
                'maxDurationDays' => $planInfo['duration'],
            ],
            'ads' => $ads->values()->all(),
        ];
    }

    public function showForStoreOwner(int $userId, int $adId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $ad = $this->advertisementClass->findForStore($adId, (int) $store->id);

        if ($ad === null) {
            return $this->fail('Advertisement not found.', 404);
        }

        return [
            'success' => true,
            'ad' => $this->toOwnerArray($ad),
        ];
    }

    public function createForStoreOwner(int $userId, array $payload, UploadedFile $image): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $planInfo = $this->resolvePlanInfoForStore((int) $store->id);
        $startDate = $payload['startDate'];
        $endDate = $payload['endDate'] ?? Carbon::parse($startDate)->addDays($planInfo['duration'])->toDateString();

        $dateError = $this->validateDateRange($startDate, $endDate, $planInfo['duration']);
        if ($dateError !== null) {
            return $this->fail($dateError, 422);
        }

        $target = $this->resolveStoreTarget($store, $payload);
        if (isset($target['error'])) {
            return $this->fail($target['error'], 422);
        }

        if ($this->wouldBeActiveToday($startDate, $endDate)) {
            $quotaError = $this->assertStoreQuotaAvailable((int) $store->id);
            if ($quotaError !== null) {
                return $this->fail($quotaError, 422);
            }
        }

        $path = $image->store("advertisements/stores/{$store->id}", 'public');

        $ad = $this->advertisementClass->create([
            'storeID' => (int) $store->id,
            'title' => $payload['title'],
            'image' => $path,
            'targetType' => $target['targetType'],
            'targetID' => $target['targetID'],
            'placement' => $planInfo['placement'],
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        return [
            'success' => true,
            'message' => 'Advertisement created.',
            'ad' => $this->toOwnerArray($ad),
            'http_status' => 201,
        ];
    }

    public function updateForStoreOwner(int $userId, int $adId, array $payload, ?UploadedFile $image): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $ad = $this->advertisementClass->findForStore($adId, (int) $store->id);

        if ($ad === null) {
            return $this->fail('Advertisement not found.', 404);
        }

        $planInfo = $this->resolvePlanInfoForStore((int) $store->id);
        $startDate = $payload['startDate'] ?? $ad->startDate?->toDateString();
        $endDate = $payload['endDate'] ?? $ad->endDate?->toDateString();

        $dateError = $this->validateDateRange($startDate, $endDate, $planInfo['duration']);
        if ($dateError !== null) {
            return $this->fail($dateError, 422);
        }

        if (isset($payload['targetType']) || isset($payload['productId'])) {
            $target = $this->resolveStoreTarget($store, $payload, $ad);
            if (isset($target['error'])) {
                return $this->fail($target['error'], 422);
            }

            $payload['targetType'] = $target['targetType'];
            $payload['targetID'] = $target['targetID'];
        }

        $willBeActive = $this->wouldBeActiveToday($startDate, $endDate);
        $wasActive = $this->resolveStatus($ad) === 'active';

        if ($willBeActive && ! $wasActive) {
            $quotaError = $this->assertStoreQuotaAvailable((int) $store->id);
            if ($quotaError !== null) {
                return $this->fail($quotaError, 422);
            }
        }

        $data = [];

        foreach (['title', 'targetType', 'targetID', 'startDate', 'endDate'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if ($image !== null) {
            if ($ad->image) {
                Storage::disk('public')->delete($ad->image);
            }

            $data['image'] = $image->store("advertisements/stores/{$store->id}", 'public');
        }

        if ($data === []) {
            return $this->fail('No fields to update.', 422);
        }

        $updated = $this->advertisementClass->update($ad, $data);

        return [
            'success' => true,
            'message' => 'Advertisement updated.',
            'ad' => $this->toOwnerArray($updated),
        ];
    }

    public function deleteForStoreOwner(int $userId, int $adId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $ad = $this->advertisementClass->findForStore($adId, (int) $store->id);

        if ($ad === null) {
            return $this->fail('Advertisement not found.', 404);
        }

        $this->deleteImage($ad);
        $this->advertisementClass->delete($ad);

        return [
            'success' => true,
            'message' => 'Advertisement deleted.',
            'deleted' => true,
        ];
    }

    public function listForServiceOwner(int $userId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $planInfo = $this->resolvePlanInfoForService((int) $service->id);
        $allowed = $planInfo['allowed'];
        $active = $this->advertisementClass->countActiveForService((int) $service->id);

        $ads = $this->advertisementClass
            ->listForService((int) $service->id)
            ->map(fn (Advertisement $ad) => $this->toOwnerArray($ad));

        return [
            'success' => true,
            'quota' => [
                'allowed' => $allowed,
                'active' => $active,
                'remaining' => max(0, $allowed - $active),
                'maxDurationDays' => $planInfo['duration'],
            ],
            'ads' => $ads->values()->all(),
        ];
    }

    public function showForServiceOwner(int $userId, int $adId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $ad = $this->advertisementClass->findForService($adId, (int) $service->id);

        if ($ad === null) {
            return $this->fail('Advertisement not found.', 404);
        }

        return [
            'success' => true,
            'ad' => $this->toOwnerArray($ad),
        ];
    }

    public function createForServiceOwner(int $userId, array $payload, UploadedFile $image): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $planInfo = $this->resolvePlanInfoForService((int) $service->id);
        $startDate = $payload['startDate'];
        $endDate = $payload['endDate'] ?? Carbon::parse($startDate)->addDays($planInfo['duration'])->toDateString();

        $dateError = $this->validateDateRange($startDate, $endDate, $planInfo['duration']);
        if ($dateError !== null) {
            return $this->fail($dateError, 422);
        }

        $target = $this->resolveServiceTarget($service, $payload);
        if (isset($target['error'])) {
            return $this->fail($target['error'], 422);
        }

        if ($this->wouldBeActiveToday($startDate, $endDate)) {
            $quotaError = $this->assertServiceQuotaAvailable((int) $service->id);
            if ($quotaError !== null) {
                return $this->fail($quotaError, 422);
            }
        }

        $path = $image->store("advertisements/services/{$service->id}", 'public');

        $ad = $this->advertisementClass->create([
            'serviceID' => (int) $service->id,
            'title' => $payload['title'],
            'image' => $path,
            'targetType' => $target['targetType'],
            'targetID' => $target['targetID'],
            'placement' => $planInfo['placement'],
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        return [
            'success' => true,
            'message' => 'Advertisement created.',
            'ad' => $this->toOwnerArray($ad),
            'http_status' => 201,
        ];
    }

    public function updateForServiceOwner(int $userId, int $adId, array $payload, ?UploadedFile $image): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $ad = $this->advertisementClass->findForService($adId, (int) $service->id);

        if ($ad === null) {
            return $this->fail('Advertisement not found.', 404);
        }

        $planInfo = $this->resolvePlanInfoForService((int) $service->id);
        $startDate = $payload['startDate'] ?? $ad->startDate?->toDateString();
        $endDate = $payload['endDate'] ?? $ad->endDate?->toDateString();

        $dateError = $this->validateDateRange($startDate, $endDate, $planInfo['duration']);
        if ($dateError !== null) {
            return $this->fail($dateError, 422);
        }

        if (isset($payload['targetType']) || isset($payload['serviceItemId'])) {
            $target = $this->resolveServiceTarget($service, $payload, $ad);
            if (isset($target['error'])) {
                return $this->fail($target['error'], 422);
            }

            $payload['targetType'] = $target['targetType'];
            $payload['targetID'] = $target['targetID'];
        }

        $willBeActive = $this->wouldBeActiveToday($startDate, $endDate);
        $wasActive = $this->resolveStatus($ad) === 'active';

        if ($willBeActive && ! $wasActive) {
            $quotaError = $this->assertServiceQuotaAvailable((int) $service->id);
            if ($quotaError !== null) {
                return $this->fail($quotaError, 422);
            }
        }

        $data = [];

        foreach (['title', 'targetType', 'targetID', 'startDate', 'endDate'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if ($image !== null) {
            if ($ad->image) {
                Storage::disk('public')->delete($ad->image);
            }

            $data['image'] = $image->store("advertisements/services/{$service->id}", 'public');
        }

        if ($data === []) {
            return $this->fail('No fields to update.', 422);
        }

        $updated = $this->advertisementClass->update($ad, $data);

        return [
            'success' => true,
            'message' => 'Advertisement updated.',
            'ad' => $this->toOwnerArray($updated),
        ];
    }

    public function deleteForServiceOwner(int $userId, int $adId): array
    {
        $service = $this->serviceProviderClass->findServiceByProviderId($userId);

        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        $ad = $this->advertisementClass->findForService($adId, (int) $service->id);

        if ($ad === null) {
            return $this->fail('Advertisement not found.', 404);
        }

        $this->deleteImage($ad);
        $this->advertisementClass->delete($ad);

        return [
            'success' => true,
            'message' => 'Advertisement deleted.',
            'deleted' => true,
        ];
    }

    private function toOwnerArray(Advertisement $ad): array
    {
        return [
            'id' => $ad->id,
            'title' => $ad->title,
            'image' => $this->resolvePublicUrl($ad->image),
            'targetType' => $ad->targetType,
            'targetID' => $ad->targetID,
            'placement' => $ad->placement,
            'startDate' => $ad->startDate?->toDateString(),
            'endDate' => $ad->endDate?->toDateString(),
            'status' => $this->resolveStatus($ad),
        ];
    }

    private function toPublicArray(Advertisement $ad): array
    {
        $ownerType = $ad->storeID !== null ? 'store' : 'service';
        $owner = $ad->storeID !== null ? $ad->store : $ad->service;

        return [
            'id' => $ad->id,
            'title' => $ad->title,
            'image' => $this->resolvePublicUrl($ad->image),
            'targetType' => $ad->targetType,
            'targetID' => $ad->targetID,
            'placement' => $ad->placement,
            'ownerType' => $ownerType,
            'ownerID' => $owner?->id,
            'ownerName' => $owner?->name,
        ];
    }

    private function resolveStatus(Advertisement $ad): string
    {
        $today = Carbon::today();
        $start = $ad->startDate ? Carbon::parse($ad->startDate)->startOfDay() : null;
        $end = $ad->endDate ? Carbon::parse($ad->endDate)->endOfDay() : null;

        if ($start === null || $end === null) {
            return 'scheduled';
        }

        if ($today->lt($start)) {
            return 'scheduled';
        }

        if ($today->gt($end)) {
            return 'expired';
        }

        return 'active';
    }

    private function validateDateRange(string $startDate, string $endDate, int $maxDuration): ?string
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            return 'End date must be on or after start date.';
        }

        if ($start->diffInDays($end) > $maxDuration) {
            return "Advertisement duration cannot exceed {$maxDuration} days.";
        }

        return null;
    }

    private function wouldBeActiveToday(string $startDate, string $endDate): bool
    {
        $today = Carbon::today();

        return $today->between(
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay()
        );
    }

    private function resolveStoreTarget(Store $store, array $payload, ?Advertisement $existing = null): array
    {
        $targetType = (string) ($payload['targetType'] ?? $existing?->targetType ?? 'store');

        if ($targetType === 'store') {
            return [
                'targetType' => 'store',
                'targetID' => (int) $store->id,
            ];
        }

        if ($targetType === 'product') {
            $productId = (int) ($payload['productId'] ?? $existing?->targetID ?? 0);
            $error = $this->validateStoreTarget($store, 'product', $productId);

            if ($error !== null) {
                return ['error' => $error];
            }

            return [
                'targetType' => 'product',
                'targetID' => $productId,
            ];
        }

        return ['error' => 'Store advertisements can only target a store or product.'];
    }

    private function resolveServiceTarget(Service $service, array $payload, ?Advertisement $existing = null): array
    {
        $targetType = (string) ($payload['targetType'] ?? $existing?->targetType ?? 'service');

        if ($targetType === 'service') {
            return [
                'targetType' => 'service',
                'targetID' => (int) $service->id,
            ];
        }

        if ($targetType === 'service_item') {
            $serviceItemId = (int) ($payload['serviceItemId'] ?? $existing?->targetID ?? 0);
            $error = $this->validateServiceTarget($service, 'service_item', $serviceItemId);

            if ($error !== null) {
                return ['error' => $error];
            }

            return [
                'targetType' => 'service_item',
                'targetID' => $serviceItemId,
            ];
        }

        return ['error' => 'Service advertisements can only target a service or service item.'];
    }

    private function validateStoreTarget(Store $store, string $targetType, int $targetId): ?string
    {
        if (! in_array($targetType, ['store', 'product'], true)) {
            return 'Store advertisements can only target a store or product.';
        }

        if ($targetType === 'store') {
            return (int) $store->id === $targetId
                ? null
                : 'Target store must be your own store.';
        }

        if ($targetType === 'product') {
            if ($targetId <= 0) {
                return 'Product ID is required when target type is product.';
            }
        }

        $product = Product::query()
            ->whereKey($targetId)
            ->where('storeID', $store->id)
            ->first();

        return $product !== null
            ? null
            : 'Target product not found in your store.';
    }

    private function validateServiceTarget(Service $service, string $targetType, int $targetId): ?string
    {
        if (! in_array($targetType, ['service', 'service_item'], true)) {
            return 'Service advertisements can only target a service or service item.';
        }

        if ($targetType === 'service') {
            return (int) $service->id === $targetId
                ? null
                : 'Target service must be your own service.';
        }

        if ($targetType === 'service_item') {
            if ($targetId <= 0) {
                return 'Service item ID is required when target type is service_item.';
            }
        }

        $item = $this->serviceProviderItemClass->findForService($targetId, (int) $service->id);

        return $item !== null
            ? null
            : 'Target service item not found in your service.';
    }

    private function resolvePlanInfoForStore(int $storeId): array
    {
        $subscription = StoreSubscription::query()
            ->with('storeSubscriptionPlan')
            ->where('storeID', $storeId)
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();

        return [
            'allowed' => (int) ($subscription?->storeSubscriptionPlan?->adsNumber ?? 0),
            'duration' => (int) ($subscription?->storeSubscriptionPlan?->adsDuration ?? 30),
            'placement' => (string) ($subscription?->storeSubscriptionPlan?->adsPlacement ?? 'home'),
        ];
    }

    private function resolvePlanInfoForService(int $serviceId): array
    {
        $subscription = ServiceSubscription::query()
            ->with('serviceSubscriptionPlan')
            ->where('serviceID', $serviceId)
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();

        return [
            'allowed' => (int) ($subscription?->serviceSubscriptionPlan?->adsNumber ?? 0),
            'duration' => (int) ($subscription?->serviceSubscriptionPlan?->adsDuration ?? 30),
            'placement' => (string) ($subscription?->serviceSubscriptionPlan?->adsPlacement ?? 'home'),
        ];
    }

    private function assertStoreQuotaAvailable(int $storeId): ?string
    {
        $planInfo = $this->resolvePlanInfoForStore($storeId);
        $allowed = $planInfo['allowed'];

        if ($allowed <= 0) {
            return 'Your plan does not include advertisement slots.';
        }

        $active = $this->advertisementClass->countActiveForStore($storeId);

        if ($active >= $allowed) {
            return "You have reached your plan limit of {$allowed} active advertisement(s).";
        }

        return null;
    }

    private function assertServiceQuotaAvailable(int $serviceId): ?string
    {
        $planInfo = $this->resolvePlanInfoForService($serviceId);
        $allowed = $planInfo['allowed'];

        if ($allowed <= 0) {
            return 'Your plan does not include advertisement slots.';
        }

        $active = $this->advertisementClass->countActiveForService($serviceId);

        if ($active >= $allowed) {
            return "You have reached your plan limit of {$allowed} active advertisement(s).";
        }

        return null;
    }

    private function normalizePlacement(string $placement): string
    {
        return in_array($placement, ['home', 'deals'], true) ? $placement : 'home';
    }

    private function resolvePublicUrl(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        return (new Media(['url' => $stored]))->url;
    }

    private function deleteImage(Advertisement $ad): void
    {
        if ($ad->image) {
            Storage::disk('public')->delete($ad->image);
        }
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
