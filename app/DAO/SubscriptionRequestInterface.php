<?php

namespace App\DAO;

use App\Models\ServiceSubscriptionRequest;
use App\Models\StoreSubscriptionRequest;
use Illuminate\Support\Collection;

interface SubscriptionRequestInterface
{
    public function createStoreRequest(array $data): StoreSubscriptionRequest;

    public function createServiceRequest(array $data): ServiceSubscriptionRequest;

    public function listStoreRequests(?string $status): Collection;

    public function listServiceRequests(?string $status): Collection;

    /**
     * @return array{success: bool, message?: string, user?: \App\Models\User, store?: \App\Models\Store, subscription?: \App\Models\StoreSubscription, request?: StoreSubscriptionRequest}
     */
    public function approveStoreRequest(StoreSubscriptionRequest $request, int $adminUserId): array;

    /**
     * @return array{success: bool, message?: string, user?: \App\Models\User, service?: \App\Models\Service, subscription?: \App\Models\ServiceSubscription, request?: ServiceSubscriptionRequest}
     */
    public function approveServiceRequest(ServiceSubscriptionRequest $request, int $adminUserId): array;

    /**
     * @return array{success: bool, message?: string, request?: StoreSubscriptionRequest}
     */
    public function rejectStoreRequest(StoreSubscriptionRequest $request, int $adminUserId, ?string $reason): array;

    /**
     * @return array{success: bool, message?: string, request?: ServiceSubscriptionRequest}
     */
    public function rejectServiceRequest(ServiceSubscriptionRequest $request, int $adminUserId, ?string $reason): array;
}
