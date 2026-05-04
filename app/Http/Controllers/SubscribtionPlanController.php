<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionPlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscribtionPlanController extends Controller
{

    public function __construct(
        private SubscriptionPlanService $subscriptionPlanService
    ) {}



    public function createStorePlan(Request $request)
    {
        $response = $this->subscriptionPlanService->createStorePlan($request->all());

        return response()->json($response);

    }


    public function createServicePlan(Request $request)
    {
        $response = $this->subscriptionPlanService->createServicePlan($request->all());

        return response()->json($response);
    }

    public function getStorePlansForSubscription()
    {
        $response = $this->subscriptionPlanService->getStorePlansForSubscription();

        return response()->json($response);
    }

    public function getServicePlansForSubscription()
    {
        $response = $this->subscriptionPlanService->getServicePlansForSubscription();

        return response()->json($response);
    }

    public function getStorePlansForAdmin()
    {
        $response = $this->subscriptionPlanService->getStorePlansForAdmin();

        return response()->json($response);
    }

    public function getServicePlansForAdmin()
    {
        $response = $this->subscriptionPlanService->getServicePlansForAdmin();

        return response()->json($response);
    }

    public function getStoresInPlan($planId)
    {
        $response = $this->subscriptionPlanService->getStoresInPlan($planId);
        return response()->json($response);
    }

    public function getServicesInPlan($planId)
    {
        $response = $this->subscriptionPlanService->getServicesInPlan($planId);
        return response()->json($response);
    }

    public function getStoreDetails($storeId)
    {
        $response = $this->subscriptionPlanService->getStoreDetails($storeId);
        return response()->json($response);
    }

    public function getServiceDetails($serviceId)
    {
        $response = $this->subscriptionPlanService->getServiceDetails($serviceId);
        return response()->json($response);
    }

    public function stopStorePlan(int $planId)
    {
        $response = $this->subscriptionPlanService->stopStorePlan($planId);

        return response()->json($response);
    }

    public function stopServicePlan(int $planId)
    {
        $response = $this->subscriptionPlanService->stopServicePlan($planId);

        return response()->json($response);
    }

    public function rerunStorePlan(int $planId)
    {
        $response = $this->subscriptionPlanService->rerunStorePlan($planId);

        return response()->json($response);
    }

    public function rerunServicePlan(int $planId)
    {
        $response = $this->subscriptionPlanService->rerunServicePlan($planId);

        return response()->json($response);
    }

}
