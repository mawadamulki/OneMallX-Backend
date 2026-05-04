<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Employee;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Support\ServiceEmployeeSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function createBooking($data)
    {
        return DB::transaction(function () use ($data) {

            //  Lock (منع race condition)
            $lockKey = "booking_lock_{$data['employee_id']}_{$data['date']}_{$data['time']}";
            $lock = Cache::lock($lockKey, 10);

            if (! $lock->get()) {
                return ['error' => 'Someone is booking this slot, try again'];
            }

            try {

                $service = Service::with('workingDays')->find($data['service_id']);
                if (! $service) {
                    return ['error' => 'Service not found'];
                }

                $item = ServiceItem::with(['employees', 'service.workingDays'])->find($data['service_item_id']);
                if (! $item) {
                    return ['error' => 'Service item not found'];
                }

                $employee = Employee::with('workingDays')->find($data['employee_id']);
                if (! $employee) {
                    return ['error' => 'Employee not found'];
                }

                //  منع الماضي
                if (Carbon::parse($data['date'])->isPast()) {
                    return ['error' => 'Cannot book in the past'];
                }

                // تحقق employee مرتبط بالـ item
                if (! $item->employees->contains('id', $employee->id)) {
                    return ['error' => 'Employee not assigned to this service'];
                }

                if (! ServiceEmployeeSchedule::bookingFitsWindow(
                    $service,
                    $employee,
                    $data['date'],
                    $data['time'],
                    (int) $item->duration
                )) {
                    return ['error' => 'Outside working hours'];
                }

                //  overlap logic
                $newStart = Carbon::parse($data['date'].' '.$data['time']);
                $newEnd = (clone $newStart)->addMinutes($item->duration);

                $bookings = Booking::where('employeeID', $data['employee_id'])
                    ->where('date', $data['date'])
                    ->get();

                foreach ($bookings as $booking) {

                    $existingStart = Carbon::parse($booking->date.' '.$booking->time);
                    $existingEnd = (clone $existingStart)
                        ->addMinutes($booking->serviceItem->duration);

                    if ($newStart < $existingEnd && $newEnd > $existingStart) {
                        return ['error' => 'Time overlaps with another booking'];
                    }
                }

                //  duplicate user
                $userId = Auth::id() ?? 1;

                $duplicate = Booking::where([
                    'customerID' => $userId,
                    'date' => $data['date'],
                    'time' => $data['time'],
                ])->exists();

                if ($duplicate) {
                    return ['error' => 'You already have booking at this time'];
                }

                $linePrice = $item->priceForEmployee((int) $employee->id);

                //  create
                $booking = Booking::create([
                    'serviceID' => $service->id,
                    'serviceItemID' => $item->id,
                    'customerID' => $userId,
                    'employeeID' => $employee->id,
                    'date' => $data['date'],
                    'time' => $data['time'],
                    'status' => 'pending',
                    'totalPrice' => $linePrice,
                ]);

                // 🧹 cache clear
                Cache::forget("availability_{$item->id}_{$data['date']}");

                return [
                    'message' => 'Booked successfully',
                    'booking' => $booking,
                ];

            } finally {
                $lock->release(); //  unlock
            }

        });
    }

    public function cancelBooking($id)
    {
        return DB::transaction(function () use ($id) {

            $booking = Booking::find($id);

            if (! $booking) {
                return ['error' => 'Booking not found'];
            }

            $bookingDateTime = Carbon::parse($booking->date.' '.$booking->time);
            $now = Carbon::now();

            if ($now->diffInHours($bookingDateTime, false) < 24) {
                return ['error' => 'Cannot cancel booking within 24 hours'];
            }

            $booking->update(['status' => 'cancelled']);

            //  clear cache
            Cache::forget("availability_{$booking->serviceItemID}_{$booking->date}");

            return ['message' => 'Booking cancelled successfully'];
        });
    }

    public function getServiceBookings($serviceId)
    {
        return Booking::with(['employee', 'serviceItem'])
            ->where('serviceID', $serviceId)
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(function ($booking) {
                return [
                    'booking_id' => $booking->id,
                    'date' => $booking->date,
                    'time' => $booking->time,
                    'employee' => [
                        'id' => $booking->employee?->id,
                        'name' => $booking->employee?->name,
                    ],
                    'service_item' => [
                        'id' => $booking->serviceItem?->id,
                        'name' => $booking->serviceItem?->name,
                    ],
                    'status' => $booking->status,
                ];
            });
    }
}
