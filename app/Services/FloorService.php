<?php

namespace App\Services;

use App\DAO\FloorDAOInterface;
use App\Models\Floor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FloorService
{
    protected $floorDAO;

    public function __construct(FloorDAOInterface $floorDAO)
    {
        $this->floorDAO = $floorDAO;
    }

    public function getAllFloors()
    {
        return $this->floorDAO->getAll();
    }

    public function getFloor($id)
    {
        return $this->floorDAO->findById($id);
    }

    public function createFloor($data)
    {
        return $this->floorDAO->create($data);
    }

    public function updateFloor($id, $data)
    {
        $floor = $this->floorDAO->findById($id);

        if (! $floor) {
            return null;
        }

        return $this->floorDAO->update($floor, $data);
    }

    public function deleteFloor($id)
    {
        $floor = $this->floorDAO->findById($id);

        if (! $floor) {
            return false;
        }

        return $this->floorDAO->delete($floor);
    }

    public function getFloorMedia(int $id): ?array
    {
        $floor = Floor::query()->with(['media' => fn ($q) => $q->orderBy('id')])->find($id);

        if (! $floor) {
            return null;
        }

        return ['media' => $floor->media];
    }

    public function attachMedia(int $id, UploadedFile $file): array
    {
        $floor = Floor::query()->find($id);

        if (! $floor) {
            return ['error' => 'Floor not found'];
        }

        foreach ($floor->media as $existing) {
            $relative = $existing->publicDiskRelativePath();
            if ($relative !== null) {
                Storage::disk('public')->delete($relative);
            }
            $existing->forceDelete();
        }

        $path = $file->store("floors/{$floor->id}", 'public');

        $media = $floor->media()->create([
            'fileType' => $file->getClientMimeType(),
            'url' => $path,
        ]);

        return ['message' => 'Photo uploaded', 'media' => $media];
    }

    public function getAdminFloors()
    {
        return $this->floorDAO->getAdminFloors()->map(function (Floor $floor) {
            $areaCategories = $floor->areas
                ->pluck('category')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            $mediaUrls = $floor->media
                ->pluck('url')
                ->filter()
                ->values()
                ->all();

            return [
                'id' => $floor->id,
                'name' => $floor->name,
                'number' => $floor->number,
                'mallID' => $floor->mallID,
                'media' => $mediaUrls,
                'areasCount' => (int) $floor->areas_count,
                'totalCapacity' => (int) ($floor->total_capacity ?? 0),
                'rentedStoresAndServicesCount' => (int) $floor->active_stores_count + (int) $floor->active_services_count,
                'areaCategories' => $areaCategories,
            ];
        })->values();
    }

    public function getAdminFloorsOverviewCounts()
    {
        $overview = $this->floorDAO->getAdminFloorsOverviewCounts();

        return [
            'floorsCount' => $overview['floorsCount'],
            'areasCount' => $overview['areasCount'],
            'activeStoresAndServicesCount' => $overview['activeStoresAndServicesCount'],
        ];

    }

    public function getShortFloors()
    {
        return $this->floorDAO->getShortFloors()->map(function (Floor $floor) {
            return [
                'id' => $floor->id,
                'name' => $floor->name,
                'number' => $floor->number,
            ];
        });
    }
}
