<?php

namespace App\DAO;

use App\Models\Service;
use App\Models\ServiceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ServiceDAO
{
    public function getByArea($areaId)
    {
        return Service::with(['media', 'rates'])
            ->where('areaID', $areaId)
            ->get();
    }

    public function findWithDetails($id)
    {
        return Service::with([
            'media',
            'rates',
            'employees',
            'workingDays',
            'serviceItems' => fn ($q) => $q->active()->with(['media', 'rates']),
        ])->find($id);
    }

    public function paginateAdminServicesSummary(int $perPage): LengthAwarePaginator
    {
        return Service::with([
            'media',
            'rates',
            'employees',
            'serviceItems.media',
            'serviceItems.rates',
        ])->paginate($perPage);
    }

    public function findAdminServiceById($serviceId): Service
    {
        return Service::query()
            ->whereKey($serviceId)
            ->with(['media', 'rates', 'employees', 'workingDays', 'serviceItems.media', 'serviceItems.rates', 'owner', 'area', 'area.floor'])
            ->firstOrFail();
    }

    public function getAdminServiceItems($serviceId): Collection
    {
        return ServiceItem::query()
            ->where('serviceID', $serviceId)
            ->with(['media', 'rates'])
            ->get();
    }

    public function findAdminServiceItemById($serviceItemId): ServiceItem
    {
        return ServiceItem::query()
            ->whereKey($serviceItemId)
            ->with([
                'media',
                'rates',
                'service',
                'employees.workingDays',
                'bookings.employee',
                'owner',
                'area',
            ])
            ->first();
    }
}
