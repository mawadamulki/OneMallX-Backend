<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionRequestController extends Controller
{
    public function __construct(
        private SubscriptionRequestService $subscriptionRequestService
    ) {}

    public function submitStore(Request $request)
    {
        return response()->json(
            $this->subscriptionRequestService->submitStoreRequest($request->all())
        );
    }

    public function submitService(Request $request)
    {
        return response()->json(
            $this->subscriptionRequestService->submitServiceRequest($request->all())
        );
    }

    public function indexStore(string $status)
    {
        $filter = $status === 'all' ? null : $status;

        return response()->json(
            $this->subscriptionRequestService->listStoreRequests($filter)
        );
    }

    public function indexService(string $status)
    {
        $filter = $status === 'all' ? null : $status;

        return response()->json(
            $this->subscriptionRequestService->listServiceRequests($filter)
        );
    }

    public function approveStore(int $id)
    {
        return response()->json(
            $this->subscriptionRequestService->approveStoreRequest($id)
        );
    }

    public function approveService(int $id)
    {
        return response()->json(
            $this->subscriptionRequestService->approveServiceRequest($id)
        );
    }

    public function rejectStore(Request $request, int $id)
    {
        return response()->json(
            $this->subscriptionRequestService->rejectStoreRequest(
                $id,
                $request->input('rejectionReason')
            )
        );
    }

    public function rejectService(Request $request, int $id)
    {
        return response()->json(
            $this->subscriptionRequestService->rejectServiceRequest(
                $id,
                $request->input('rejectionReason')
            )
        );
    }

    /**
     * Store owner’s current subscription (store, plan, price, extensions). Uses only the Sanctum token — no body or query params.
     */
    public function myStoreSubscription()
    {
        $result = $this->subscriptionRequestService->getMyStoreSubscription((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json(['subscription' => $result['subscription']]);
    }

    /**
     * Service provider’s current subscription (service, plan, price, extensions). Uses only the Sanctum token — no body or query params.
     */
    public function myServiceSubscription()
    {
        $result = $this->subscriptionRequestService->getMyServiceSubscription((int) Auth::id());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json(['subscription' => $result['subscription']]);
    }
}
