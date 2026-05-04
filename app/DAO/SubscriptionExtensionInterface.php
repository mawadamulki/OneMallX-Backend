<?php

namespace App\DAO;

use App\Models\ServiceSubscriptionExtensionRequest;
use App\Models\StoreSubscriptionExtensionRequest;
use Illuminate\Support\Collection;

interface SubscriptionExtensionInterface
{
    public function listStoreExtensionRequests(?string $status): Collection;

    public function listServiceExtensionRequests(?string $status): Collection;

    /**
     * @return array{success: bool, message?: string, subscription?: \App\Models\StoreSubscription, request?: StoreSubscriptionExtensionRequest}
     */
    public function approveStoreExtensionRequest(StoreSubscriptionExtensionRequest $request, int $adminUserId): array;

    /**
     * @return array{success: bool, message?: string, subscription?: \App\Models\ServiceSubscription, request?: ServiceSubscriptionExtensionRequest}
     */
    public function approveServiceExtensionRequest(ServiceSubscriptionExtensionRequest $request, int $adminUserId): array;

    /**
     * @return array{success: bool, message?: string, request?: StoreSubscriptionExtensionRequest}
     */
    public function rejectStoreExtensionRequest(StoreSubscriptionExtensionRequest $request, int $adminUserId, ?string $reason): array;

    /**
     * @return array{success: bool, message?: string, request?: ServiceSubscriptionExtensionRequest}
     */
    public function rejectServiceExtensionRequest(ServiceSubscriptionExtensionRequest $request, int $adminUserId, ?string $reason): array;

    public function insertStoreSubscriptionExtensionRequest(array $data): StoreSubscriptionExtensionRequest;

    public function insertServiceSubscriptionExtensionRequest(array $data): ServiceSubscriptionExtensionRequest;
}
