<?php

namespace App\DAO;

use App\DAO\SubscriptionPlanInterface;
use App\Models\Floor;
use App\Models\Area;
use App\Models\StoreSubscriptionPlan;
use App\Models\ServiceSubscriptionPlan;
use App\Models\StorePlanPrice;
use App\Models\ServicePlanPrice;
use App\Models\StoreSubscription;
use App\Models\ServiceSubscription;
use App\Models\Store;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanClass implements SubscriptionPlanInterface
{

    public function createStorePlan($data)
    {
        DB::beginTransaction();

        try {
            $plan = StoreSubscriptionPlan::create([
                'name' => $data['name'],
                'floorID' => $data['floorID'],
                'storeSpace' => $data['storeSpace'],
                'adsNumber' => $data['adsNumber'],
                'adsDuration' => $data['adsDuration'],
                'adsPlacement' => $data['adsPlacement'],
            ]);


            if (!empty($data['area'])) {

                $alreadyAssigned = Area::whereIn('id', $data['area'])
                        ->whereNotNull('planable_id')
                        ->whereNotNull('planable_type')
                        ->exists();

                if ($alreadyAssigned) {
                    throw new \Exception(__('app.area_already_has_a_plan'));
                }

                Area::whereIn('id', $data['area'])
                    ->update([
                        'planable_id' => $plan->id,
                        'planable_type' => StoreSubscriptionPlan::class,
                    ]);
            }



            foreach ($data['prices'] as $price) {
                    StorePlanPrice::create([
                    'storeSubscriptionPlanID' => $plan->id,
                    'duration' => $price['duration'],
                    'price' => $price['price'],
                ]);
            }

            DB::commit();

            return [
                'message' => __('app.store_plan_created'),
                'plan' => $plan->load('prices', 'areas'),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }



    public function createServicePlan($data)
    {
        DB::beginTransaction();

        try {
            $plan = ServiceSubscriptionPlan::create([
                'name' => $data['name'],
                'floorID' => $data['floorID'],
                'serviceSpace' => $data['serviceSpace'],
                'adsNumber' => $data['adsNumber'],
                'adsDuration' => $data['adsDuration'],
                'adsPlacement' => $data['adsPlacement'],
            ]);


            if (!empty($data['area'])) {

                $alreadyAssigned = Area::whereIn('id', $data['area'])
                        ->whereNotNull('planable_id')
                        ->whereNotNull('planable_type')
                        ->exists();

                if ($alreadyAssigned) {
                    throw new \Exception(__('app.area_already_has_a_plan'));
                }

                Area::whereIn('id', $data['area'])
                    ->update([
                        'planable_id' => $plan->id,
                        'planable_type' => ServiceSubscriptionPlan::class,
                    ]);
            }



            foreach ($data['prices'] as $price) {
                    ServicePlanPrice::create([
                    'serviceSubscriptionPlanID' => $plan->id,
                    'duration' => $price['duration'],
                    'price' => $price['price'],
                ]);
            }

            DB::commit();

            return [
                'message' => __('app.service_plan_created'),
                'plan' => $plan->load('prices', 'areas'),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

    }

    public function getStorePlansForAdmin()
    {
        return $this->storeSubscriptionPlansQuery(onlyAcceptingSubscriptions: false)->get();
    }

    public function getStorePlansForSubscription()
    {
        return $this->storeSubscriptionPlansQuery(onlyAcceptingSubscriptions: true)->get();
    }

    public function getServicePlansForAdmin()
    {
        return $this->serviceSubscriptionPlansQuery(onlyAcceptingSubscriptions: false)->get();
    }

    public function getServicePlansForSubscription()
    {
        return $this->serviceSubscriptionPlansQuery(onlyAcceptingSubscriptions: true)->get();
    }

    /**
     * @return Builder<StoreSubscriptionPlan>
     */
    private function storeSubscriptionPlansQuery(bool $onlyAcceptingSubscriptions): Builder
    {
        $q = StoreSubscriptionPlan::query()->with(['prices', 'areas', 'floor']);
        if ($onlyAcceptingSubscriptions) {
            $q->where('accepting_subscriptions', true);
        }

        return $q;
    }

    /**
     * @return Builder<ServiceSubscriptionPlan>
     */
    private function serviceSubscriptionPlansQuery(bool $onlyAcceptingSubscriptions): Builder
    {
        $q = ServiceSubscriptionPlan::query()->with(['prices', 'areas', 'floor']);
        if ($onlyAcceptingSubscriptions) {
            $q->where('accepting_subscriptions', true);
        }

        return $q;
    }

    public function getStoresInPlan($planId)
    {
        return StoreSubscription::query()
            ->where('storeSubscriptionPlanID', $planId)
            ->join('stores', 'stores.id', '=', 'store_subscriptions.storeID')
            ->select([
                'store_subscriptions.storeID',
                'stores.name',
            ])
            ->get()
            ->map(fn ($row) => [
                'storeID' => $row->storeID,
                'name' => $row->name,
            ])
            ->values();
    }

    public function getServicesInPlan($planId)
    {
        return ServiceSubscription::query()
            ->where('serviceSubscriptionPlanID', $planId)
            ->join('services', 'services.id', '=', 'service_subscriptions.serviceID')
            ->select([
                'service_subscriptions.serviceID',
                'services.name',
            ])
            ->get()
            ->map(fn ($row) => [
                'serviceID' => $row->serviceID,
                'name' => $row->name,
            ])
            ->values();

    }

    public function getStoreDetails($storeId)
    {
        return Store::where('id', $storeId)->with(['subscriptions.planPrice', 'area.floor'])
        ->get()
        ->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'status' => $row->status,
            'accountStatus' => $row->accountStatus,
            'paymentAccount' => $row->paymentAccount,
            'areaID' => $row->areaID,
            'areaName' => $row->area->name,
            'floorID' => $row->area->floorID,
            'floorName' => $row->area->floor->name,
            'subscription' => $row->subscriptions->map(fn ($sub) => [
                'id' => $sub->id,
                'duration' => $sub->planPrice->duration,
                'price' => $sub->planPrice->price,
            ])->values()->all(),
        ])->values();
    }

    public function getServiceDetails($serviceId)
    {
        return Service::where('id', $serviceId)->with(['subscriptions.servicePlanPrice', 'area.floor'])
        ->get()
        ->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'status' => $row->status,
            'accountStatus' => $row->accountStatus,
            'paymentAccount' => $row->paymentAccount,
            'areaID' => $row->areaID,
            'areaName' => $row->area->name,
            'floorID' => $row->area->floorID,
            'floorName' => $row->area->floor->name,
            'subscription' => $row->subscriptions->map(fn ($sub) => [
                'id' => $sub->id,
                'duration' => $sub->servicePlanPrice->duration,
                'price' => $sub->servicePlanPrice->price,
            ])->values()->all(),
        ])->values();
    }

    public function stopStorePlan(int $planId): array
    {
        return DB::transaction(function () use ($planId) {
            $plan = StoreSubscriptionPlan::query()->lockForUpdate()->find($planId);

            if (! $plan) {
                return ['success' => false, 'message' => __('app.store_plan_not_found')];
            }

            if (! $plan->accepting_subscriptions) {
                return ['success' => false, 'message' => __('app.store_plan_already_stopped')];
            }

            $plan->accepting_subscriptions = false;
            $plan->save();

            return [
                'success' => true,
                'message' => __('app.store_plan_stopped'),
                'plan' => $plan->fresh(),
            ];
        });
    }

    public function stopServicePlan(int $planId): array
    {
        return DB::transaction(function () use ($planId) {
            $plan = ServiceSubscriptionPlan::query()->lockForUpdate()->find($planId);

            if (! $plan) {
                return ['success' => false, 'message' => __('app.service_plan_not_found')];
            }

            if (! $plan->accepting_subscriptions) {
                return ['success' => false, 'message' => __('app.service_plan_already_stopped')];
            }

            $plan->accepting_subscriptions = false;
            $plan->save();

            return [
                'success' => true,
                'message' => __('app.service_plan_stopped'),
                'plan' => $plan->fresh(),
            ];
        });
    }

    public function rerunStorePlan(int $planId): array
    {
        return DB::transaction(function () use ($planId) {
            $plan = StoreSubscriptionPlan::query()->lockForUpdate()->find($planId);

            if (! $plan) {
                return ['success' => false, 'message' => __('app.store_plan_not_found')];
            }

            if ($plan->accepting_subscriptions) {
                return ['success' => false, 'message' => __('app.store_plan_already_open')];
            }

            $plan->accepting_subscriptions = true;
            $plan->save();

            return [
                'success' => true,
                'message' => __('app.store_plan_rerun'),
                'plan' => $plan->fresh(),
            ];
        });
    }

    public function rerunServicePlan(int $planId): array
    {
        return DB::transaction(function () use ($planId) {
            $plan = ServiceSubscriptionPlan::query()->lockForUpdate()->find($planId);

            if (! $plan) {
                return ['success' => false, 'message' => __('app.service_plan_not_found')];
            }

            if ($plan->accepting_subscriptions) {
                return ['success' => false, 'message' => __('app.service_plan_already_open')];
            }

            $plan->accepting_subscriptions = true;
            $plan->save();

            return [
                'success' => true,
                'message' => __('app.service_plan_rerun'),
                'plan' => $plan->fresh(),
            ];
        });
    }
}
