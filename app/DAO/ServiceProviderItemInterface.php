<?php

namespace App\DAO;

use App\Models\ServiceItem;
use Illuminate\Support\Collection;

interface ServiceProviderItemInterface
{
    public function listForService(int $serviceId): Collection;

    public function findForService(int $itemId, int $serviceId): ?ServiceItem;

    public function createForService(int $serviceId, array $data): ServiceItem;

    public function update(ServiceItem $item, array $data): ServiceItem;

    public function delete(ServiceItem $item): bool;

    /**
     * @param  array<int, array{employeeID: int, price?: int|null}>  $employees
     */
    public function syncEmployees(ServiceItem $item, array $employees): ServiceItem;
}
