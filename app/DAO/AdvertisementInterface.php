<?php

namespace App\DAO;

use App\Models\Advertisement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AdvertisementInterface
{
    public function paginateAllForAdmin(
        int $perPage,
        ?string $ownerType = null,
        ?string $status = null,
        ?string $placement = null,
    ): LengthAwarePaginator;

    public function listForStore(int $storeId): Collection;

    public function listForService(int $serviceId): Collection;

    public function findForStore(int $adId, int $storeId): ?Advertisement;

    public function findForService(int $adId, int $serviceId): ?Advertisement;

    public function countActiveForStore(int $storeId): int;

    public function countActiveForService(int $serviceId): int;

    public function listActivePublic(string $placement): Collection;

    public function create(array $data): Advertisement;

    public function update(Advertisement $ad, array $data): Advertisement;

    public function delete(Advertisement $ad): bool;
}
