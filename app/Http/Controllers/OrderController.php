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

    public function storeOrders()
    {
        return $this->respond($this->storeOrderService->getStoreOrders());
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
