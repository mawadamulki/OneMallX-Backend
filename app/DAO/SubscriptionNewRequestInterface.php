<?php

namespace App\DAO;

use App\Models\ServiceSubscriptionNewRequest;
use App\Models\StoreSubscriptionNewRequest;
use Illuminate\Support\Collection;

interface SubscriptionNewRequestInterface
{
    public function submitStoreNewRequest(array $input): StoreSubscriptionNewRequest;

    public function submitServiceNewRequest(array $input): ServiceSubscriptionNewRequest;

    public function listStoreNewRequests(?string $status): Collection;

    public function listServiceNewRequests(?string $status): Collection;

    /**
     * @return array{success: bool, message?: string, subscription?: \App\Models\StoreSubscription, request?: StoreSubscriptionNewRequest}
     */
    public function approveStoreNewRequest(StoreSubscriptionNewRequest $request, int $adminUserId): array;

    /**
     * @return array{success: bool, message?: string, subscription?: \App\Models\ServiceSubscription, request?: ServiceSubscriptionNewRequest}
     */
    public function approveServiceNewRequest(ServiceSubscriptionNewRequest $request, int $adminUserId): array;

    /**
     * @return array{success: bool, message?: string, request?: StoreSubscriptionNewRequest}
     */
    public function rejectStoreNewRequest(StoreSubscriptionNewRequest $request, int $adminUserId, ?string $reason): array;

    /**
     * @return array{success: bool, message?: string, request?: ServiceSubscriptionNewRequest}
     */
    public function rejectServiceNewRequest(ServiceSubscriptionNewRequest $request, int $adminUserId, ?string $reason): array;
}
