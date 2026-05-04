<?php

namespace App\Services;

use App\DAO\AreaDAOInterface;
use App\DAO\FloorDAOInterface;
use App\Models\Area;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AreaService
{
    protected $areaDAO;

    protected $floorDAO;

    public function __construct(
        AreaDAOInterface $areaDAO,
        FloorDAOInterface $floorDAO
    ) {
        $this->areaDAO = $areaDAO;
        $this->floorDAO = $floorDAO;
    }

    public function getStoreAreasByFloor($floorId)
    {
        return $this->areaDAO->getStoreAreasByFloor($floorId);
    }

    public function getServiceAreasByFloor($floorId)
    {
        return $this->areaDAO->getServiceAreasByFloor($floorId);
    }

    public function getAreasByFloor($floorId)
    {
        return $this->areaDAO->getByFloor($floorId);
    }

    public function getArea($id)
    {
        return $this->areaDAO->findById($id);
    }

    public function createArea($floorId, $data)
    {
        $floor = $this->floorDAO->findById($floorId);

        if (! $floor) {
            return ['error' => 'Floor not found'];
        }

        $data['floorID'] = $floorId;

        return $this->areaDAO->create($data);
    }

    public function updateArea($id, $data)
    {
        $area = $this->areaDAO->findById($id);

        if (! $area) {
            return null;
        }

        return $this->areaDAO->update($area, $data);
    }

    public function deleteArea($id)
    {
        $area = $this->areaDAO->findById($id);

        if (! $area) {
            return false;
        }

        return $this->areaDAO->delete($area);
    }

    public function getAreaMedia(int $id): ?array
    {
        $area = Area::query()->with(['media' => fn ($q) => $q->orderBy('id')])->find($id);

        if (! $area) {
            return null;
        }

        return ['media' => $area->media];
    }

    public function attachMedia(int $id, UploadedFile $file): array
    {
        $area = Area::query()->find($id);

        if (! $area) {
            return ['error' => 'Area not found'];
        }

        foreach ($area->media as $existing) {
            $relative = $existing->publicDiskRelativePath();
            if ($relative !== null) {
                Storage::disk('public')->delete($relative);
            }
            $existing->forceDelete();
        }

        $path = $file->store("areas/{$area->id}", 'public');

        $media = $area->media()->create([
            'fileType' => $file->getClientMimeType(),
            'url' => $path,
        ]);

        return ['message' => 'Photo uploaded', 'media' => $media];
    }
}
