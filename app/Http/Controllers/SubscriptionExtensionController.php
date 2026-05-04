<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionExtensionService;
use Illuminate\Http\Request;

class SubscriptionExtensionController extends Controller
{
    public function __construct(
        private SubscriptionExtensionService $subscriptionExtensionService
    ) {}

    public function indexStore(string $status)
    {
        $filter = $status === 'all' ? null : $status;

        return response()->json(
            $this->subscriptionExtensionService->listStoreExtensionRequests($filter)
        );
    }

    public function indexService(string $status)
    {
        $filter = $status === 'all' ? null : $status;

        return response()->json(
            $this->subscriptionExtensionService->listServiceExtensionRequests($filter)
        );
    }

    public function approveStore(int $id)
    {
        return response()->json(
            $this->subscriptionExtensionService->approveStoreExtensionRequest($id)
        );
    }

    public function approveService(int $id)
    {
        return response()->json(
            $this->subscriptionExtensionService->approveServiceExtensionRequest($id)
        );
    }

    public function rejectStore(Request $request, int $id)
    {
        return response()->json(
            $this->subscriptionExtensionService->rejectStoreExtensionRequest(
                $id,
                $request->input('rejectionReason')
            )
        );
    }

    public function rejectService(Request $request, int $id)
    {
        return response()->json(
            $this->subscriptionExtensionService->rejectServiceExtensionRequest(
                $id,
                $request->input('rejectionReason')
            )
        );
    }

    /**
     * Store owner: submit a subscription extension request (pending until admin approves).
     */
    public function submitStore(Request $request)
    {
        $result = $this->subscriptionExtensionService->submitStoreExtensionRequestFromOwner(
            (int) $request->user()->id
        );

        if (! $result['success']) {
            return response()->json(
                ['message' => $result['message']],
                $result['http_status'] ?? 422
            );
        }

        return response()->json([
            'message' => $result['message'],
            'request' => $result['request'],
        ], 201);
    }

    /**
     * Service provider: submit a subscription extension request (pending until admin approves).
     */
    public function submitService(Request $request)
    {
        $result = $this->subscriptionExtensionService->submitServiceExtensionRequestFromProvider(
            (int) $request->user()->id
        );

        if (! $result['success']) {
            return response()->json(
                ['message' => $result['message']],
                $result['http_status'] ?? 422
            );
        }

        return response()->json([
            'message' => $result['message'],
            'request' => $result['request'],
        ], 201);
    }
}
