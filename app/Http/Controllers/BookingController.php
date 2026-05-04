<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BookingService;

class BookingController extends Controller
{
        public function __construct(private BookingService $service) {}
    public function store(Request $request)
{
    $data = $request->validate([
        'service_id' => 'required|integer',
        'service_item_id' => 'required|integer',
        'employee_id' => 'required|integer',
        'date' => 'required|date',
        'time' => 'required'
    ]);

    return response()->json(
        $this->service->createBooking($data)
    );
}

public function cancel($id)
{
    return response()->json(
        $this->service->cancelBooking($id)
    );
}

public function serviceBookings($serviceId)
{
    return response()->json(
        $this->service->getServiceBookings($serviceId)
    );
}
}
