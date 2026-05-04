<?php

namespace App\DAO;

use App\Models\ServicePlanPrice;
use App\Models\ServiceSubscription;
use App\Models\ServiceSubscriptionNewRequest;
use App\Models\ServiceSubscriptionPlan;
use App\Models\StorePlanPrice;
use App\Models\StoreSubscription;
use App\Models\StoreSubscriptionNewRequest;
use App\Models\StoreSubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class SubscriptionNewRequestClass implements SubscriptionNewRequestInterface
{
    public function submitStoreNewRequest(array $input): StoreSubscriptionNewRequest
    {
        return StoreSubscriptionNewRequest::query()->create($input);
    }

    public function submitServiceNewRequest(array $input): ServiceSubscriptionNewRequest
    {
        return ServiceSubscriptionNewRequest::query()->create($input);
    }

    public function listStoreNewRequests(?string $status): Collection
    {
        $q = StoreSubscriptionNewRequest::query()
            ->with([
                'subscription.store',
                'subscription.planPrice',
                'requestedPlan',
                'requestedPlanPrice',
                'requestedBy',
                'reviewer',
            ])
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        return $q->get();
    }

    public function listServiceNewRequests(?string $status): Collection
    {
        $q = ServiceSubscriptionNewRequest::query()
            ->with([
                'subscription.service',
                'subscription.servicePlanPrice',
                'requestedPlan',
                'requestedPlanPrice',
                'requestedBy',
                'reviewer',
            ])
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        return $q->get();
    }

    public function approveStoreNewRequest(StoreSubscriptionNewRequest $request, int $adminUserId): array
    {
        return DB::transaction(function () use ($request, $adminUserId) {
            $locked = StoreSubscriptionNewRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_new_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_new_request_not_pending')];
            }

            $subscription = StoreSubscription::query()->lockForUpdate()->find($locked->storeSubscriptionID);
            if (! $subscription) {
                return ['success' => false, 'message' => __('app.store_subscription_not_found_for_new_request')];
            }

            $plan = StoreSubscriptionPlan::query()->find($locked->requestedStoreSubscriptionPlanID);
            if (! $plan) {
                return ['success' => false, 'message' => __('app.store_plan_not_found')];
            }
            if (! $plan->accepting_subscriptions) {
                return ['success' => false, 'message' => __('app.store_plan_not_accepting_subscriptions')];
            }

            $planPrice = StorePlanPrice::withTrashed()->whereKey($locked->requestedPlanPriceID)->first();
            if (! $planPrice || (int) $planPrice->storeSubscriptionPlanID !== (int) $locked->requestedStoreSubscriptionPlanID) {
                return ['success' => false, 'message' => __('app.plan_price_mismatch')];
            }
            if ((int) $planPrice->duration < 1) {
                return ['success' => false, 'message' => __('app.subscription_plan_price_duration_not_found')];
            }

            $now = Carbon::now();
            $base = $subscription->endDate
                ? Carbon::parse($subscription->endDate)->max($now)
                : $now;

            $subscription->storeSubscriptionPlanID = $locked->requestedStoreSubscriptionPlanID;
            $subscription->planPriceID = $locked->requestedPlanPriceID;
            $subscription->startDate = $base->copy();
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
                'message' => __('app.store_new_plan_request_approved'),
                'subscription' => $subscription->fresh(),
                'request' => $locked->fresh(),
            ];
        });
    }

    public function approveServiceNewRequest(ServiceSubscriptionNewRequest $request, int $adminUserId): array
    {
        return DB::transaction(function () use ($request, $adminUserId) {
            $locked = ServiceSubscriptionNewRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_new_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_new_request_not_pending')];
            }

            $subscription = ServiceSubscription::query()->lockForUpdate()->find($locked->serviceSubscriptionID);
            if (! $subscription) {
                return ['success' => false, 'message' => __('app.service_subscription_not_found_for_new_request')];
            }

            $plan = ServiceSubscriptionPlan::query()->find($locked->requestedServiceSubscriptionPlanID);
            if (! $plan) {
                return ['success' => false, 'message' => __('app.service_plan_not_found')];
            }
            if (! $plan->accepting_subscriptions) {
                return ['success' => false, 'message' => __('app.service_plan_not_accepting_subscriptions')];
            }

            $planPrice = ServicePlanPrice::withTrashed()->whereKey($locked->requestedPlanPriceID)->first();
            if (! $planPrice || (int) $planPrice->serviceSubscriptionPlanID !== (int) $locked->requestedServiceSubscriptionPlanID) {
                return ['success' => false, 'message' => __('app.plan_price_mismatch')];
            }
            if ((int) $planPrice->duration < 1) {
                return ['success' => false, 'message' => __('app.subscription_plan_price_duration_not_found')];
            }

            $now = Carbon::now();
            $base = $subscription->endDate
                ? Carbon::parse($subscription->endDate)->max($now)
                : $now;

            $subscription->serviceSubscriptionPlanID = $locked->requestedServiceSubscriptionPlanID;
            $subscription->planPriceID = $locked->requestedPlanPriceID;
            $subscription->startDate = $base->copy();
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
                'message' => __('app.service_new_plan_request_approved'),
                'subscription' => $subscription->fresh(),
                'request' => $locked->fresh(),
            ];
        });
    }

    public function rejectStoreNewRequest(StoreSubscriptionNewRequest $request, int $adminUserId, ?string $reason): array
    {
        return DB::transaction(function () use ($request, $adminUserId, $reason) {
            $locked = StoreSubscriptionNewRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_new_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_new_request_not_pending')];
            }

            $now = Carbon::now();
            $locked->update([
                'status' => 'rejected',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'rejectionReason' => $reason,
            ]);

            return ['success' => true, 'message' => __('app.store_new_plan_request_rejected'), 'request' => $locked->fresh()];
        });
    }

    public function rejectServiceNewRequest(ServiceSubscriptionNewRequest $request, int $adminUserId, ?string $reason): array
    {
        return DB::transaction(function () use ($request, $adminUserId, $reason) {
            $locked = ServiceSubscriptionNewRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_new_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_new_request_not_pending')];
            }

            $now = Carbon::now();
            $locked->update([
                'status' => 'rejected',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'rejectionReason' => $reason,
            ]);

            return ['success' => true, 'message' => __('app.service_new_plan_request_rejected'), 'request' => $locked->fresh()];
        });
    }
}
