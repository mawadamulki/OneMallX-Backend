<?php

namespace App\DAO;

use App\Models\ServiceItem;
use Illuminate\Support\Collection;

interface ServiceProviderItemInterface
{
    public function listForService(int $serviceId): Collection;

    public function listNamesForService(int $serviceId): Collection;

    public function findForService(int $itemId, int $serviceId): ?ServiceItem;

    public function createForService(int $serviceId, array $data): ServiceItem;

    public function update(ServiceItem $item, array $data): ServiceItem;

    public function delete(ServiceItem $item): bool;

    /** @param  int[]  $itemIds */
    public function allBelongToService(int $serviceId, array $itemIds): bool;
}
