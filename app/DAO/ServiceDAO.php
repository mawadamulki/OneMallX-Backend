<?php

namespace App\DAO;

use App\Models\Service;
use App\Models\ServiceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ServiceDAO
{
    public function paginateVisibleToCustomers(int $perPage, ?int $areaId): LengthAwarePaginator
    {
        $query = Service::query()
            ->visibleToCustomers()
            ->with(['area.floor', 'area.category', 'media' => fn ($q) => $q->orderBy('id')])
            ->withCount('rates')
            ->withAvg('rates', 'score')
            ->orderBy('name');

        if ($areaId !== null) {
            $query->where('areaID', $areaId);
        }

        return $query->paginate($perPage);
    }

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
            ->with(['media', 'rates', 'employees', 'workingDays', 'serviceItems.media', 'serviceItems.rates', 'owner', 'area.category', 'area.floor'])
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
                'service.owner',
                'service.area.floor',
                'employees.workingDays',
            ])
            ->first();
    }

    public function getServiceRateSummary(int $serviceId): ?array
    {
        $service = Service::query()
            ->whereKey($serviceId)
            ->withCount('rates')
            ->withAvg('rates', 'score')
            ->first();

        if (! $service) {
            return null;
        }

        return [
            'rating' => $service->rates_count > 0 ? round((float) $service->rates_avg_score, 1) : null,
            'rating_count' => (int) $service->rates_count,
        ];
    }

    public function getServiceItemRateSummary(int $serviceItemId): ?array
    {
        $item = ServiceItem::query()
            ->whereKey($serviceItemId)
            ->withCount('rates')
            ->withAvg('rates', 'score')
            ->first();

        if (! $item) {
            return null;
        }

        return [
            'rating' => $item->rates_count > 0 ? round((float) $item->rates_avg_score, 1) : null,
            'rating_count' => (int) $item->rates_count,
        ];
    }
}
