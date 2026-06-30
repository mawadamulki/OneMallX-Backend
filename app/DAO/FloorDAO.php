<?php

namespace App\DAO;

use App\Models\Area;
use App\Models\Floor;
use App\Models\Service;
use App\Models\Store;

class FloorDAO implements FloorDAOInterface
{
    public function getAll()
    {
        return Floor::with('media')->get();
    }

    public function findById($id): ?Floor
    {
        return Floor::with(['media', 'areas.media'])->find($id);
    }

    public function create(array $data): Floor
    {
        return Floor::create($data);
    }

    public function update(Floor $floor, array $data): Floor
    {
        $floor->update($data);
        return $floor;
    }

    public function delete(Floor $floor): bool
    {
        return $floor->delete();
    }

    public function getAdminFloors()
    {
        return Floor::query()
            ->select(['id', 'name', 'number', 'mallID'])
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'areas' => fn ($q) => $q->select(['id', 'floorID', 'categoryID'])->with('category:id,name,slug'),
            ])
            ->withCount([
                'areas',
                'stores as active_stores_count' => fn ($q) => $q->where('accountStatus', 'active'),
                'services as active_services_count' => fn ($q) => $q->where('accountStatus', 'active'),
            ])
            ->withSum('areas as total_capacity', 'maxCapacity')
            ->orderBy('mallID')
            ->get();
    }

    public function getAdminFloorsOverviewCounts(): array
    {
        return [
            'floorsCount' => Floor::query()->count(),
            'areasCount' => Area::query()->count(),
            'activeStoresAndServicesCount' => Store::query()->where('accountStatus', 'active')->count()
                + Service::query()->where('accountStatus', 'active')->count(),
        ];
    }

    public function getShortFloors()
    {
        return Floor::query()
            ->select(['id', 'name', 'number'])
            ->get();
    }
}
