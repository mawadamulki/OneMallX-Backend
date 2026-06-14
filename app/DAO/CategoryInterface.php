<?php

namespace App\DAO;

use App\Models\Category;
use Illuminate\Support\Collection;

interface CategoryInterface
{
    public function listForStore(int $storeId): Collection;

    public function findForStore(int $categoryId, int $storeId): ?Category;

    public function createForStore(int $storeId, array $data): Category;

    public function update(Category $category, array $data): Category;

    public function delete(Category $category): bool;

    public function slugExistsInStore(int $storeId, string $slug, ?int $excludeCategoryId = null): bool;

    /** @param  int[]  $categoryIds */
    public function allBelongToStore(int $storeId, array $categoryIds): bool;
}
