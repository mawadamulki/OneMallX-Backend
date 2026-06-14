<?php

namespace App\DAO;

use App\Models\Service;

interface ServiceProviderInterface
{
    public function findServiceByProviderId(int $userId): ?Service;

    public function findServiceForProvider(int $serviceId, int $providerId): ?Service;

    public function updateService(Service $service, array $data): Service;

    /** @param  list<int>  $isoWeekdays */
    public function syncWorkingDays(Service $service, array $isoWeekdays): Service;
}
