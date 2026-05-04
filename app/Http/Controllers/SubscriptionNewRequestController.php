<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionNewRequestService;
use Illuminate\Http\Request;

class SubscriptionNewRequestController extends Controller
{
    public function __construct(
        private SubscriptionNewRequestService $subscriptionNewRequestService
    ) {}

    public function submitStore(Request $request)
    {
        $result = $this->subscriptionNewRequestService->submitStoreNewRequestFromOwner(
            (int) $request->user()->id,
            $request->all()
        );

        return response()->json(
            array_filter([
                'message' => $result['message'] ?? null,
                'errors' => $result['errors'] ?? null,
                'request' => $result['request'] ?? null,
            ], fn ($value) => $value !== null),
            $result['http_status'] ?? 200
        );
    }

    public function submitService(Request $request)
    {
        $result = $this->subscriptionNewRequestService->submitServiceNewRequestFromProvider(
            (int) $request->user()->id,
            $request->all()
        );

        return response()->json(
            array_filter([
                'message' => $result['message'] ?? null,
                'errors' => $result['errors'] ?? null,
                'request' => $result['request'] ?? null,
            ], fn ($value) => $value !== null),
            $result['http_status'] ?? 200
        );
    }

    public function indexStore(string $status)
    {
        $filter = $status === 'all' ? null : $status;

        return response()->json(
            $this->subscriptionNewRequestService->listStoreNewRequests($filter)
        );
    }

    public function indexService(string $status)
    {
        $filter = $status === 'all' ? null : $status;

        return response()->json(
            $this->subscriptionNewRequestService->listServiceNewRequests($filter)
        );
    }

    public function approveStore(int $id)
    {
        $result = $this->subscriptionNewRequestService->approveStoreNewRequest($id);

        return response()->json(
            array_filter([
                'message' => $result['message'] ?? null,
                'subscription' => $result['subscription'] ?? null,
                'request' => $result['request'] ?? null,
            ], fn ($value) => $value !== null),
            $result['http_status'] ?? 200
        );
    }

    public function approveService(int $id)
    {
        $result = $this->subscriptionNewRequestService->approveServiceNewRequest($id);

        return response()->json(
            array_filter([
                'message' => $result['message'] ?? null,
                'subscription' => $result['subscription'] ?? null,
                'request' => $result['request'] ?? null,
            ], fn ($value) => $value !== null),
            $result['http_status'] ?? 200
        );
    }

    public function rejectStore(Request $request, int $id)
    {
        $result = $this->subscriptionNewRequestService->rejectStoreNewRequest(
            $id,
            $request->input('rejectionReason')
        );

        return response()->json(
            array_filter([
                'message' => $result['message'] ?? null,
                'errors' => $result['errors'] ?? null,
                'request' => $result['request'] ?? null,
            ], fn ($value) => $value !== null),
            $result['http_status'] ?? 200
        );
    }

    public function rejectService(Request $request, int $id)
    {
        $result = $this->subscriptionNewRequestService->rejectServiceNewRequest(
            $id,
            $request->input('rejectionReason')
        );

        return response()->json(
            array_filter([
                'message' => $result['message'] ?? null,
                'errors' => $result['errors'] ?? null,
                'request' => $result['request'] ?? null,
            ], fn ($value) => $value !== null),
            $result['http_status'] ?? 200
        );
    }
}
