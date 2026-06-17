<?php

namespace App\DAO;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductInterface
{
    public function findStoreByOwnerId(int $userId): ?Store;

    public function paginateProductsForStore(int $storeId, int $perPage): LengthAwarePaginator;

    public function listAllProductsForStore(int $storeId): \Illuminate\Support\Collection;

    public function countActiveProductsForStore(int $storeId): int;

    public function findStoreSpaceForStore(int $storeId): ?int;

    public function findProductForStore(int $productId, int $storeId, ?int $reporterUserId = null): ?Product;

    public function findVariantForStore(int $variantId, int $storeId): ?ProductVariant;

    public function createProduct(int $storeId, array $productData, array $variantsData, array $categoryIds): Product;

    public function updateProduct(Product $product, array $productData, ?array $categoryIds): Product;

    public function deleteProduct(Product $product): bool;

    public function createVariant(Product $product, int $storeId, array $data): ProductVariant;

    public function updateVariant(ProductVariant $variant, array $data): ProductVariant;

    public function deleteVariant(ProductVariant $variant): bool;

    public function skuExistsInStore(int $storeId, string $sku, ?int $excludeVariantId = null): bool;

    public function slugExistsInStore(int $storeId, string $slug, ?int $excludeProductId = null): bool;
}
