<?php

namespace App\DAO;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductClass implements ProductInterface
{
    public function findStoreByOwnerId(int $userId): ?Store
    {
        return Store::query()->where('storeOwnerID', $userId)->first();
    }

    public function paginateProductsForStore(int $storeId, int $perPage): LengthAwarePaginator
    {
        return Product::query()
            ->where('storeID', $storeId)
            ->select(['id', 'name', 'status', 'publishedAt'])
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'categories',
                'variants' => fn ($q) => $q->select(['id', 'productID', 'price', 'quantity']),
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'archived' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateVisibleProductsForStore(int $storeId, int $perPage): LengthAwarePaginator
    {
        return $this->visibleProductsQuery($storeId)->paginate($perPage);
    }

    public function paginateOfferProductsForStore(int $storeId, int $perPage): LengthAwarePaginator
    {
        return $this->visibleProductsQuery($storeId)
            ->whereHas('variants', fn ($q) => $q
                ->where('status', 'active')
                ->where('discountPercentage', '>', 0))
            ->paginate($perPage);
    }

    public function paginateVisibleProductsForCategory(int $storeId, int $categoryId, int $perPage): LengthAwarePaginator
    {
        return $this->visibleProductsQuery($storeId)
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId))
            ->paginate($perPage);
    }

    public function paginateVisibleProductsForCollection(int $storeId, int $collectionId, int $perPage): LengthAwarePaginator
    {
        return $this->visibleProductsQuery($storeId)
            ->whereHas('collections', fn ($q) => $q->where('collections.id', $collectionId))
            ->paginate($perPage);
    }

    public function listAllProductsForStore(int $storeId): \Illuminate\Support\Collection
    {
        return Product::query()
            ->where('storeID', $storeId)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    public function countActiveProductsForStore(int $storeId): int
    {
        return Product::query()
            ->where('storeID', $storeId)
            ->where('status', 'active')
            ->count();
    }

    public function findStoreSpaceForStore(int $storeId): ?int
    {
        $subscription = StoreSubscription::query()
            ->with('storeSubscriptionPlan')
            ->where('storeID', $storeId)
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();

        $storeSpace = $subscription?->storeSubscriptionPlan?->storeSpace;

        return $storeSpace !== null ? (int) $storeSpace : null;
    }

    public function findProductForStore(int $productId, int $storeId, ?int $reporterUserId = null): ?Product
    {
        return Product::query()
            ->whereKey($productId)
            ->where('storeID', $storeId)
            ->with([
                'media' => fn ($q) => $q->orderBy('id'),
                'categories',
                'variants.attributeValues.attribute',
                'rates' => function ($q) use ($reporterUserId) {
                    $q->with('user.media');
                    if ($reporterUserId !== null) {
                        $q->withCount(['reports as is_reported' => function ($q) use ($reporterUserId) {
                            $q->where('reporterUserID', $reporterUserId);
                        }]);
                    }
                },
            ])
            ->first();
    }

    public function findVisibleProductForCustomer(int $productId): ?Product
    {
        return $this->visibleProductQuery()
            ->whereKey($productId)
            ->first();
    }

    public function findVisibleProductMediaForCustomer(int $productId): ?Product
    {
        return Product::query()
            ->select(['id'])
            ->whereKey($productId)
            ->where('status', 'active')
            ->whereHas('store', fn ($q) => $q->visibleToCustomers())
            ->with(['media' => fn ($q) => $q->orderBy('id')])
            ->first();
    }

    public function findVariantForStore(int $variantId, int $storeId): ?ProductVariant
    {
        return ProductVariant::query()
            ->whereKey($variantId)
            ->where('storeID', $storeId)
            ->with(['product', 'attributeValues.attribute'])
            ->first();
    }

    public function createProduct(int $storeId, array $productData, array $variantsData, array $categoryIds): Product
    {
        return DB::transaction(function () use ($storeId, $productData, $variantsData, $categoryIds) {
            $product = Product::query()->create([
                ...$productData,
                'storeID' => $storeId,
            ]);

            $this->persistVariants($product, $storeId, $variantsData);

            if ($categoryIds !== []) {
                $product->categories()->sync($categoryIds);
            }

            return $this->findProductForStore((int) $product->id, $storeId);
        });
    }

    public function updateProduct(Product $product, array $productData, ?array $categoryIds): Product
    {
        return DB::transaction(function () use ($product, $productData, $categoryIds) {
            $product->update($productData);

            if ($categoryIds !== null) {
                $product->categories()->sync($categoryIds);
            }

            return $this->findProductForStore((int) $product->id, (int) $product->storeID);
        });
    }

    public function deleteProduct(Product $product): bool
    {
        return (bool) $product->delete();
    }

    public function createVariant(Product $product, int $storeId, array $data): ProductVariant
    {
        return DB::transaction(function () use ($product, $storeId, $data) {
            $attributeValueIds = $data['attributeValueIds'] ?? [];
            unset($data['attributeValueIds']);

            if (! empty($data['isDefault'])) {
                ProductVariant::query()
                    ->where('productID', $product->id)
                    ->update(['isDefault' => false]);
            }

            $this->applyDiscountLogic($data);

            if (! isset($data['attributeName'])) {
                $data['attributeName'] = $this->generateAttributeName($attributeValueIds);
            }

            /** @var ProductVariant $variant */
            $variant = $product->variants()->create([
                ...$data,
                'storeID' => $storeId,
            ]);

            if ($attributeValueIds !== []) {
                $variant->attributeValues()->sync($attributeValueIds);
            }

            return $variant->fresh(['attributeValues.attribute']);
        });
    }

    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data) {
            $attributeValueIds = $data['attributeValueIds'] ?? null;
            unset($data['attributeValueIds']);

            if (! empty($data['isDefault'])) {
                ProductVariant::query()
                    ->where('productID', $variant->productID)
                    ->where('id', '!=', $variant->id)
                    ->update(['isDefault' => false]);
            }

            if ($attributeValueIds !== null && ! isset($data['attributeName'])) {
                $data['attributeName'] = $this->generateAttributeName($attributeValueIds);
            }

            $this->applyDiscountLogic($data, $variant);

            $variant->update($data);

            if ($attributeValueIds !== null) {
                $variant->attributeValues()->sync($attributeValueIds);
            }

            return $variant->fresh(['attributeValues.attribute', 'product', 'media']);
        });
    }

    public function deleteVariant(ProductVariant $variant): bool
    {
        return DB::transaction(function () use ($variant) {
            $wasDefault = (bool) $variant->isDefault;
            $productId = (int) $variant->productID;

            $deleted = (bool) $variant->delete();

            if ($deleted && $wasDefault) {
                $next = ProductVariant::query()
                    ->where('productID', $productId)
                    ->orderBy('id')
                    ->first();

                if ($next !== null) {
                    $next->update(['isDefault' => true]);
                }
            }

            return $deleted;
        });
    }

    private function applyDiscountLogic(array &$data, ?ProductVariant $existing = null): void
    {
        $discount = isset($data['discountPercentage']) ? (int) $data['discountPercentage'] : ($existing ? (int) $existing->discountPercentage : 0);

        if ($discount > 0) {
            $basePrice = isset($data['price']) ? (int) $data['price'] : ($existing ? ($existing->compareAtPrice ?: $existing->price) : 0);

            if ($basePrice > 0) {
                $data['compareAtPrice'] = $basePrice;
                $data['price'] = (int) round($basePrice * (1 - $discount / 100));
                $data['discountPercentage'] = $discount;
            }
        } elseif (isset($data['discountPercentage']) && (int) $data['discountPercentage'] === 0) {
            if ($existing && $existing->compareAtPrice) {
                $data['price'] = $existing->compareAtPrice;
                $data['compareAtPrice'] = null;
            }
            $data['discountPercentage'] = 0;
        }
    }

    private function generateAttributeName(array $attributeValueIds): ?string
    {
        if (empty($attributeValueIds)) {
            return null;
        }

        return DB::table('attribute_values')
            ->whereIn('id', $attributeValueIds)
            ->orderBy('id')
            ->pluck('value')
            ->filter(fn ($val) => $val !== null && $val !== '')
            ->implode(' / ');
    }

    public function skuExistsInStore(int $storeId, string $sku, ?int $excludeVariantId = null): bool
    {
        $query = ProductVariant::query()
            ->where('storeID', $storeId)
            ->where('sku', $sku);

        if ($excludeVariantId !== null) {
            $query->where('id', '!=', $excludeVariantId);
        }

        return $query->exists();
    }

    public function slugExistsInStore(int $storeId, string $slug, ?int $excludeProductId = null): bool
    {
        $query = Product::query()
            ->where('storeID', $storeId)
            ->where('slug', $slug);

        if ($excludeProductId !== null) {
            $query->where('id', '!=', $excludeProductId);
        }

        return $query->exists();
    }

    private function visibleProductsQuery(int $storeId)
    {
        return $this->visibleProductQuery()
            ->where('storeID', $storeId)
            ->orderByDesc('isFeatured')
            ->orderByDesc('publishedAt')
            ->orderBy('name');
    }

    private function visibleProductQuery()
    {
        return Product::query()
            ->where('status', 'active')
            ->whereHas('store', fn ($q) => $q->visibleToCustomers())
            ->with([
                'store:id,name',
                'media' => fn ($q) => $q->orderBy('id'),
                'categories:id,name,slug',
                'variants' => fn ($q) => $q
                    ->select([
                        'id',
                        'productID',
                        'price',
                        'compareAtPrice',
                        'discountPercentage',
                        'quantity',
                        'attributeName',
                        'isDefault',
                        'status',
                    ])
                    ->where('status', 'active')
                    ->orderByDesc('isDefault'),
            ])
            ->withCount('rates')
            ->withAvg('rates', 'score');
    }

    /** @param  array<int, array<string, mixed>>  $variantsData */
    private function persistVariants(Product $product, int $storeId, array $variantsData): void
    {
        $defaultAssigned = false;

        foreach ($variantsData as $index => $variantData) {
            $attributeValueIds = $variantData['attributeValueIds'] ?? [];
            unset($variantData['attributeValueIds']);

            $isDefault = ! empty($variantData['isDefault']) || ($index === 0 && ! $defaultAssigned);
            if ($isDefault) {
                $defaultAssigned = true;
            }

            $this->applyDiscountLogic($variantData);

            if (! isset($variantData['attributeName'])) {
                $variantData['attributeName'] = $this->generateAttributeName($attributeValueIds);
            }

            /** @var ProductVariant $variant */
            $variant = $product->variants()->create([
                ...$variantData,
                'storeID' => $storeId,
                'isDefault' => $isDefault,
            ]);

            if ($attributeValueIds !== []) {
                $variant->attributeValues()->sync($attributeValueIds);
            }
        }
    }
}
