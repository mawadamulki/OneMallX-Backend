<?php

namespace App\Http\Controllers;

use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $service) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_id' => 'required|integer',
            'service_item_id' => 'required|integer',
            'employee_id' => 'required|integer',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required',
        ]);

        return $this->respond($this->service->createBooking($data));
    }

    public function cancel($id)
    {
        return $this->respond($this->service->cancelBooking((int) $id));
    }

    public function myBookings()
    {
        return $this->respond($this->service->getMyBookings());
    }

    public function show($id)
    {
        return $this->respond($this->service->getBooking((int) $id));
    }

    public function serviceBookings($serviceId)
    {
        return $this->respond($this->service->getServiceBookings((int) $serviceId));
    }

    private function respond(array $result)
    {
        $status = $result['http_status'] ?? ($result['success'] ? 200 : 422);
        unset($result['http_status'], $result['success']);

        return response()->json($result, $status);
    }
}
