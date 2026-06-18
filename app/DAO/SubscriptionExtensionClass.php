<?php

namespace App\DAO;

use App\Models\ServicePlanPrice;
use App\Models\ServiceSubscription;
use App\Models\ServiceSubscriptionExtensionRequest;
use App\Models\StorePlanPrice;
use App\Models\StoreSubscription;
use App\Models\StoreSubscriptionExtensionRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubscriptionExtensionClass implements SubscriptionExtensionInterface
{
    public function listStoreExtensionRequests(?string $status): Collection
    {
        $q = StoreSubscriptionExtensionRequest::query()
            ->with([
                'subscription.store',
                'subscription.storeSubscriptionPlan',
                'subscription.planPrice',
                'requestedBy',
                'reviewer',
            ])
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        return $q->get();
    }

    public function listServiceExtensionRequests(?string $status): Collection
    {
        $q = ServiceSubscriptionExtensionRequest::query()
            ->with([
                'subscription.service',
                'subscription.servicePlanPrice',
                'requestedBy',
                'reviewer',
            ])
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        return $q->get();
    }

    public function approveStoreExtensionRequest(StoreSubscriptionExtensionRequest $request, int $adminUserId): array
    {
        return DB::transaction(function () use ($request, $adminUserId) {
            $locked = StoreSubscriptionExtensionRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_extension_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_extension_request_not_pending')];
            }

            $subscription = StoreSubscription::query()->lockForUpdate()->find($locked->storeSubscriptionID);
            if (! $subscription) {
                return ['success' => false, 'message' => __('app.store_subscription_not_found_for_extension')];
            }

            $planPrice = StorePlanPrice::withTrashed()->whereKey($subscription->planPriceID)->first();
            if (! $planPrice || (int) $planPrice->duration < 1) {
                return ['success' => false, 'message' => __('app.subscription_plan_price_duration_not_found')];
            }

            $now = Carbon::now();
            $base = $subscription->endDate
                ? Carbon::parse($subscription->endDate)->max($now)
                : $now;
            $subscription->endDate = $base->copy()->addMonths((int) $planPrice->duration);
            $subscription->save();

            $locked->update([
                'status' => 'approved',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'rejectionReason' => null,
            ]);

            return [
                'success' => true,
                'message' => __('app.store_subscription_extension_approved'),
                'subscription' => $subscription->fresh(),
                'request' => $locked->fresh(),
            ];
        });
    }

    public function approveServiceExtensionRequest(ServiceSubscriptionExtensionRequest $request, int $adminUserId): array
    {
        return DB::transaction(function () use ($request, $adminUserId) {
            $locked = ServiceSubscriptionExtensionRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_extension_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_extension_request_not_pending')];
            }

            $subscription = ServiceSubscription::query()->lockForUpdate()->find($locked->serviceSubscriptionID);
            if (! $subscription) {
                return ['success' => false, 'message' => __('app.service_subscription_not_found_for_extension')];
            }

            $planPrice = ServicePlanPrice::withTrashed()->whereKey($subscription->planPriceID)->first();
            if (! $planPrice || (int) $planPrice->duration < 1) {
                return ['success' => false, 'message' => __('app.subscription_plan_price_duration_not_found')];
            }

            $now = Carbon::now();
            $base = $subscription->endDate
                ? Carbon::parse($subscription->endDate)->max($now)
                : $now;
            $subscription->endDate = $base->copy()->addMonths((int) $planPrice->duration);
            $subscription->save();

            $locked->update([
                'status' => 'approved',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'rejectionReason' => null,
            ]);

            return [
                'success' => true,
                'message' => __('app.service_subscription_extension_approved'),
                'subscription' => $subscription->fresh(),
                'request' => $locked->fresh(),
            ];
        });
    }

    public function rejectStoreExtensionRequest(StoreSubscriptionExtensionRequest $request, int $adminUserId, ?string $reason): array
    {
        return DB::transaction(function () use ($request, $adminUserId, $reason) {
            $locked = StoreSubscriptionExtensionRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_extension_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_extension_request_not_pending')];
            }

            $now = Carbon::now();
            $locked->update([
                'status' => 'rejected',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'rejectionReason' => $reason,
            ]);

            return ['success' => true, 'message' => __('app.store_subscription_extension_rejected'), 'request' => $locked->fresh()];
        });
    }

    public function rejectServiceExtensionRequest(ServiceSubscriptionExtensionRequest $request, int $adminUserId, ?string $reason): array
    {
        return DB::transaction(function () use ($request, $adminUserId, $reason) {
            $locked = ServiceSubscriptionExtensionRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_extension_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_extension_request_not_pending')];
            }

            $now = Carbon::now();
            $locked->update([
                'status' => 'rejected',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'rejectionReason' => $reason,
            ]);

            return ['success' => true, 'message' => __('app.service_subscription_extension_rejected'), 'request' => $locked->fresh()];
        });
    }

    public function insertStoreSubscriptionExtensionRequest(array $data): StoreSubscriptionExtensionRequest
    {
        return StoreSubscriptionExtensionRequest::query()->create($data);
    }

    public function insertServiceSubscriptionExtensionRequest(array $data): ServiceSubscriptionExtensionRequest
    {
        return ServiceSubscriptionExtensionRequest::query()->create($data);
    }
}
