<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('productID');
            $table->unsignedBigInteger('storeID');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('name')->nullable();
            $table->integer('price');
            $table->integer('compareAtPrice')->nullable();
            $table->integer('costPrice')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('reservedQuantity')->default(0);
            $table->unsignedInteger('weight')->nullable();
            $table->boolean('isDefault')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('productID')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('storeID')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();

            $table->unique(['storeID', 'sku']);
            $table->index(['productID', 'isDefault']);
            $table->index(['storeID', 'status']);
        });

        $now = now();

        DB::table('products')->orderBy('id')->chunkById(100, function ($products) use ($now): void {
            foreach ($products as $product) {
                $sku = strtoupper(trim((string) $product->name));
                if ($sku === '') {
                    $sku = 'SKU-'.$product->id;
                }

                $sku = $this->uniqueSku((int) $product->storeID, $sku, (int) $product->id);

                DB::table('product_variants')->insert([
                    'productID' => $product->id,
                    'storeID' => $product->storeID,
                    'sku' => $sku,
                    'name' => null,
                    'price' => $product->price,
                    'quantity' => $product->quantity,
                    'isDefault' => true,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('price')->default(0);
            $table->integer('quantity')->default(0);
        });

        $variants = DB::table('product_variants')
            ->where('isDefault', true)
            ->orderBy('id')
            ->get();

        foreach ($variants as $variant) {
            DB::table('products')
                ->where('id', $variant->productID)
                ->update([
                    'price' => $variant->price,
                    'quantity' => $variant->quantity,
                ]);
        }

        Schema::dropIfExists('product_variants');
    }

    private function uniqueSku(int $storeId, string $sku, int $productId): string
    {
        $candidate = $sku;
        $suffix = 1;

        while (
            DB::table('product_variants')
                ->where('storeID', $storeId)
                ->where('sku', $candidate)
                ->exists()
        ) {
            $candidate = $sku.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
};
