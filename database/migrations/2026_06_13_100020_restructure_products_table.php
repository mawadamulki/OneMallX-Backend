<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->text('shortDetail')->nullable()->after('detail');
            $table->string('status')->default('active')->after('storeID');
            $table->boolean('isFeatured')->default(false)->after('status');
            $table->timestamp('publishedAt')->nullable()->after('isFeatured');
        });

        DB::table('products')->orderBy('id')->chunkById(100, function ($products): void {
            foreach ($products as $product) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'slug' => $this->uniqueProductSlug((int) $product->storeID, (string) $product->name, (int) $product->id),
                        'status' => 'active',
                    ]);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['storeID', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['storeID', 'slug']);
            $table->dropColumn([
                'slug',
                'shortDetail',
                'status',
                'isFeatured',
                'publishedAt',
            ]);
        });
    }

    private function uniqueProductSlug(int $storeId, string $name, int $productId): string
    {
        $base = Str::slug($name) ?: 'product-'.$productId;
        $slug = $base;
        $suffix = 1;

        while (
            DB::table('products')
                ->where('storeID', $storeId)
                ->where('slug', $slug)
                ->where('id', '!=', $productId)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
};
