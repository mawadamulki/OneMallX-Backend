<?php

namespace App\Services;

use App\DAO\SubscriptionExtensionInterface;
use App\Models\ServiceSubscription;
use App\Models\ServiceSubscriptionExtensionRequest;
use App\Models\ServiceSubscriptionNewRequest;
use App\Models\StoreSubscription;
use App\Models\StoreSubscriptionExtensionRequest;
use App\Models\StoreSubscriptionNewRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SubscriptionExtensionService
{
    /** Owners may request an extension only in the last N calendar days before endDate (or after expiry). */
    private const EXTENSION_REQUEST_MAX_DAYS_BEFORE_END = 10;

    public function __construct(
        protected SubscriptionExtensionInterface $subscriptionExtensionDao
    ) {}

    /**
     * Store owner submits a pending extension request for their current store subscription
     * (same plan; admin approval only extends endDate). Subscription is resolved from the user — no body.
     *
     * @return array{success: bool, message?: string, request?: StoreSubscriptionExtensionRequest, http_status?: int}
     */
    public function submitStoreExtensionRequestFromOwner(int $userId): array
    {
        $subscription = StoreSubscription::query()
            ->with('store')
            ->whereHas('store', fn ($q) => $q->where('storeOwnerID', $userId))
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();

        if (! $subscription) {
            return [
                'success' => false,
                'message' => __('app.store_owner_no_subscription_for_extension'),
                'http_status' => 404,
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

        if (! $this->subscriptionEndDateAllowsExtensionRequest($subscription->endDate)) {
            return [
                'success' => false,
                'message' => __('app.store_extension_too_early', ['days' => self::EXTENSION_REQUEST_MAX_DAYS_BEFORE_END]),
                'http_status' => 422,
            ];
        }

        $request = $this->subscriptionExtensionDao->insertStoreSubscriptionExtensionRequest([
            'storeSubscriptionID' => $subscription->id,
            'applicantNote' => null,
            'requestedByUserID' => $userId,
            'status' => 'pending',
        ]);

        return [
            'success' => true,
            'message' => __('app.store_extension_request_submitted'),
            'request' => $request,
            'http_status' => 201,
        ];
    }

    /**
     * Service provider submits a pending extension request for their current service subscription
     * (same plan; admin approval only extends endDate). Subscription is resolved from the user — no body.
     *
     * @return array{success: bool, message?: string, request?: ServiceSubscriptionExtensionRequest, http_status?: int}
     */
    public function submitServiceExtensionRequestFromProvider(int $userId): array
    {
        $subscription = ServiceSubscription::query()
            ->with('service')
            ->whereHas('service', fn ($q) => $q->where('serviceOwnerID', $userId))
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();

        if (! $subscription) {
            return [
                'success' => false,
                'message' => __('app.service_provider_no_subscription_for_extension'),
                'http_status' => 404,
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

        if (! $this->subscriptionEndDateAllowsExtensionRequest($subscription->endDate)) {
            return [
                'success' => false,
                'message' => __('app.service_extension_too_early', ['days' => self::EXTENSION_REQUEST_MAX_DAYS_BEFORE_END]),
                'http_status' => 422,
            ];
        }

        $request = $this->subscriptionExtensionDao->insertServiceSubscriptionExtensionRequest([
            'serviceSubscriptionID' => $subscription->id,
            'applicantNote' => null,
            'requestedByUserID' => $userId,
            'status' => 'pending',
        ]);

        return [
            'success' => true,
            'message' => __('app.service_extension_request_submitted'),
            'request' => $request,
            'http_status' => 201,
        ];
    }

    public function listStoreExtensionRequests(?string $status = null): array
    {
        $requests = $this->subscriptionExtensionDao->listStoreExtensionRequests($status);

        return ['requests' => $requests];
    }

    public function listServiceExtensionRequests(?string $status = null): array
    {
        $requests = $this->subscriptionExtensionDao->listServiceExtensionRequests($status);

        return ['requests' => $requests];
    }

    public function approveStoreExtensionRequest(int $id): array
    {
        $request = StoreSubscriptionExtensionRequest::find($id);
        if (! $request) {
            return ['message' => __('app.subscription_extension_request_not_found')];
        }

        $result = $this->subscriptionExtensionDao->approveStoreExtensionRequest($request, (int) Auth::id());
        if (! $result['success']) {
            return ['message' => $result['message']];
        }

        return [
            'message' => $result['message'],
            'subscription' => $result['subscription'],
            'request' => $result['request'],
        ];
    }

    public function approveServiceExtensionRequest(int $id): array
    {
        $request = ServiceSubscriptionExtensionRequest::find($id);
        if (! $request) {
            return ['message' => __('app.subscription_extension_request_not_found')];
        }

        $result = $this->subscriptionExtensionDao->approveServiceExtensionRequest($request, (int) Auth::id());
        if (! $result['success']) {
            return ['message' => $result['message']];
        }

        return [
            'message' => $result['message'],
            'subscription' => $result['subscription'],
            'request' => $result['request'],
        ];
    }

    public function rejectStoreExtensionRequest(int $id, ?string $reason = null): array
    {
        $validator = Validator::make(['rejectionReason' => $reason], [
            'rejectionReason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => $validator->errors(),
            ];
        }

        $request = StoreSubscriptionExtensionRequest::find($id);
        if (! $request) {
            return ['message' => __('app.subscription_extension_request_not_found')];
        }

        $result = $this->subscriptionExtensionDao->rejectStoreExtensionRequest(
            $request,
            (int) Auth::id(),
            $validator->validated()['rejectionReason'] ?? null
        );
        if (! $result['success']) {
            return ['message' => $result['message']];
        }

        return [
            'message' => __('app.store_subscription_extension_rejected'),
            'request' => $result['request'],
        ];
    }

    public function rejectServiceExtensionRequest(int $id, ?string $reason = null): array
    {
        $validator = Validator::make(['rejectionReason' => $reason], [
            'rejectionReason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => $validator->errors(),
            ];
        }

        $request = ServiceSubscriptionExtensionRequest::find($id);
        if (! $request) {
            return ['message' => __('app.subscription_extension_request_not_found')];
        }

        $result = $this->subscriptionExtensionDao->rejectServiceExtensionRequest(
            $request,
            (int) Auth::id(),
            $validator->validated()['rejectionReason'] ?? null
        );
        if (! $result['success']) {
            return ['message' => $result['message']];
        }

        return [
            'message' => __('app.service_subscription_extension_rejected'),
            'request' => $result['request'],
        ];
    }

    /**
     * Allowed when end date is on or before (today + N days) in app timezone, or already ended.
     */
    private function subscriptionEndDateAllowsExtensionRequest(mixed $endDate): bool
    {
        if ($endDate === null || $endDate === '') {
            return false;
        }

        $tz = (string) config('app.timezone', 'UTC');
        $end = $endDate instanceof Carbon
            ? $endDate->copy()->timezone($tz)->startOfDay()
            : Carbon::parse($endDate, $tz)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();
        $cutoff = $today->copy()->addDays(self::EXTENSION_REQUEST_MAX_DAYS_BEFORE_END);

        return $end->lte($cutoff);
    }
}
