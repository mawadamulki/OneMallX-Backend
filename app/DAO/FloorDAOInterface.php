<?php
namespace App\DAO;

use App\Models\Floor;

interface FloorDAOInterface
{
    public function getAll();
    public function findById($id): ?Floor;
    public function create(array $data): Floor;
    public function update(Floor $floor, array $data): Floor;
    public function delete(Floor $floor): bool;
    public function getAdminFloors();
    public function getAdminFloorsOverviewCounts(): array;
    public function getShortFloors();
}
