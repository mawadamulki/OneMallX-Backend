<?php

namespace App\DAO;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StoreInterface
{
    public function paginateVisibleToCustomers(int $perPage, ?int $areaId): LengthAwarePaginator;

    public function getStoreForCustomerDetail(Store $store): ?Store;

    public function paginateAdminStoresSummary(int $perPage): LengthAwarePaginator;

    public function getAdminStoreFull(int $storeId): Store;

    public function paginateStoreProductsSummary(int $storeId, int $perPage): LengthAwarePaginator;

    public function findAdminProductById(int $productId): ?Product;

    public function findStoreByOwnerId(int $userId): ?Store;

    public function updateStore(Store $store, array $data): Store;
}
