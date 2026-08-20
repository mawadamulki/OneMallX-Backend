<?php

namespace App\DAO;

use App\Models\Area;
use App\Models\Service;
use App\Models\ServiceSubscription;
use App\Models\ServiceSubscriptionPlan;
use App\Models\ServiceSubscriptionRequest;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\StoreSubscriptionPlan;
use App\Models\StoreSubscriptionRequest;
use App\Models\User;
use App\Support\WorkingWeekday;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SubscriptionRequestClass implements SubscriptionRequestInterface
{
    public function createStoreRequest(array $data): StoreSubscriptionRequest
    {
        $payload = $data;
        $payload['password'] = Hash::make($payload['password']);
        $payload['status'] = 'pending';

        return StoreSubscriptionRequest::create($payload)->load('businessCategory');
    }

    public function createServiceRequest(array $data): ServiceSubscriptionRequest
    {
        $payload = $data;
        $payload['password'] = Hash::make($payload['password']);
        $payload['status'] = 'pending';

        return ServiceSubscriptionRequest::create($payload)->load('businessCategory');
    }

    public function listStoreRequests(?string $status): Collection
    {
        $q = StoreSubscriptionRequest::query()
            ->with([
                'businessCategory',
                'requestedPlan.floor',
                'requestedPlanPrice',
                'reviewer',
            ])
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        return $q->get();
    }

    public function listServiceRequests(?string $status): Collection
    {
        $q = ServiceSubscriptionRequest::query()
            ->with([
                'businessCategory',
                'requestedPlan.floor',
                'requestedPlanPrice',
                'reviewer',
            ])
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        return $q->get();
    }

    public function approveStoreRequest(StoreSubscriptionRequest $request, int $adminUserId): array
    {
        return DB::transaction(function () use ($request, $adminUserId) {
            $locked = StoreSubscriptionRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_request_not_pending')];
            }
            if (User::where('email', $locked->email)->exists()) {
                return ['success' => false, 'message' => __('app.email_already_registered')];
            }

            $planPrice = $locked->planPrice;
            if (! $planPrice || (int) $planPrice->storeSubscriptionPlanID !== (int) $locked->storeSubscriptionPlanID) {
                return ['success' => false, 'message' => __('app.plan_price_mismatch')];
            }

            $pick = $this->pickRandomAvailableStoreAreaId(
                (int) $locked->storeSubscriptionPlanID,
                $locked->businessCategoryID !== null ? (int) $locked->businessCategoryID : null
            );
            if ($pick['areaId'] === null) {
                $message = match ($pick['reason']) {
                    'plan_missing' => __('app.store_plan_not_found'),
                    'plan_closed' => __('app.store_plan_not_accepting_subscriptions'),
                    'none_for_category' => __('app.no_areas_for_business_category_in_store_plan'),
                    'none' => __('app.no_areas_assigned_to_store_plan'),
                    'full_for_category' => __('app.no_available_area_for_business_category_in_store_plan'),
                    default => __('app.no_available_area_in_store_plan'),
                };

                return ['success' => false, 'message' => $message];
            }
            $areaId = $pick['areaId'];

            $now = Carbon::now();
            $userId = DB::table('users')->insertGetId([
                'name' => $locked->applicantName,
                'email' => $locked->email,
                'password' => $locked->password,
                'phoneNumber' => $locked->phoneNumber,
                'status' => 'inactive',
                'otp_code' => null,
                'otp_expires_at' => null,
                'is_verified' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $user = User::find($userId);
            $user->syncRoles(['Store Owner']);

            $store = Store::create([
                'name' => $locked->storeName,
                'storeOwnerID' => $user->id,
                'areaID' => $areaId,
                'businessCategoryID' => $locked->businessCategoryID,
                'description' => $locked->description,
                'status' => $locked->storeStatus,
                'accountStatus' => 'notActive',
                'paymentAccount' => $locked->paymentAccount,
            ]);

            $startDate = $now->copy();
            $endDate = $now->copy()->addMonths((int) $planPrice->duration);

            $subscription = StoreSubscription::create([
                'storeID' => $store->id,
                'storeSubscriptionPlanID' => $locked->storeSubscriptionPlanID,
                'planPriceID' => $locked->planPriceID,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'autoRenew' => false,
            ]);

            $locked->update([
                'status' => 'approved',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'createdUserID' => $user->id,
                'createdStoreID' => $store->id,
                'createdSubscriptionID' => $subscription->id,
            ]);

            return [
                'success' => true,
                'user' => $user,
                'store' => $store,
                'subscription' => $subscription,
                'request' => $locked->fresh(),
            ];
        });
    }

    public function approveServiceRequest(ServiceSubscriptionRequest $request, int $adminUserId): array
    {
        return DB::transaction(function () use ($request, $adminUserId) {
            $locked = ServiceSubscriptionRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_request_not_pending')];
            }
            if (User::where('email', $locked->email)->exists()) {
                return ['success' => false, 'message' => __('app.email_already_registered')];
            }

            $planPrice = $locked->planPrice;
            if (! $planPrice || (int) $planPrice->serviceSubscriptionPlanID !== (int) $locked->serviceSubscriptionPlanID) {
                return ['success' => false, 'message' => __('app.plan_price_mismatch')];
            }

            $pick = $this->pickRandomAvailableServiceAreaId(
                (int) $locked->serviceSubscriptionPlanID,
                $locked->businessCategoryID !== null ? (int) $locked->businessCategoryID : null
            );
            if ($pick['areaId'] === null) {
                $message = match ($pick['reason']) {
                    'plan_missing' => __('app.service_plan_not_found'),
                    'plan_closed' => __('app.service_plan_not_accepting_subscriptions'),
                    'none_for_category' => __('app.no_areas_for_business_category_in_service_plan'),
                    'none' => __('app.no_areas_assigned_to_service_plan'),
                    'full_for_category' => __('app.no_available_area_for_business_category_in_service_plan'),
                    default => __('app.no_available_area_in_service_plan'),
                };

                return ['success' => false, 'message' => $message];
            }
            $areaId = $pick['areaId'];

            $now = Carbon::now();
            $userId = DB::table('users')->insertGetId([
                'name' => $locked->applicantName,
                'email' => $locked->email,
                'password' => $locked->password,
                'phoneNumber' => $locked->phoneNumber,
                'status' => 'inactive',
                'otp_code' => null,
                'otp_expires_at' => null,
                'is_verified' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $user = User::find($userId);
            $user->syncRoles(['Service Provider']);

            $service = Service::create([
                'name' => $locked->serviceName,
                'serviceOwnerID' => $user->id,
                'price' => $locked->price,
                'areaID' => $areaId,
                'businessCategoryID' => $locked->businessCategoryID,
                'description' => $locked->description,
                'paymentAccount' => $locked->paymentAccount,
                'openTime' => $locked->openTime,
                'closeTime' => $locked->closeTime,
                'duration' => $locked->duration,
                'locationID' => $locked->locationID,
                'status' => $locked->serviceStatus ?? 'pending',
                'accountStatus' => 'notActive',
            ]);

            WorkingWeekday::syncServiceFromLegacyCsv($service, $locked->daysOfWeek);

            $startDate = $now->copy();
            $endDate = $now->copy()->addMonths((int) $planPrice->duration);

            $subscription = ServiceSubscription::create([
                'serviceID' => $service->id,
                'serviceSubscriptionPlanID' => $locked->serviceSubscriptionPlanID,
                'planPriceID' => $locked->planPriceID,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'autoRenew' => false,
            ]);

            $locked->update([
                'status' => 'approved',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'createdUserID' => $user->id,
                'createdServiceID' => $service->id,
                'createdSubscriptionID' => $subscription->id,
            ]);

            return [
                'success' => true,
                'user' => $user,
                'service' => $service,
                'subscription' => $subscription,
                'request' => $locked->fresh(),
            ];
        });
    }

    public function rejectStoreRequest(StoreSubscriptionRequest $request, int $adminUserId, ?string $reason): array
    {
        return DB::transaction(function () use ($request, $adminUserId, $reason) {
            $locked = StoreSubscriptionRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_request_not_pending')];
            }

            $now = Carbon::now();
            $locked->update([
                'status' => 'rejected',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'rejectionReason' => $reason,
            ]);

            return ['success' => true, 'request' => $locked->fresh()];
        });
    }

    public function rejectServiceRequest(ServiceSubscriptionRequest $request, int $adminUserId, ?string $reason): array
    {
        return DB::transaction(function () use ($request, $adminUserId, $reason) {
            $locked = ServiceSubscriptionRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['success' => false, 'message' => __('app.subscription_request_not_found')];
            }
            if ($locked->status !== 'pending') {
                return ['success' => false, 'message' => __('app.subscription_request_not_pending')];
            }

            $now = Carbon::now();
            $locked->update([
                'status' => 'rejected',
                'reviewedByUserID' => $adminUserId,
                'reviewedAt' => $now,
                'rejectionReason' => $reason,
            ]);

            return ['success' => true, 'request' => $locked->fresh()];
        });
    }

    public function activateApprovedAccountByEmail(string $email): array
    {
        return DB::transaction(function () use ($email) {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user === null) {
                return ['success' => false, 'message' => __('app.user_not_found')];
            }

            $storeRequest = StoreSubscriptionRequest::query()
                ->where('email', $email)
                ->where('status', 'approved')
                ->where('createdUserID', $user->id)
                ->orderByDesc('id')
                ->first();

            $serviceRequest = ServiceSubscriptionRequest::query()
                ->where('email', $email)
                ->where('status', 'approved')
                ->where('createdUserID', $user->id)
                ->orderByDesc('id')
                ->first();

            if ($storeRequest === null && $serviceRequest === null) {
                return ['success' => false, 'message' => __('app.subscription_request_not_found_for_email')];
            }

            $now = Carbon::now();
            $accountType = null;

            if ($storeRequest !== null && $storeRequest->createdStoreID !== null) {
                $store = Store::query()->whereKey($storeRequest->createdStoreID)->lockForUpdate()->first();
                if ($store !== null && $store->accountStatus !== 'active') {
                    $store->update(['accountStatus' => 'active']);
                }

                $subscription = StoreSubscription::query()
                    ->with('planPrice')
                    ->whereKey($storeRequest->createdSubscriptionID)
                    ->first();

                if ($subscription !== null && $subscription->planPrice !== null) {
                    $subscription->update([
                        'startDate' => $now,
                        'endDate' => $now->copy()->addMonths((int) $subscription->planPrice->duration),
                    ]);
                }

                $accountType = 'store';
            }

            if ($serviceRequest !== null && $serviceRequest->createdServiceID !== null) {
                $service = Service::query()->whereKey($serviceRequest->createdServiceID)->lockForUpdate()->first();
                if ($service !== null && $service->accountStatus !== 'active') {
                    $service->update(['accountStatus' => 'active']);
                }

                $subscription = ServiceSubscription::query()
                    ->with('servicePlanPrice')
                    ->whereKey($serviceRequest->createdSubscriptionID)
                    ->first();

                if ($subscription !== null && $subscription->servicePlanPrice !== null) {
                    $subscription->update([
                        'startDate' => $now,
                        'endDate' => $now->copy()->addMonths((int) $subscription->servicePlanPrice->duration),
                    ]);
                }

                $accountType = $accountType === null ? 'service' : $accountType;
            }

            if ($user->status !== 'active') {
                $user->update(['status' => 'active']);
            }

            return [
                'success' => true,
                'message' => __('app.subscription_account_activated'),
                'accountType' => $accountType,
                'user' => $user->fresh(['roles']),
            ];
        });
    }

    /**
     * @return array{areaId: int|null, reason?: 'plan_missing'|'plan_closed'|'none'|'none_for_category'|'full'|'full_for_category'}
     */
    private function pickRandomAvailableStoreAreaId(int $storeSubscriptionPlanId, ?int $businessCategoryId = null): array
    {
        $plan = StoreSubscriptionPlan::query()->find($storeSubscriptionPlanId);
        if (! $plan) {
            return ['areaId' => null, 'reason' => 'plan_missing'];
        }

        if (! $plan->accepting_subscriptions) {
            return ['areaId' => null, 'reason' => 'plan_closed'];
        }

        $areasQuery = $plan->areas()->where('usageType', 'store');

        if ($businessCategoryId !== null) {
            $areasQuery->where('categoryID', $businessCategoryId);
        }

        $areaIds = $areasQuery->pluck('id')->shuffle();

        if ($areaIds->isEmpty()) {
            return ['areaId' => null, 'reason' => $businessCategoryId !== null ? 'none_for_category' : 'none'];
        }

        foreach ($areaIds as $id) {
            $area = Area::query()->whereKey($id)->lockForUpdate()->first();
            if (! $area) {
                continue;
            }
            $used = Store::query()->where('areaID', $area->id)->count();
            if ($used < $area->maxCapacity) {
                return ['areaId' => $area->id];
            }
        }

        return ['areaId' => null, 'reason' => $businessCategoryId !== null ? 'full_for_category' : 'full'];
    }

    /**
     * @return array{areaId: int|null, reason?: 'plan_missing'|'plan_closed'|'none'|'none_for_category'|'full'|'full_for_category'}
     */
    private function pickRandomAvailableServiceAreaId(int $serviceSubscriptionPlanId, ?int $businessCategoryId = null): array
    {
        $plan = ServiceSubscriptionPlan::query()->find($serviceSubscriptionPlanId);
        if (! $plan) {
            return ['areaId' => null, 'reason' => 'plan_missing'];
        }

        if (! $plan->accepting_subscriptions) {
            return ['areaId' => null, 'reason' => 'plan_closed'];
        }

        $areasQuery = $plan->areas()->where('usageType', 'service');

        if ($businessCategoryId !== null) {
            $areasQuery->where('categoryID', $businessCategoryId);
        }

        $areaIds = $areasQuery->pluck('id')->shuffle();

        if ($areaIds->isEmpty()) {
            return ['areaId' => null, 'reason' => $businessCategoryId !== null ? 'none_for_category' : 'none'];
        }

        foreach ($areaIds as $id) {
            $area = Area::query()->whereKey($id)->lockForUpdate()->first();
            if (! $area) {
                continue;
            }
            $used = Service::query()->where('areaID', $area->id)->count();
            if ($used < $area->maxCapacity) {
                return ['areaId' => $area->id];
            }
        }

        return ['areaId' => null, 'reason' => $businessCategoryId !== null ? 'full_for_category' : 'full'];
    }
}
