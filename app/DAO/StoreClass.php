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
            ->with(['area.floor', 'area.category', 'media'])
            ->withCount('rates')
            ->withAvg('rates', 'score')
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
            ->with(['area.floor', 'area.category', 'media'])
            ->withCount('rates')
            ->withAvg('rates', 'score')
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
            ->with(['media', 'area.floor', 'area.category', 'owner'])->first();

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

    public function findStoreByOwnerId(int $userId): ?Store
    {
        return Store::query()
            ->where('storeOwnerID', $userId)
            ->with(['media'])
            ->first();
    }

    public function updateStore(Store $store, array $data): Store
    {
        $store->update($data);

        return $store->fresh(['media']);
    }

    public function getStoreRateSummary(int $storeId): ?array
    {
        $store = Store::query()
            ->whereKey($storeId)
            ->withCount('rates')
            ->withAvg('rates', 'score')
            ->first();

        if (! $store) {
            return null;
        }

        return [
            'rating' => $store->rates_count > 0 ? round((float) $store->rates_avg_score, 1) : null,
            'rating_count' => (int) $store->rates_count,
        ];
    }

    public function getProductRateSummary(int $productId): ?array
    {
        $product = Product::query()
            ->whereKey($productId)
            ->withCount('rates')
            ->withAvg('rates', 'score')
            ->first();

        if (! $product) {
            return null;
        }

        return [
            'rating' => $product->rates_count > 0 ? round((float) $product->rates_avg_score, 1) : null,
            'rating_count' => (int) $product->rates_count,
        ];
    }
}
