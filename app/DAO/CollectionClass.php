<?php

namespace App\DAO;

use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Support\Collection;

class CollectionClass implements CollectionInterface
{
    public function listForStore(int $storeId): Collection
    {
        return ProductCollection::query()
            ->where('storeID', $storeId)
            ->withCount('products')
            ->orderBy('name')
            ->get();
    }

    public function findForStore(int $collectionId, int $storeId): ?ProductCollection
    {
        return ProductCollection::query()
            ->whereKey($collectionId)
            ->where('storeID', $storeId)
            ->with([
                'products' => fn ($q) => $q
                    ->with(['media' => fn ($m) => $m->orderBy('id')])
                    ->with(['variants' => fn ($v) => $v->orderByDesc('isDefault')])
                    ->orderBy('name'),
            ])
            ->withCount('products')
            ->first();
    }

    public function createForStore(int $storeId, array $data): ProductCollection
    {
        return ProductCollection::query()->create([
            ...$data,
            'storeID' => $storeId,
        ]);
    }

    public function update(ProductCollection $collection, array $data): ProductCollection
    {
        $collection->update($data);

        return $collection->fresh()->loadCount('products');
    }

    public function delete(ProductCollection $collection): bool
    {
        return (bool) $collection->delete();
    }

    public function syncProducts(ProductCollection $collection, array $productIds): void
    {
        $collection->products()->sync($productIds);
    }

    public function allProductsBelongToStore(int $storeId, array $productIds): bool
    {
        if ($productIds === []) {
            return true;
        }

        $uniqueIds = array_values(array_unique($productIds));

        $count = Product::query()
            ->where('storeID', $storeId)
            ->whereIn('id', $uniqueIds)
            ->count();

        return $count === count($uniqueIds);
    }
}
