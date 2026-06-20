<?php

namespace App\DAO;

use App\Models\ServiceItem;

class ServiceItemDAO
{
    public function findWithEmployees($id)
    {
        return ServiceItem::active()->with([
            'media',
            'rates',
            'service.workingDays',
            'employees.workingDays',
            'employees.bookings',
        ])->find($id);
    }

    public function getByService($serviceId)
    {
        return ServiceItem::active()->with(['media', 'rates', 'employees'])
            ->where('serviceID', $serviceId)
            ->get();
    }
}
