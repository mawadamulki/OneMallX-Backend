<?php

namespace App\DAO;

use App\Models\ServiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServiceProviderItemClass implements ServiceProviderItemInterface
{
    public function listForService(int $serviceId): Collection
    {
        return ServiceItem::query()
            ->where('serviceID', $serviceId)
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'employees',
            ])
            ->orderBy('name')
            ->get();
    }

    public function listNamesForService(int $serviceId): Collection
    {
        return ServiceItem::query()
            ->where('serviceID', $serviceId)
            ->with('employees')
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    public function findForService(int $itemId, int $serviceId): ?ServiceItem
    {
        return ServiceItem::query()
            ->whereKey($itemId)
            ->where('serviceID', $serviceId)
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'employees',
            ])
            ->first();
    }

    public function createForService(int $serviceId, array $data): ServiceItem
    {
        return DB::transaction(function () use ($serviceId, $data) {
            $employees = $data['employees'] ?? [];
            unset($data['employees']);

            /** @var ServiceItem $item */
            $item = ServiceItem::query()->create([
                ...$data,
                'serviceID' => $serviceId,
            ]);

            if ($employees !== []) {
                $this->applyEmployeeSync($item, $employees);
            }

            return $this->findForService((int) $item->id, $serviceId);
        });
    }

    public function update(ServiceItem $item, array $data): ServiceItem
    {
        $item->update($data);

        return $item->fresh(['media', 'employees']);
    }

    public function delete(ServiceItem $item): bool
    {
        return (bool) $item->delete();
    }

    /** @param  array<int, array{employeeID: int, price?: int|null}>  $employees */
    private function applyEmployeeSync(ServiceItem $item, array $employees): void
    {
        $sync = [];

        foreach ($employees as $row) {
            $employeeId = (int) ($row['employeeID'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }

            $sync[$employeeId] = [
                'price' => array_key_exists('price', $row) ? $row['price'] : null,
            ];
        }

        $item->employees()->sync($sync);
    }

    public function allBelongToService(int $serviceId, array $itemIds): bool
    {
        if ($itemIds === []) {
            return true;
        }

        $count = ServiceItem::query()
            ->where('serviceID', $serviceId)
            ->whereIn('id', $itemIds)
            ->count();

        return $count === count(array_unique($itemIds));
    }
}
