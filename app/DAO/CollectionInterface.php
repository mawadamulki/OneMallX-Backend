<?php

namespace App\DAO;

use App\Models\ProductCollection;
use Illuminate\Support\Collection;

interface CollectionInterface
{
    public function listForStore(int $storeId): Collection;

    public function findForStore(int $collectionId, int $storeId): ?ProductCollection;

    public function createForStore(int $storeId, array $data): ProductCollection;

    public function update(ProductCollection $collection, array $data): ProductCollection;

    public function delete(ProductCollection $collection): bool;

    public function syncProducts(ProductCollection $collection, array $productIds): void;

    public function allProductsBelongToStore(int $storeId, array $productIds): bool;
}
