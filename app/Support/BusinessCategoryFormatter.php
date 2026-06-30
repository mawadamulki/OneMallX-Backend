<?php

namespace App\Support;

use App\Models\BusinessCategory;

class BusinessCategoryFormatter
{
    public static function toArray(?BusinessCategory $category): ?array
    {
        if ($category === null) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'type' => $category->type,
            'icon' => $category->icon,
        ];
    }
}
