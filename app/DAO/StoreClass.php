<?php

namespace App\DAO;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StoreClass implements StoreInterface
{
    public function paginateVisibleToCustomers(int $perPage, ?int $areaId): LengthAwarePaginator
    {
        $query = Store::query()
            ->visibleToCustomers()
            ->with(['area.floor', 'media'])
            ->orderBy('name');

        if ($areaId !== null) {
            $query->where('areaID', $areaId);
        }

        return $query->paginate($perPage);
    }

    public function getStoreForCustomerDetail(Store $store): ?Store
    {
        if (! $store->isVisibleToCustomers()) {
            return null;
        }

        return Store::query()
            ->whereKey($store->id)
            ->with(['area.floor', 'media'])
            ->first();
    }

    public function paginateAdminStoresSummary(int $perPage): LengthAwarePaginator
    {
        return Store::query()
            ->select(['id', 'name'])
            ->with(['media' => fn ($q) => $q->orderBy('id')])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getAdminStoreFull(int $storeId): Store
    {
        return Store::query()
            ->whereKey($storeId)
            ->with(['media', 'area.floor', 'owner'])->first();

        if (! $store) {
            return null;
        }

        return $store;
    }

    public function paginateStoreProductsSummary(int $storeId, int $perPage): LengthAwarePaginator
    {
        return Product::query()
            ->where('storeID', $storeId)
            ->select(['id', 'name', 'slug', 'status'])
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'defaultVariant',
            ])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findAdminProductById(int $productId): ?Product
    {
        $product = Product::query()
            ->whereKey($productId)
            ->with([
                'media',
                'store',
                'categories',
                'variants.attributeValues.attribute',
            ])->first();

        if (! $product) {
            return null;
        }

        return $product;
    }
}
