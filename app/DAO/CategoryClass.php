<?php

namespace App\DAO;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryClass implements CategoryInterface
{
    public function listForStore(int $storeId): Collection
    {
        return Category::query()
            ->where('storeID', $storeId)
            ->with(['parent:id,name,slug'])
            ->orderBy('sortOrder')
            ->orderBy('name')
            ->get();
    }

    public function findForStore(int $categoryId, int $storeId): ?Category
    {
        return Category::query()
            ->whereKey($categoryId)
            ->where('storeID', $storeId)
            ->with(['parent', 'children'])
            ->first();
    }

    public function createForStore(int $storeId, array $data): Category
    {
        return Category::query()->create([
            ...$data,
            'storeID' => $storeId,
        ]);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh(['parent', 'children']);
    }

    public function delete(Category $category): bool
    {
        return (bool) $category->delete();
    }

    public function slugExistsInStore(int $storeId, string $slug, ?int $excludeCategoryId = null): bool
    {
        $query = Category::query()
            ->where('storeID', $storeId)
            ->where('slug', $slug);

        if ($excludeCategoryId !== null) {
            $query->where('id', '!=', $excludeCategoryId);
        }

        return $query->exists();
    }

    public function allBelongToStore(int $storeId, array $categoryIds): bool
    {
        if ($categoryIds === []) {
            return true;
        }

        $count = Category::query()
            ->where('storeID', $storeId)
            ->whereIn('id', $categoryIds)
            ->count();

        return $count === count(array_unique($categoryIds));
    }
}
