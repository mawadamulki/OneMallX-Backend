<?php

namespace Database\Seeders;

use App\Models\BusinessCategory;
use Illuminate\Database\Seeder;

class BusinessCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Retail', 'slug' => 'retail', 'type' => 'store', 'sortOrder' => 10],
            ['name' => 'Fashion', 'slug' => 'fashion', 'type' => 'store', 'sortOrder' => 20],
            ['name' => 'Food & Beverage', 'slug' => 'food_beverage', 'type' => 'store', 'sortOrder' => 30],
            ['name' => 'Electronics', 'slug' => 'electronics', 'type' => 'store', 'sortOrder' => 40],
            ['name' => 'Beauty', 'slug' => 'beauty', 'type' => 'store', 'sortOrder' => 50],
            ['name' => 'Sports', 'slug' => 'sports', 'type' => 'store', 'sortOrder' => 60],
            ['name' => 'Books', 'slug' => 'books', 'type' => 'store', 'sortOrder' => 70],
            ['name' => 'Home & Living', 'slug' => 'home_living', 'type' => 'store', 'sortOrder' => 80],
            ['name' => 'Common', 'slug' => 'common', 'type' => 'service', 'sortOrder' => 10],
            ['name' => 'Customer Service', 'slug' => 'customer_service', 'type' => 'service', 'sortOrder' => 20],
            ['name' => 'Entertainment', 'slug' => 'entertainment', 'type' => 'service', 'sortOrder' => 30],
            ['name' => 'Family', 'slug' => 'family', 'type' => 'service', 'sortOrder' => 40],
            ['name' => 'Health', 'slug' => 'health', 'type' => 'service', 'sortOrder' => 50],
            ['name' => 'Beauty & Wellness', 'slug' => 'beauty_wellness', 'type' => 'service', 'sortOrder' => 60],
        ];

        foreach ($categories as $category) {
            BusinessCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    ...$category,
                    'isActive' => true,
                ]
            );
        }
    }
}
