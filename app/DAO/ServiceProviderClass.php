<?php

namespace App\DAO;

use App\Models\Service;
use App\Support\WorkingWeekday;

class ServiceProviderClass implements ServiceProviderInterface
{
    public function findServiceByProviderId(int $userId): ?Service
    {
        return Service::query()
            ->where('serviceOwnerID', $userId)
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'workingDays',
                'area.floor',
                'location',
            ])
            ->first();
    }

    public function findServiceForProvider(int $serviceId, int $providerId): ?Service
    {
        return Service::query()
            ->whereKey($serviceId)
            ->where('serviceOwnerID', $providerId)
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'workingDays',
                'area.floor',
                'location',
            ])
            ->first();
    }

    public function updateService(Service $service, array $data): Service
    {
        $service->update($data);

        return $service->fresh([
            'media',
            'workingDays',
            'area.floor',
            'location',
        ]);
    }

    public function syncWorkingDays(Service $service, array $isoWeekdays): Service
    {
        WorkingWeekday::syncForService($service, $isoWeekdays);

        return $service->fresh(['workingDays']);
    }
}
