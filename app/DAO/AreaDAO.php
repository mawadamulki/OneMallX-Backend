<?php

namespace App\DAO;

use App\Models\Area;

class AreaDAO implements AreaDAOInterface
{
    public function getByFloor($floorId)
    {
        return Area::with(['media', 'category'])->where('floorID', $floorId)->get();
    }

    public function getStoreAreasByFloor($floorId)
    {
        return Area::query()
            ->with(['media', 'category'])
            ->withCount([
                'stores as occupied' => fn ($q) => $q->where('accountStatus', 'active'),
            ])
            ->where('floorID', $floorId)
            ->where('usageType', 'store')
            ->get();
    }

    public function getServiceAreasByFloor($floorId)
    {
        return Area::query()
            ->with(['media', 'category'])
            ->withCount([
                'services as occupied' => fn ($q) => $q->where('accountStatus', 'active'),
            ])
            ->where('floorID', $floorId)
            ->where('usageType', 'service')
            ->get();
    }

    public function findById($id): ?Area
    {
        return Area::with(['media', 'category'])->find($id);
    }

    public function create(array $data): Area
    {
        return Area::create($data);
    }

    public function update(Area $area, array $data): Area
    {
        $area->update($data);

        return $area;
    }

    public function delete(Area $area): bool
    {
        return $area->delete();
    }
}
