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

        return StoreSubscriptionRequest::create($payload);
    }

    public function createServiceRequest(array $data): ServiceSubscriptionRequest
    {
        $payload = $data;
        $payload['password'] = Hash::make($payload['password']);
        $payload['status'] = 'pending';

        return ServiceSubscriptionRequest::create($payload);
    }

    public function listStoreRequests(?string $status): Collection
    {
        $q = StoreSubscriptionRequest::query()->orderBy('created_at');
        if ($status !== null && $status !== '') {
            $q->where('status', $status);
        }

        return $q->get();
    }

    public function listServiceRequests(?string $status): Collection
    {
        $q = ServiceSubscriptionRequest::query()->orderBy('created_at');
        if ($status !== null && $status !== '') {
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

            $pick = $this->pickRandomAvailableStoreAreaId((int) $locked->storeSubscriptionPlanID);
            if ($pick['areaId'] === null) {
                $message = match ($pick['reason']) {
                    'plan_missing' => __('app.store_plan_not_found'),
                    'plan_closed' => __('app.store_plan_not_accepting_subscriptions'),
                    'none' => __('app.no_areas_assigned_to_store_plan'),
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
                'status' => 'active',
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
                'description' => $locked->description,
                'status' => $locked->storeStatus,
                'accountStatus' => 'active',
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

            $pick = $this->pickRandomAvailableServiceAreaId((int) $locked->serviceSubscriptionPlanID);
            if ($pick['areaId'] === null) {
                $message = match ($pick['reason']) {
                    'plan_missing' => __('app.service_plan_not_found'),
                    'plan_closed' => __('app.service_plan_not_accepting_subscriptions'),
                    'none' => __('app.no_areas_assigned_to_service_plan'),
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
                'status' => 'active',
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
                'description' => $locked->description,
                'paymentAccount' => $locked->paymentAccount,
                'openTime' => $locked->openTime,
                'closeTime' => $locked->closeTime,
                'duration' => $locked->duration,
                'locationID' => $locked->locationID,
                'status' => $locked->serviceStatus ?? 'pending',
                'accountStatus' => 'active',
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

    /**
     * @return array{areaId: int|null, reason?: 'plan_missing'|'plan_closed'|'none'|'full'}
     */
    private function pickRandomAvailableStoreAreaId(int $storeSubscriptionPlanId): array
    {
        $plan = StoreSubscriptionPlan::query()->find($storeSubscriptionPlanId);
        if (! $plan) {
            return ['areaId' => null, 'reason' => 'plan_missing'];
        }

        if (! $plan->accepting_subscriptions) {
            return ['areaId' => null, 'reason' => 'plan_closed'];
        }

        $areaIds = $plan->areas()
            ->where('usageType', 'store')
            ->pluck('id')
            ->shuffle();

        if ($areaIds->isEmpty()) {
            return ['areaId' => null, 'reason' => 'none'];
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

        return ['areaId' => null, 'reason' => 'full'];
    }

    /**
     * @return array{areaId: int|null, reason?: 'plan_missing'|'plan_closed'|'none'|'full'}
     */
    private function pickRandomAvailableServiceAreaId(int $serviceSubscriptionPlanId): array
    {
        $plan = ServiceSubscriptionPlan::query()->find($serviceSubscriptionPlanId);
        if (! $plan) {
            return ['areaId' => null, 'reason' => 'plan_missing'];
        }

        if (! $plan->accepting_subscriptions) {
            return ['areaId' => null, 'reason' => 'plan_closed'];
        }

        $areaIds = $plan->areas()
            ->where('usageType', 'service')
            ->pluck('id')
            ->shuffle();

        if ($areaIds->isEmpty()) {
            return ['areaId' => null, 'reason' => 'none'];
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

        return ['areaId' => null, 'reason' => 'full'];
    }
}
