<?php

namespace App\DAO;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SearchInterface
{
    public function searchStores(string $query, int $perPage): LengthAwarePaginator;

    public function searchProducts(string $query, int $perPage, ?int $storeId = null): LengthAwarePaginator;

    public function searchServices(string $query, int $perPage): LengthAwarePaginator;
}
