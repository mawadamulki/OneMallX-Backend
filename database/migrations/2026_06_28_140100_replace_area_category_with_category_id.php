<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $seedCategories = [
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

    public function up(): void
    {
        $now = now();

        foreach ($this->seedCategories as $category) {
            DB::table('business_categories')->insert([
                ...$category,
                'isActive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $slugToId = DB::table('business_categories')
            ->pluck('id', 'slug')
            ->all();

        Schema::table('areas', function (Blueprint $table) {
            $table->unsignedBigInteger('categoryID')->nullable()->after('usageType');
        });

        foreach (DB::table('areas')->select(['id', 'category'])->get() as $area) {
            $categoryId = $slugToId[(string) $area->category] ?? null;

            if ($categoryId === null) {
                $fallbackType = DB::table('areas')->where('id', $area->id)->value('usageType');
                $fallbackSlug = $fallbackType === 'service' ? 'common' : 'retail';
                $categoryId = $slugToId[$fallbackSlug];
            }

            DB::table('areas')
                ->where('id', $area->id)
                ->update(['categoryID' => $categoryId]);
        }

        Schema::table('areas', function (Blueprint $table) {
            $table->foreign('categoryID')
                ->references('id')
                ->on('business_categories')
                ->restrictOnDelete();

            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->string('category')->nullable()->after('usageType');
        });

        $idToSlug = DB::table('business_categories')
            ->pluck('slug', 'id')
            ->all();

        foreach (DB::table('areas')->select(['id', 'categoryID'])->get() as $area) {
            DB::table('areas')
                ->where('id', $area->id)
                ->update(['category' => $idToSlug[(int) $area->categoryID] ?? 'retail']);
        }

        Schema::table('areas', function (Blueprint $table) {
            $table->dropForeign(['categoryID']);
            $table->dropColumn('categoryID');
        });
    }
};
