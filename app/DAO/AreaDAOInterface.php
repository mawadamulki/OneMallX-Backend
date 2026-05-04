<?php


namespace App\DAO;

use App\Models\Area;

interface AreaDAOInterface
{
    public function getByFloor($floorId);
    public function getStoreAreasByFloor($floorId);
    public function getServiceAreasByFloor($floorId);
    public function findById($id): ?Area;
    public function create(array $data): Area;
    public function update(Area $area, array $data): Area;
    public function delete(Area $area): bool;
}
