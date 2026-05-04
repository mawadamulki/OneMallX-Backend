<?php

namespace App\Services;

use App\DAO\SubscriptionRequestInterface;
use App\Mail\SubscriptionRequestApprovedMail;
use App\Mail\SubscriptionRequestRejectedMail;
use App\Models\ServicePlanPrice;
use App\Models\ServiceSubscription;
use App\Models\ServiceSubscriptionExtensionRequest;
use App\Models\ServiceSubscriptionPlan;
use App\Models\ServiceSubscriptionRequest;
use App\Models\StorePlanPrice;
use App\Models\StoreSubscription;
use App\Models\StoreSubscriptionExtensionRequest;
use App\Models\StoreSubscriptionPlan;
use App\Models\StoreSubscriptionRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubscriptionRequestService
{
    public function __construct(
        protected SubscriptionRequestInterface $subscriptionRequestDao
    ) {}

    public function submitStoreRequest(array $input): array
    {
        $validator = Validator::make($input, [
            'applicantName' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8',
            'phoneNumber' => 'required|string|max:255',

            'storeName' => 'required|string|max:255',
            'description' => 'nullable|string',
            'storeStatus' => 'nullable|string|max:255',
            'paymentAccount' => 'nullable|string|max:255',

            'storeSubscriptionPlanID' => 'required|integer|exists:store_subscription_plans,id',
            'planPriceID' => 'required|integer|exists:store_plan_prices,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();

        if ($this->hasPendingSubscriptionRequest($data['email'])) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => ['email' => [__('app.pending_subscription_request_exists')]],
            ];
        }

        if (! $this->storePlanPriceMatches((int) $data['storeSubscriptionPlanID'], (int) $data['planPriceID'])) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => ['planPriceID' => [__('app.plan_price_mismatch')]],
            ];
        }

        $storePlan = StoreSubscriptionPlan::query()->find($data['storeSubscriptionPlanID']);
        if (! $storePlan) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => ['storeSubscriptionPlanID' => [__('app.store_plan_not_found')]],
            ];
        }
        if (! $storePlan->accepting_subscriptions) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => ['storeSubscriptionPlanID' => [__('app.store_plan_not_accepting_subscriptions')]],
            ];
        }

        $request = $this->subscriptionRequestDao->createStoreRequest($data);

        return [
            'message' => __('app.store_subscription_request_submitted'),
            'request' => $request,
        ];
    }

    public function submitServiceRequest(array $input): array
    {
        $validator = Validator::make($input, [
            'applicantName' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8',
            'phoneNumber' => 'required|string|max:255',

            'serviceName' => 'required|string|max:255',
            'price' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'paymentAccount' => 'nullable|string|max:255',
            'openTime' => ['nullable', 'string', 'max:10', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'closeTime' => ['nullable', 'string', 'max:10', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'duration' => 'nullable|integer|min:0',
            'locationID' => 'nullable|integer|exists:locations,id',
            'serviceStatus' => 'nullable|string|max:255',
            'daysOfWeek' => 'nullable|string|max:255',

            'serviceSubscriptionPlanID' => 'required|integer|exists:service_subscription_plans,id',
            'planPriceID' => 'required|integer|exists:service_plan_prices,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();

        if ($this->hasPendingSubscriptionRequest($data['email'])) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => ['email' => [__('app.pending_subscription_request_exists')]],
            ];
        }

        if (! $this->servicePlanPriceMatches((int) $data['serviceSubscriptionPlanID'], (int) $data['planPriceID'])) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => ['planPriceID' => [__('app.plan_price_mismatch')]],
            ];
        }

        $servicePlan = ServiceSubscriptionPlan::query()->find($data['serviceSubscriptionPlanID']);
        if (! $servicePlan) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => ['serviceSubscriptionPlanID' => [__('app.service_plan_not_found')]],
            ];
        }
        if (! $servicePlan->accepting_subscriptions) {
            return [
                'message' => __('app.validation_failed'),
                'errors' => ['serviceSubscriptionPlanID' => [__('app.service_plan_not_accepting_subscriptions')]],
            ];
        }

        $request = $this->subscriptionRequestDao->createServiceRequest($data);

        return [
            'message' => __('app.service_subscription_request_submitted'),
            'request' => $request,
        ];
    }

    public function listStoreRequests(?string $status = null): array
    {
        $requests = $this->subscriptionRequestDao->listStoreRequests($status);

        return ['requests' => $requests];
    }

    public function listServiceRequests(?string $status = null): array
    {
        $requests = $this->subscriptionRequestDao->listServiceRequests($status);

        return ['requests' => $requests];
    }

    public function approveStoreRequest(int $id): array
    {
        $request = StoreSubscriptionRequest::find($id);
        if (! $request) {
            return ['message' => __('app.subscription_request_not_found')];
        }

        $result = $this->subscriptionRequestDao->approveStoreRequest($request, (int) Auth::id());
        if (! $result['success']) {
            return ['message' => $result['message']];
        }

        $this->sendApprovalEmail($result['user']->name, true, $result['user']->email);

        return [
            'message' => __('app.store_subscription_request_approved'),
            'user' => $result['user'],
            'store' => $result['store'],
            'subscription' => $result['subscription'],
            'request' => $result['request'],
        ];
    }

    public function approveServiceRequest(int $id): array
    {
        $request = ServiceSubscriptionRequest::find($id);
        if (! $request) {
            return ['message' => __('app.subscription_request_not_found')];
        }

        $result = $this->subscriptionRequestDao->approveServiceRequest($request, (int) Auth::id());
        if (! $result['success']) {
            return ['message' => $result['message']];
        }

        $this->sendApprovalEmail($result['user']->name, false, $result['user']->email);

        return [
            'message' => __('app.service_subscription_request_approved'),
            'user' => $result['user'],
            'service' => $result['service'],
            'subscription' => $result['subscription'],
            'request' => $result['request'],
        ];
    }

    public function rejectStoreRequest(int $id, ?string $reason = null): array
    {
        $validator = Validator::make(['rejectionReason' => $reason], [
            'rejectionReason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $request = StoreSubscriptionRequest::find($id);
        if (! $request) {
            return ['message' => __('app.subscription_request_not_found')];
        }

        $result = $this->subscriptionRequestDao->rejectStoreRequest(
            $request,
            (int) Auth::id(),
            $validator->validated()['rejectionReason'] ?? null
        );
        if (! $result['success']) {
            return ['message' => $result['message']];
        }

        $fresh = $result['request'];
        $this->sendRejectionEmail(
            $fresh->applicantName,
            true,
            $fresh->email,
            $fresh->rejectionReason
        );

        return [
            'message' => __('app.store_subscription_request_rejected'),
            'request' => $fresh,
        ];
    }

    public function rejectServiceRequest(int $id, ?string $reason = null): array
    {
        $validator = Validator::make(['rejectionReason' => $reason], [
            'rejectionReason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $request = ServiceSubscriptionRequest::find($id);
        if (! $request) {
            return ['message' => __('app.subscription_request_not_found')];
        }

        $result = $this->subscriptionRequestDao->rejectServiceRequest(
            $request,
            (int) Auth::id(),
            $validator->validated()['rejectionReason'] ?? null
        );
        if (! $result['success']) {
            return ['message' => $result['message']];
        }

        $fresh = $result['request'];
        $this->sendRejectionEmail(
            $fresh->applicantName,
            false,
            $fresh->email,
            $fresh->rejectionReason
        );

        return [
            'message' => __('app.service_subscription_request_rejected'),
            'request' => $fresh,
        ];
    }

    private function hasPendingSubscriptionRequest(string $email): bool
    {
        return ServiceSubscriptionRequest::where('email', $email)->where('status', 'pending')->exists()
            || StoreSubscriptionRequest::where('email', $email)->where('status', 'pending')->exists();
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

    private function validationError(\Illuminate\Validation\Validator $validator): array
    {
        return [
            'message' => __('app.validation_failed'),
            'errors' => $validator->errors(),
        ];
    }

    private function sendApprovalEmail(string $applicantName, bool $isStoreAccount, string $email): void
    {
        try {
            Mail::to($email)->send(
                new SubscriptionRequestApprovedMail($applicantName, $isStoreAccount)
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function sendRejectionEmail(
        string $applicantName,
        bool $isStoreAccount,
        string $email,
        ?string $rejectionReason,
    ): void {
        try {
            Mail::to($email)->send(
                new SubscriptionRequestRejectedMail($applicantName, $isStoreAccount, $rejectionReason)
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Current store subscription for this owner (latest endDate, then id). Resolves everything from $userId only — no request body or query.
     *
     * @return array{success: bool, subscription?: array<string, mixed>, message?: string, http_status?: int}
     */
    public function getMyStoreSubscription(int $userId): array
    {
        $sub = StoreSubscription::query()
            ->with([
                'store.area',
                'storeSubscriptionPlan.floor',
                'planPrice',
            ])
            ->whereHas('store', fn ($q) => $q->where('storeOwnerID', $userId))
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();

        if (! $sub) {
            return [
                'success' => false,
                'message' => __('app.store_owner_no_subscription_for_extension'),
                'http_status' => 404,
            ];
        }

        $extensionRows = StoreSubscriptionExtensionRequest::query()
            ->where('storeSubscriptionID', $sub->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return [
            'success' => true,
            'subscription' => $this->formatOwnerStoreSubscriptionPayload($sub, $extensionRows),
            'http_status' => 200,
        ];
    }

    /**
     * Current service subscription for this provider (latest endDate, then id). Resolves everything from $userId only — no request body or query.
     *
     * @return array{success: bool, subscription?: array<string, mixed>, message?: string, http_status?: int}
     */
    public function getMyServiceSubscription(int $userId): array
    {
        $sub = ServiceSubscription::query()
            ->with([
                'service.area',
                'serviceSubscriptionPlan.floor',
                'servicePlanPrice',
            ])
            ->whereHas('service', fn ($q) => $q->where('serviceOwnerID', $userId))
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();

        if (! $sub) {
            return [
                'success' => false,
                'message' => __('app.service_provider_no_subscription_for_extension'),
                'http_status' => 404,
            ];
        }

        $extensionRows = ServiceSubscriptionExtensionRequest::query()
            ->where('serviceSubscriptionID', $sub->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return [
            'success' => true,
            'subscription' => $this->formatOwnerServiceSubscriptionPayload($sub, $extensionRows),
            'http_status' => 200,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StoreSubscriptionExtensionRequest>  $extensionRows
     * @return array<string, mixed>
     */
    private function formatOwnerStoreSubscriptionPayload(StoreSubscription $sub, $extensionRows): array
    {
        $store = $sub->store;
        $plan = $sub->storeSubscriptionPlan;
        $price = $sub->planPrice;
        $floor = $plan?->floor;

        return [
            'id' => $sub->id,
            'start_date' => $this->formatSubscriptionDate($sub->startDate),
            'end_date' => $this->formatSubscriptionDate($sub->endDate),
            'auto_renew' => (bool) $sub->autoRenew,
            'created_at' => $sub->created_at?->toIso8601String(),
            'updated_at' => $sub->updated_at?->toIso8601String(),
            'store' => $store ? [
                'id' => $store->id,
                'name' => $store->name,
                'description' => $store->description,
                'status' => $store->status,
                'account_status' => $store->accountStatus,
                'payment_account' => $store->paymentAccount,
                'area' => $store->area ? [
                    'id' => $store->area->id,
                    'name' => $store->area->name,
                    'number' => $store->area->number,
                    'floor_id' => $store->area->floorID,
                    'usage_type' => $store->area->usageType,
                ] : null,
            ] : null,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'floor_id' => $plan->floorID,
                'store_space' => $plan->storeSpace,
                'ads_number' => $plan->adsNumber,
                'accepting_subscriptions' => (bool) $plan->accepting_subscriptions,
                'floor' => $floor ? [
                    'id' => $floor->id,
                    'name' => $floor->name,
                    'number' => $floor->number,
                    'mall_id' => $floor->mallID,
                ] : null,
            ] : null,
            'plan_price' => $price ? [
                'id' => $price->id,
                'duration_months' => (int) $price->duration,
                'price' => $price->price,
            ] : null,
            'pending_extension_request' => $this->mapOwnerPendingExtension($extensionRows->firstWhere('status', 'pending')),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ServiceSubscriptionExtensionRequest>  $extensionRows
     * @return array<string, mixed>
     */
    private function formatOwnerServiceSubscriptionPayload(ServiceSubscription $sub, $extensionRows): array
    {
        $service = $sub->service;
        $plan = $sub->serviceSubscriptionPlan;
        $price = $sub->servicePlanPrice;
        $floor = $plan?->floor;

        return [
            'id' => $sub->id,
            'start_date' => $this->formatSubscriptionDate($sub->startDate),
            'end_date' => $this->formatSubscriptionDate($sub->endDate),
            'auto_renew' => (bool) $sub->autoRenew,
            'created_at' => $sub->created_at?->toIso8601String(),
            'updated_at' => $sub->updated_at?->toIso8601String(),
            'service' => $service ? [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'status' => $service->status,
                'account_status' => $service->accountStatus,
                'payment_account' => $service->paymentAccount,
                'open_time' => $service->openTime,
                'close_time' => $service->closeTime,
                'duration' => $service->duration,
                'location_id' => $service->locationID,
                'area' => $service->area ? [
                    'id' => $service->area->id,
                    'name' => $service->area->name,
                    'number' => $service->area->number,
                    'floor_id' => $service->area->floorID,
                    'usage_type' => $service->area->usageType,
                ] : null,
            ] : null,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'floor_id' => $plan->floorID,
                'service_space' => $plan->serviceSpace,
                'ads_number' => $plan->adsNumber,
                'accepting_subscriptions' => (bool) $plan->accepting_subscriptions,
                'floor' => $floor ? [
                    'id' => $floor->id,
                    'name' => $floor->name,
                    'number' => $floor->number,
                    'mall_id' => $floor->mallID,
                ] : null,
            ] : null,
            'plan_price' => $price ? [
                'id' => $price->id,
                'duration_months' => (int) $price->duration,
                'price' => $price->price,
            ] : null,
            'pending_extension_request' => $this->mapOwnerPendingExtension($extensionRows->firstWhere('status', 'pending')),
        ];
    }

    private function formatSubscriptionDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function mapOwnerPendingExtension(StoreSubscriptionExtensionRequest|ServiceSubscriptionExtensionRequest|null $r): ?array
    {
        if ($r === null) {
            return null;
        }

        return $this->mapOwnerExtensionRequest($r);
    }

    private function mapOwnerExtensionRequest(StoreSubscriptionExtensionRequest|ServiceSubscriptionExtensionRequest $r): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'applicant_note' => $r->applicantNote,
            'requested_at' => $r->created_at?->toIso8601String(),
            'reviewed_at' => $r->reviewedAt?->toIso8601String(),
            'rejection_reason' => $r->rejectionReason,
        ];
    }
}
