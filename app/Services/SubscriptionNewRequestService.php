<?php

namespace App\Services;

use App\DAO\SubscriptionNewRequestInterface;
use App\Models\ServicePlanPrice;
use App\Models\ServiceSubscription;
use App\Models\ServiceSubscriptionExtensionRequest;
use App\Models\ServiceSubscriptionNewRequest;
use App\Models\ServiceSubscriptionPlan;
use App\Models\StorePlanPrice;
use App\Models\StoreSubscription;
use App\Models\StoreSubscriptionExtensionRequest;
use App\Models\StoreSubscriptionNewRequest;
use App\Models\StoreSubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SubscriptionNewRequestService
{
    private const NEW_REQUEST_MAX_DAYS_BEFORE_END = 10;

    public function __construct(
        protected SubscriptionNewRequestInterface $subscriptionNewRequestDao
    ) {}

    public function submitStoreNewRequestFromOwner(int $userId, array $input): array
    {
        $validator = Validator::make($input, [
            'requestedStoreSubscriptionPlanID' => 'required|integer|exists:store_subscription_plans,id',
            'requestedPlanPriceID' => 'required|integer|exists:store_plan_prices,id',
            'applicantNote' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $subscription = $this->getCurrentStoreSubscriptionForOwner($userId);
        if (! $subscription) {
            return [
                'success' => false,
                'message' => __('app.store_owner_no_subscription_for_extension'),
                'http_status' => 404,
            ];
        }

        if (! $this->storePlanPriceMatches((int) $data['requestedStoreSubscriptionPlanID'], (int) $data['requestedPlanPriceID'])) {
            return [
                'success' => false,
                'message' => __('app.validation_failed'),
                'errors' => ['requestedPlanPriceID' => [__('app.plan_price_mismatch')]],
                'http_status' => 422,
            ];
        }

        $requestedPlan = StoreSubscriptionPlan::query()->find($data['requestedStoreSubscriptionPlanID']);
        if (! $requestedPlan || ! $requestedPlan->accepting_subscriptions) {
            return [
                'success' => false,
                'message' => __('app.validation_failed'),
                'errors' => ['requestedStoreSubscriptionPlanID' => [__('app.store_plan_not_accepting_subscriptions')]],
                'http_status' => 422,
            ];
        }

        if ((int) $subscription->storeSubscriptionPlanID === (int) $data['requestedStoreSubscriptionPlanID']) {
            return [
                'success' => false,
                'message' => __('app.store_new_plan_same_as_current'),
                'http_status' => 409,
            ];
        }

        if (! $this->subscriptionEndDateAllowsNewRequest($subscription->endDate)) {
            return [
                'success' => false,
                'message' => __('app.store_new_plan_window_closed', ['days' => self::NEW_REQUEST_MAX_DAYS_BEFORE_END]),
                'http_status' => 422,
            ];
        }

        if (StoreSubscriptionExtensionRequest::query()
            ->where('storeSubscriptionID', $subscription->id)
            ->where('status', 'pending')
            ->exists()) {
            return [
                'success' => false,
                'message' => __('app.store_extension_pending_request_exists'),
                'http_status' => 409,
            ];
        }

        if (StoreSubscriptionNewRequest::query()
            ->where('storeSubscriptionID', $subscription->id)
            ->where('status', 'pending')
            ->exists()) {
            return [
                'success' => false,
                'message' => __('app.store_new_plan_pending_exists'),
                'http_status' => 409,
            ];
        }

        $request = $this->subscriptionNewRequestDao->submitStoreNewRequest([
            'storeSubscriptionID' => $subscription->id,
            'requestedStoreSubscriptionPlanID' => $data['requestedStoreSubscriptionPlanID'],
            'requestedPlanPriceID' => $data['requestedPlanPriceID'],
            'applicantNote' => $data['applicantNote'] ?? null,
            'requestedByUserID' => $userId,
            'status' => 'pending',
        ]);

        return [
            'success' => true,
            'message' => __('app.store_new_plan_request_submitted'),
            'request' => $request,
            'http_status' => 201,
        ];
    }

    public function submitServiceNewRequestFromProvider(int $userId, array $input): array
    {
        $validator = Validator::make($input, [
            'requestedServiceSubscriptionPlanID' => 'required|integer|exists:service_subscription_plans,id',
            'requestedPlanPriceID' => 'required|integer|exists:service_plan_prices,id',
            'applicantNote' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $subscription = $this->getCurrentServiceSubscriptionForProvider($userId);
        if (! $subscription) {
            return [
                'success' => false,
                'message' => __('app.service_provider_no_subscription_for_extension'),
                'http_status' => 404,
            ];
        }

        if (! $this->servicePlanPriceMatches((int) $data['requestedServiceSubscriptionPlanID'], (int) $data['requestedPlanPriceID'])) {
            return [
                'success' => false,
                'message' => __('app.validation_failed'),
                'errors' => ['requestedPlanPriceID' => [__('app.plan_price_mismatch')]],
                'http_status' => 422,
            ];
        }

        $requestedPlan = ServiceSubscriptionPlan::query()->find($data['requestedServiceSubscriptionPlanID']);
        if (! $requestedPlan || ! $requestedPlan->accepting_subscriptions) {
            return [
                'success' => false,
                'message' => __('app.validation_failed'),
                'errors' => ['requestedServiceSubscriptionPlanID' => [__('app.service_plan_not_accepting_subscriptions')]],
                'http_status' => 422,
            ];
        }

        if ((int) $subscription->serviceSubscriptionPlanID === (int) $data['requestedServiceSubscriptionPlanID']) {
            return [
                'success' => false,
                'message' => __('app.service_new_plan_same_as_current'),
                'http_status' => 409,
            ];
        }

        if (! $this->subscriptionEndDateAllowsNewRequest($subscription->endDate)) {
            return [
                'success' => false,
                'message' => __('app.service_new_plan_window_closed', ['days' => self::NEW_REQUEST_MAX_DAYS_BEFORE_END]),
                'http_status' => 422,
            ];
        }

        if (ServiceSubscriptionExtensionRequest::query()
            ->where('serviceSubscriptionID', $subscription->id)
            ->where('status', 'pending')
            ->exists()) {
            return [
                'success' => false,
                'message' => __('app.service_extension_pending_request_exists'),
                'http_status' => 409,
            ];
        }

        if (ServiceSubscriptionNewRequest::query()
            ->where('serviceSubscriptionID', $subscription->id)
            ->where('status', 'pending')
            ->exists()) {
            return [
                'success' => false,
                'message' => __('app.service_new_plan_pending_exists'),
                'http_status' => 409,
            ];
        }

        $request = $this->subscriptionNewRequestDao->submitServiceNewRequest([
            'serviceSubscriptionID' => $subscription->id,
            'requestedServiceSubscriptionPlanID' => $data['requestedServiceSubscriptionPlanID'],
            'requestedPlanPriceID' => $data['requestedPlanPriceID'],
            'applicantNote' => $data['applicantNote'] ?? null,
            'requestedByUserID' => $userId,
            'status' => 'pending',
        ]);

        return [
            'success' => true,
            'message' => __('app.service_new_plan_request_submitted'),
            'request' => $request,
            'http_status' => 201,
        ];
    }

    public function listStoreNewRequests(?string $status = null): array
    {
        return [
            'requests' => $this->subscriptionNewRequestDao->listStoreNewRequests($status),
        ];
    }

    public function listServiceNewRequests(?string $status = null): array
    {
        return [
            'requests' => $this->subscriptionNewRequestDao->listServiceNewRequests($status),
        ];
    }

    public function approveStoreNewRequest(int $id): array
    {
        $request = StoreSubscriptionNewRequest::find($id);
        if (! $request) {
            return [
                'message' => __('app.subscription_new_request_not_found'),
                'http_status' => 404,
            ];
        }

        $result = $this->subscriptionNewRequestDao->approveStoreNewRequest($request, (int) Auth::id());
        if (! $result['success']) {
            return [
                'message' => $result['message'] ?? __('app.validation_failed'),
                'http_status' => 409,
            ];
        }

        return [
            'message' => $result['message'] ?? __('app.store_new_plan_request_approved'),
            'subscription' => $result['subscription'] ?? null,
            'request' => $result['request'] ?? null,
            'http_status' => 200,
        ];
    }

    public function approveServiceNewRequest(int $id): array
    {
        $request = ServiceSubscriptionNewRequest::find($id);
        if (! $request) {
            return [
                'message' => __('app.subscription_new_request_not_found'),
                'http_status' => 404,
            ];
        }

        $result = $this->subscriptionNewRequestDao->approveServiceNewRequest($request, (int) Auth::id());
        if (! $result['success']) {
            return [
                'message' => $result['message'] ?? __('app.validation_failed'),
                'http_status' => 409,
            ];
        }

        return [
            'message' => $result['message'] ?? __('app.service_new_plan_request_approved'),
            'subscription' => $result['subscription'] ?? null,
            'request' => $result['request'] ?? null,
            'http_status' => 200,
        ];
    }

    public function rejectStoreNewRequest(int $id, ?string $reason = null): array
    {
        $validator = Validator::make(['rejectionReason' => $reason], [
            'rejectionReason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $request = StoreSubscriptionNewRequest::find($id);
        if (! $request) {
            return [
                'message' => __('app.subscription_new_request_not_found'),
                'http_status' => 404,
            ];
        }

        $result = $this->subscriptionNewRequestDao->rejectStoreNewRequest(
            $request,
            (int) Auth::id(),
            $validator->validated()['rejectionReason'] ?? null
        );
        if (! $result['success']) {
            return [
                'message' => $result['message'] ?? __('app.validation_failed'),
                'http_status' => 409,
            ];
        }

        return [
            'message' => $result['message'] ?? __('app.store_new_plan_request_rejected'),
            'request' => $result['request'] ?? null,
            'http_status' => 200,
        ];
    }

    public function rejectServiceNewRequest(int $id, ?string $reason = null): array
    {
        $validator = Validator::make(['rejectionReason' => $reason], [
            'rejectionReason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $request = ServiceSubscriptionNewRequest::find($id);
        if (! $request) {
            return [
                'message' => __('app.subscription_new_request_not_found'),
                'http_status' => 404,
            ];
        }

        $result = $this->subscriptionNewRequestDao->rejectServiceNewRequest(
            $request,
            (int) Auth::id(),
            $validator->validated()['rejectionReason'] ?? null
        );
        if (! $result['success']) {
            return [
                'message' => $result['message'] ?? __('app.validation_failed'),
                'http_status' => 409,
            ];
        }

        return [
            'message' => $result['message'] ?? __('app.service_new_plan_request_rejected'),
            'request' => $result['request'] ?? null,
            'http_status' => 200,
        ];
    }

    private function getCurrentStoreSubscriptionForOwner(int $userId): ?StoreSubscription
    {
        return StoreSubscription::query()
            ->whereHas('store', fn ($q) => $q->where('storeOwnerID', $userId))
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();
    }

    private function getCurrentServiceSubscriptionForProvider(int $userId): ?ServiceSubscription
    {
        return ServiceSubscription::query()
            ->whereHas('service', fn ($q) => $q->where('serviceOwnerID', $userId))
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();
    }

    private function storePlanPriceMatches(int $planId, int $planPriceId): bool
    {
        return StorePlanPrice::query()
            ->whereKey($planPriceId)
            ->where('storeSubscriptionPlanID', $planId)
            ->exists();
    }

    private function servicePlanPriceMatches(int $planId, int $planPriceId): bool
    {
        return ServicePlanPrice::query()
            ->whereKey($planPriceId)
            ->where('serviceSubscriptionPlanID', $planId)
            ->exists();
    }

    /**
     * Allowed when end date is on or before (today + N days) in app timezone, or already ended.
     */
    private function subscriptionEndDateAllowsNewRequest(mixed $endDate): bool
    {
        if ($endDate === null || $endDate === '') {
            return false;
        }

        $tz = (string) config('app.timezone', 'UTC');
        $end = $endDate instanceof Carbon
            ? $endDate->copy()->timezone($tz)->startOfDay()
            : Carbon::parse($endDate, $tz)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();
        $cutoff = $today->copy()->addDays(self::NEW_REQUEST_MAX_DAYS_BEFORE_END);

        return $end->lte($cutoff);
    }

    private function validationError(\Illuminate\Validation\Validator $validator): array
    {
        return [
            'success' => false,
            'message' => __('app.validation_failed'),
            'errors' => $validator->errors(),
            'http_status' => 422,
        ];
    }
}