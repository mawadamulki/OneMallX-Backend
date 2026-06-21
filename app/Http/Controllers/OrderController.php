<?php

namespace App\Http\Controllers;

use App\Services\CheckoutService;
use App\Services\StoreOrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private CheckoutService $service,
        private StoreOrderService $storeOrderService,
    ) {}

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'payment_method_id' => 'nullable|integer',
            'location' => 'required|string|max:1000',
        ]);

        return $this->respond($this->service->checkout($data));
    }

    public function myOrders()
    {
        return $this->respond($this->service->getMyOrders());
    }

    public function show($id)
    {
        return $this->respond($this->service->getOrder((int) $id));
    }

    public function storeOrders(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->storeOrderService->getStoreOrders($perPage));
    }

    public function storeOrderShow($id)
    {
        return $this->respond($this->storeOrderService->getStoreOrder((int) $id));
    }

    private function respond(array $result)
    {
        $status = $result['http_status'] ?? ($result['success'] ? 200 : 422);
        unset($result['http_status'], $result['success']);

        return response()->json($result, $status);
    }
}
