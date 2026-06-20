<?php

namespace App\Http\Controllers;

use App\Models\BasketItem;
use App\Services\BasketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BasketController extends Controller
{
    public function __construct(private BasketService $service) {}

    public function show()
    {
        return $this->respond($this->service->getBasket());
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'line_type' => ['required', Rule::in([BasketItem::LINE_TYPE_PRODUCT, BasketItem::LINE_TYPE_SERVICE])],
            'service_id' => 'required_if:line_type,service|integer',
            'service_item_id' => 'required_if:line_type,service|integer',
            'employee_id' => 'required_if:line_type,service|integer',
            'date' => 'required_if:line_type,service|date|after_or_equal:today',
            'time' => 'required_if:line_type,service',
            'product_variant_id' => 'required_if:line_type,product|integer',
            'quantity' => 'required_if:line_type,product|integer|min:1',
        ]);

        return $this->respond($this->service->addItem($data));
    }

    public function updateItem(Request $request, $id)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        return $this->respond($this->service->updateItem((int) $id, $data));
    }

    public function destroyItem($id)
    {
        return $this->respond($this->service->removeItem((int) $id));
    }

    public function clear()
    {
        return $this->respond($this->service->clearBasket());
    }

    private function respond(array $result)
    {
        $status = $result['http_status'] ?? ($result['success'] ? 200 : 422);
        unset($result['http_status'], $result['success']);

        return response()->json($result, $status);
    }
}
