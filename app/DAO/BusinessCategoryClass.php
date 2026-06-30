<?php

namespace App\DAO;

use App\Models\BusinessCategory;
use Illuminate\Support\Collection;

class BusinessCategoryClass implements BusinessCategoryInterface
{
    public function listActive(?string $type = null): Collection
    {
        $query = BusinessCategory::query()
            ->active()
            ->orderBy('sortOrder')
            ->orderBy('name');

        if ($type !== null && in_array($type, ['store', 'service'], true)) {
            $query->forUsageType($type);
        }

        return $query->get();
    }

    public function findActiveById(int $categoryId): ?BusinessCategory
    {
        return BusinessCategory::query()
            ->active()
            ->whereKey($categoryId)
            ->first();
    }

    public function findActiveForUsageType(int $categoryId, string $usageType): ?BusinessCategory
    {
        return BusinessCategory::query()
            ->active()
            ->forUsageType($usageType)
            ->whereKey($categoryId)
            ->first();
    }
}
