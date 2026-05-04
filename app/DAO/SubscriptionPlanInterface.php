<?php

namespace App\DAO;

interface SubscriptionPlanInterface
{
    public function createStorePlan($data);

    public function createServicePlan($data);

    public function getStorePlansForAdmin();

    public function getStorePlansForSubscription();

    public function getServicePlansForAdmin();

    public function getServicePlansForSubscription();

    public function getStoresInPlan($planId);

    public function getServicesInPlan($planId);

    public function getStoreDetails($storeId);

    public function getServiceDetails($serviceId);

    public function stopStorePlan(int $planId): array;

    public function stopServicePlan(int $planId): array;

    public function rerunStorePlan(int $planId): array;

    public function rerunServicePlan(int $planId): array;

}
