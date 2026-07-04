<?php

namespace App\DAO;

use App\Models\BusinessCategory;
use Illuminate\Support\Collection;

interface BusinessCategoryInterface
{
    public function listActive(?string $type = null): Collection;

    public function findActiveById(int $categoryId): ?BusinessCategory;

    public function findActiveForUsageType(int $categoryId, string $usageType): ?BusinessCategory;
}
