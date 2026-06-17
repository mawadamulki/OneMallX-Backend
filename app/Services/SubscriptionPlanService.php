<?php

namespace App\Services;

use App\DAO\SubscriptionPlanInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubscriptionPlanService
{
    protected $subscriptionPlanInterface;

    public function __construct(SubscriptionPlanInterface $subscriptionPlanInterface)
    {
        $this->subscriptionPlanInterface = $subscriptionPlanInterface;
    }


    public function createStorePlan($request)
    {
        $floorId = is_array($request) ? ($request['floorID'] ?? null) : null;

        $areaMustBeStoreOnFloor = Rule::exists('areas', 'id')
            ->where('usageType', 'store')
            ->whereNull('deleted_at');

        if ($floorId !== null && $floorId !== '') {
            $areaMustBeStoreOnFloor->where('floorID', (int) $floorId);
        }

        $validator = Validator::make($request, [
            'name' => 'required|string|max:255',

            'floorID' => 'required|integer|exists:floors,id',
            'area' => 'required|array|min:1',
            'area.*' => ['integer', $areaMustBeStoreOnFloor],

            'storeSpace' => 'required|integer|min:0',
            'adsNumber' => 'required|integer|min:0',
            'adsDuration' => 'required|integer|min:1',
            'adsPlacement' => 'required|string|in:home,deals',

            'prices' => 'required|array|min:1',
            'prices.*.duration' => 'required|integer|min:1',
            'prices.*.price' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => $validator->errors(),
            ];
        }

        $plan = $this->subscriptionPlanInterface->createStorePlan($validator->validated());

        return [
            'message' => __('app.store_plan_created'),
            'plan' => $plan,
        ];
    }

    public function createServicePlan($request)
    {
        $floorId = is_array($request) ? ($request['floorID'] ?? null) : null;

        $areaMustBeServiceOnFloor = Rule::exists('areas', 'id')
            ->where('usageType', 'service')
            ->whereNull('deleted_at');

        if ($floorId !== null && $floorId !== '') {
            $areaMustBeServiceOnFloor->where('floorID', (int) $floorId);
        }

        $validator = Validator::make($request, [
            'name' => 'required|string|max:255',

            'floorID' => 'required|integer|exists:floors,id',
            'area' => 'required|array|min:1',
            'area.*' => ['integer', $areaMustBeServiceOnFloor],

            'serviceSpace' => 'required|integer|min:0',
            'adsNumber' => 'required|integer|min:0',
            'adsDuration' => 'required|integer|min:1',
            'adsPlacement' => 'required|string|in:home,deals',

            'prices' => 'required|array|min:1',
            'prices.*.duration' => 'required|integer|min:1',
            'prices.*.price' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => $validator->errors(),
            ];
        }


        $plan = $this->subscriptionPlanInterface->createServicePlan($validator->validated());

        return [
            'message' => __('app.service_plan_created'),
            'plan' => $plan,
        ];
    }

    public function getStorePlansForAdmin()
    {
        $plans = $this->subscriptionPlanInterface->getStorePlansForAdmin();

        return ['plans' => $plans];
    }

    public function getStorePlansForSubscription()
    {
        $plans = $this->subscriptionPlanInterface->getStorePlansForSubscription();

        return ['plans' => $plans];
    }

    public function getServicePlansForAdmin()
    {
        $plans = $this->subscriptionPlanInterface->getServicePlansForAdmin();

        return ['plans' => $plans];
    }

    public function getServicePlansForSubscription()
    {
        $plans = $this->subscriptionPlanInterface->getServicePlansForSubscription();

        return ['plans' => $plans];
    }

    public function getStoresInPlan($planId)
    {
        $stores = $this->subscriptionPlanInterface->getStoresInPlan($planId);
        return ['stores' => $stores];
    }

    public function getServicesInPlan($planId)
    {
        $services = $this->subscriptionPlanInterface->getServicesInPlan($planId);
        return ['services' => $services];
    }

    public function getStoreDetails($storeId)
    {
        $store = $this->subscriptionPlanInterface->getStoreDetails($storeId);
        return ['store' => $store];
    }

    public function getServiceDetails($serviceId)
    {
        $service = $this->subscriptionPlanInterface->getServiceDetails($serviceId);
        return ['service' => $service];
    }

    public function stopStorePlan(int $planId)
    {
        $validator = Validator::make(
            ['planId' => $planId],
            ['planId' => 'required|integer|min:1']
        );

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => __('app.validation_failed'),
                'errors' => $validator->errors(),
            ];
        }

        return $this->subscriptionPlanInterface->stopStorePlan($planId);
    }

    public function stopServicePlan(int $planId)
    {
        $validator = Validator::make(
            ['planId' => $planId],
            ['planId' => 'required|integer|min:1']
        );

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => __('app.validation_failed'),
                'errors' => $validator->errors(),
            ];
        }

        return $this->subscriptionPlanInterface->stopServicePlan($planId);
    }

    public function rerunStorePlan(int $planId)
    {
        $validator = Validator::make(
            ['planId' => $planId],
            ['planId' => 'required|integer|min:1']
        );

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => __('app.validation_failed'),
                'errors' => $validator->errors(),
            ];
        }

        return $this->subscriptionPlanInterface->rerunStorePlan($planId);
    }

    public function rerunServicePlan(int $planId)
    {
        $validator = Validator::make(
            ['planId' => $planId],
            ['planId' => 'required|integer|min:1']
        );

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => __('app.validation_failed'),
                'errors' => $validator->errors(),
            ];
        }

        return $this->subscriptionPlanInterface->rerunServicePlan($planId);
    }
}
