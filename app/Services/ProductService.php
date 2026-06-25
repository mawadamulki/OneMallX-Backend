<?php

namespace App\Services;

use App\DAO\CategoryInterface;
use App\DAO\ProductAttributeInterface;
use App\DAO\ProductInterface;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        protected ProductInterface $productClass,
        protected CategoryInterface $categoryClass,
        protected ProductAttributeInterface $productAttributeClass,
    ) {}

    public function listForOwner(int $userId, int $perPage): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $paginator = $this->productClass
            ->paginateProductsForStore((int) $store->id, $perPage)
            ->through(fn (Product $product) => $this->toSummaryArray($product));

        return [
            'success' => true,
            'store' => $this->toStoreRefArray($store),
            'activeProductCount' => $this->productClass->countActiveProductsForStore((int) $store->id),
            'storeSpace' => $this->productClass->findStoreSpaceForStore((int) $store->id),
            'products' => $paginator,
        ];
    }

    public function showForOwner(int $userId, int $productId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $product = $this->productClass->findProductForStore($productId, (int) $store->id, $userId);

        if ($product === null) {
            return $this->fail('Product not found.', 404);
        }

        return [
            'success' => true,
            'product' => $this->toDetailArray($product),
        ];
    }

    public function createForOwner(int $userId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $variantsData = $this->normalizeVariantsInput($payload);
        if ($variantsData === []) {
            return $this->fail('At least one variant is required.', 422);
        }

        $categoryIds = array_values(array_unique(array_map('intval', $payload['categoryIds'] ?? [])));

        if (! $this->categoryClass->allBelongToStore((int) $store->id, $categoryIds)) {
            return $this->fail('One or more categories do not belong to your store.', 422);
        }

        $skuError = $this->validateSkusForStore((int) $store->id, $variantsData);
        if ($skuError !== null) {
            return $this->fail($skuError, 422);
        }

        $attributeError = $this->validateAttributeValuesForStore((int) $store->id, $variantsData);
        if ($attributeError !== null) {
            return $this->fail($attributeError, 422);
        }

        $slug = $this->resolveSlug((int) $store->id, (string) $payload['name'], $payload['slug'] ?? null);

        $productData = [
            'name' => $payload['name'],
            'slug' => $slug,
            'detail' => $payload['detail'] ?? null,
            'shortDetail' => $payload['shortDetail'] ?? null,
            'status' => $payload['status'],
            'isFeatured' => (bool) ($payload['isFeatured'] ?? false),
            'publishedAt' => ($payload['status'] === 'active') ? now() : ($payload['publishedAt'] ?? null),
        ];

        $preparedVariants = $this->prepareVariantsData($variantsData);

        $product = $this->productClass->createProduct(
            (int) $store->id,
            $productData,
            $preparedVariants,
            $categoryIds
        );

        return [
            'success' => true,
            'message' => 'Product created.',
            'product' => $this->toFullArray($product),
            'http_status' => 201,
        ];
    }

    public function updateForOwner(int $userId, int $productId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $product = $this->productClass->findProductForStore($productId, (int) $store->id);

        if ($product === null) {
            return $this->fail('Product not found.', 404);
        }

        $productData = [];

        if (array_key_exists('name', $payload)) {
            $productData['name'] = $payload['name'];
        }

        if (array_key_exists('slug', $payload) || array_key_exists('name', $payload)) {
            $name = $payload['name'] ?? $product->name;
            $productData['slug'] = $this->resolveSlug(
                (int) $store->id,
                $name,
                $payload['slug'] ?? null,
                (int) $product->id
            );
        }

        foreach (['detail', 'shortDetail', 'status', 'isFeatured', 'publishedAt'] as $field) {
            if (array_key_exists($field, $payload)) {
                $productData[$field] = $payload[$field];
            }
        }

        if (isset($payload['status']) && $payload['status'] === 'active' && $product->publishedAt === null) {
            $productData['publishedAt'] = now();
        }

        $categoryIds = null;
        if (array_key_exists('categoryIds', $payload)) {
            $categoryIds = array_values(array_unique(array_map('intval', $payload['categoryIds'] ?? [])));

            if (! $this->categoryClass->allBelongToStore((int) $store->id, $categoryIds)) {
                return $this->fail('One or more categories do not belong to your store.', 422);
            }
        }

        $updated = $this->productClass->updateProduct($product, $productData, $categoryIds);

        return [
            'success' => true,
            'message' => 'Product updated.',
            'product' => $this->toFullArray($updated),
        ];
    }

    public function deleteForOwner(int $userId, int $productId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $product = $this->productClass->findProductForStore($productId, (int) $store->id);

        if ($product === null) {
            return $this->fail('Product not found.', 404);
        }

        $this->productClass->deleteProduct($product);

        return [
            'success' => true,
            'message' => 'Product deleted.',
            'deleted' => true,
        ];
    }

    public function createVariantForOwner(int $userId, int $productId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $product = $this->productClass->findProductForStore($productId, (int) $store->id);

        if ($product === null) {
            return $this->fail('Product not found.', 404);
        }

        $sku = $this->normalizeSku((string) $payload['sku']);

        if ($this->productClass->skuExistsInStore((int) $store->id, $sku)) {
            return $this->fail("SKU \"{$sku}\" already exists in your store.", 422);
        }

        $attributeValueIds = array_values(array_unique(array_map('intval', $payload['attributeValueIds'] ?? [])));

        if (! $this->productAttributeClass->allValuesBelongToStore((int) $store->id, $attributeValueIds)) {
            return $this->fail('One or more attribute values are invalid for your store.', 422);
        }

        $variant = $this->productClass->createVariant($product, (int) $store->id, [
            ...$this->prepareSingleVariantData($payload, $sku),
            'attributeValueIds' => $attributeValueIds,
        ]);

        return [
            'success' => true,
            'message' => 'Variant created.',
            'variant' => $this->toVariantArray($variant),
            'http_status' => 201,
        ];
    }

    public function updateVariantForOwner(int $userId, int $variantId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $variant = $this->productClass->findVariantForStore($variantId, (int) $store->id);

        if ($variant === null) {
            return $this->fail('Variant not found.', 404);
        }

        $data = [];

        if (array_key_exists('sku', $payload)) {
            $sku = $this->normalizeSku((string) $payload['sku']);

            if ($this->productClass->skuExistsInStore((int) $store->id, $sku, (int) $variant->id)) {
                return $this->fail("SKU \"{$sku}\" already exists in your store.", 422);
            }

            $data['sku'] = $sku;
        }

        foreach (['barcode', 'name', 'price', 'compareAtPrice', 'discountPercentage', 'costPrice', 'quantity', 'weight', 'isDefault', 'status', 'attributeName'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if (array_key_exists('attributeValueIds', $payload)) {
            $attributeValueIds = array_values(array_unique(array_map('intval', $payload['attributeValueIds'] ?? [])));

            if (! $this->productAttributeClass->allValuesBelongToStore((int) $store->id, $attributeValueIds)) {
                return $this->fail('One or more attribute values are invalid for your store.', 422);
            }

            $data['attributeValueIds'] = $attributeValueIds;
        }

        $updated = $this->productClass->updateVariant($variant, $data);

        return [
            'success' => true,
            'message' => 'Variant updated.',
            'variant' => $this->toVariantArray($updated),
        ];
    }

    public function deleteVariantForOwner(int $userId, int $variantId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $variant = $this->productClass->findVariantForStore($variantId, (int) $store->id);

        if ($variant === null) {
            return $this->fail('Variant not found.', 404);
        }

        $remaining = ProductVariant::query()
            ->where('productID', $variant->productID)
            ->where('id', '!=', $variant->id)
            ->count();

        if ($remaining === 0) {
            return $this->fail('Cannot delete the last variant of a product.', 422);
        }

        $this->productClass->deleteVariant($variant);

        return [
            'success' => true,
            'message' => 'Variant deleted.',
            'deleted' => true,
        ];
    }

    public function attachMediaForOwner(int $userId, int $productId, UploadedFile $file): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $product = $this->productClass->findProductForStore($productId, (int) $store->id);

        if ($product === null) {
            return $this->fail('Product not found.', 404);
        }

        $path = $file->store("products/{$product->id}", 'public');

        $media = $product->media()->create([
            'fileType' => $file->getClientMimeType(),
            'url' => $path,
        ]);

        return [
            'success' => true,
            'message' => 'Photo uploaded.',
            'media' => $this->mapMedia($media),
            'http_status' => 201,
        ];
    }

    public function deleteMediaForOwner(int $userId, int $mediaId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $media = Media::query()
            ->whereKey($mediaId)
            ->where('mediableType', Product::class)
            ->first();

        if ($media === null) {
            return $this->fail('Media not found.', 404);
        }

        $product = Product::query()
            ->whereKey($media->mediableID)
            ->where('storeID', $store->id)
            ->first();

        if ($product === null) {
            return $this->fail('Media not found.', 404);
        }

        $relative = $media->publicDiskRelativePath();
        if ($relative !== null) {
            Storage::disk('public')->delete($relative);
        }

        $media->forceDelete();

        return [
            'success' => true,
            'message' => 'Media deleted.',
            'deleted' => true,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeVariantsInput(array $payload): array
    {
        if (isset($payload['variant']) && is_array($payload['variant'])) {
            return [$payload['variant']];
        }

        if (isset($payload['variants']) && is_array($payload['variants'])) {
            return $payload['variants'];
        }

        return [];
    }

    /** @param  array<int, array<string, mixed>>  $variantsData */
    private function validateSkusForStore(int $storeId, array $variantsData): ?string
    {
        $seen = [];

        foreach ($variantsData as $variantData) {
            $sku = $this->normalizeSku((string) ($variantData['sku'] ?? ''));

            if ($sku === '') {
                return 'Each variant must have a SKU.';
            }

            if (isset($seen[$sku])) {
                return "Duplicate SKU \"{$sku}\" in request.";
            }

            $seen[$sku] = true;

            if ($this->productClass->skuExistsInStore($storeId, $sku)) {
                return "SKU \"{$sku}\" already exists in your store.";
            }
        }

        return null;
    }

    /** @param  array<int, array<string, mixed>>  $variantsData */
    private function validateAttributeValuesForStore(int $storeId, array $variantsData): ?string
    {
        foreach ($variantsData as $variantData) {
            $ids = array_values(array_unique(array_map('intval', $variantData['attributeValueIds'] ?? [])));

            if (! $this->productAttributeClass->allValuesBelongToStore($storeId, $ids)) {
                return 'One or more attribute values are invalid for your store.';
            }
        }

        return null;
    }

    /** @param  array<int, array<string, mixed>>  $variantsData */
    private function prepareVariantsData(array $variantsData): array
    {
        return array_map(function (array $variantData) {
            $sku = $this->normalizeSku((string) $variantData['sku']);

            return $this->prepareSingleVariantData($variantData, $sku);
        }, $variantsData);
    }

    private function prepareSingleVariantData(array $data, string $sku): array
    {
        return [
            'sku' => $sku,
            'barcode' => $data['barcode'] ?? null,
            'name' => $data['name'] ?? null,
            'price' => (int) $data['price'],
            'compareAtPrice' => isset($data['compareAtPrice']) ? (int) $data['compareAtPrice'] : null,
            'costPrice' => isset($data['costPrice']) ? (int) $data['costPrice'] : null,
            'quantity' => (int) ($data['quantity'] ?? 0),
            'discountPercentage' => isset($data['discountPercentage']) ? (int) $data['discountPercentage'] : 0,
            'weight' => isset($data['weight']) ? (int) $data['weight'] : null,
            'isDefault' => (bool) ($data['isDefault'] ?? false),
            'status' => $data['status'] ?? 'active',
            'attributeName' => $data['attributeName'] ?? null,
            'attributeValueIds' => array_values(array_unique(array_map('intval', $data['attributeValueIds'] ?? []))),
        ];
    }

    private function resolveSlug(int $storeId, string $name, ?string $requestedSlug, ?int $excludeProductId = null): string
    {
        $base = Str::slug($requestedSlug ?: $name) ?: 'product';

        $slug = $base;
        $suffix = 1;

        while ($this->productClass->slugExistsInStore($storeId, $slug, $excludeProductId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function normalizeSku(string $sku): string
    {
        return strtoupper(trim($sku));
    }

    private function toStoreRefArray(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
        ];
    }

    private function toSummaryArray(Product $product): array
    {
        $variants = $product->relationLoaded('variants') ? $product->variants : collect();
        $prices = $variants->pluck('price')->filter(fn ($price) => $price !== null);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'status' => $product->status,
            'publishedAt' => $product->publishedAt,
            'media' => $this->mapMediaCollection($product),
            'priceRange' => $prices->isEmpty()
                ? null
                : [
                    'min' => (int) $prices->min(),
                    'max' => (int) $prices->max(),
                ],
            'totalQuantity' => (int) $variants->sum('quantity'),
            'categories' => $product->relationLoaded('categories')
                ? $product->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])->values()->all()
                : [],
            'variants' => $variants->map(fn ($v) => [
                'id' => $v->id,
                'price' => $v->price,
                'quantity' => $v->quantity,
            'attributeName' => $v->attributeName ?: $this->formatVariantAttributeString($v),
        ])->values()->all(),
        ];
    }

    private function toDetailArray(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'detail' => $product->detail,
            'media' => $this->mapMediaCollection($product),
            'categories' => $product->relationLoaded('categories')
                ? $product->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])->values()->all()
                : [],
            'variants' => $product->relationLoaded('variants')
                ? $product->variants->map(fn (ProductVariant $variant) => $this->toVariantDetailArray($variant))->values()->all()
                : [],
        ];
    }

    private function toFullArray(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'detail' => $product->detail,
            'shortDetail' => $product->shortDetail,
            'status' => $product->status,
            'isFeatured' => $product->isFeatured,
            'publishedAt' => $product->publishedAt,
            'storeID' => $product->storeID,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'media' => $this->mapMediaCollection($product),
            'categories' => $product->relationLoaded('categories')
                ? $product->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->values()->all()
                : [],
            'variants' => $product->relationLoaded('variants')
                ? $product->variants->map(fn (ProductVariant $variant) => $this->toVariantArray($variant))->values()->all()
                : [],
        ];
    }

    private function toVariantArray(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'name' => $variant->name,
            'price' => $variant->price,
            'compareAtPrice' => $variant->compareAtPrice,
            'discountPercentage' => $variant->discountPercentage,
            'costPrice' => $variant->costPrice,
            'quantity' => $variant->quantity,
            'reservedQuantity' => $variant->reservedQuantity,
            'availableQuantity' => $variant->availableQuantity(),
            'weight' => $variant->weight,
            'attributeName' => $variant->attributeName ?: $this->formatVariantAttributeString($variant),
            'isDefault' => $variant->isDefault,
            'status' => $variant->status,
            'attributes' => $variant->relationLoaded('attributeValues')
                ? $variant->attributeValues->map(fn ($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'attribute' => $value->relationLoaded('attribute') && $value->attribute
                        ? [
                            'id' => $value->attribute->id,
                            'name' => $value->attribute->name,
                            'code' => $value->attribute->code,
                        ]
                        : null,
                ])->values()->all()
                : [],
        ];
    }

    private function toVariantDetailArray(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => $variant->price,
            'compareAtPrice' => $variant->compareAtPrice,
            'discountPercentage' => $variant->discountPercentage,
            'quantity' => $variant->quantity,
            'isDefault' => $variant->isDefault,
            'status' => $variant->status,
            'attributeName' => $variant->attributeName ?: $this->formatVariantAttributeString($variant),
        ];
    }

    private function formatVariantAttributeString(ProductVariant $variant): string
    {
        if (! $variant->relationLoaded('attributeValues')) {
            return '';
        }

        return $variant->attributeValues
            ->map(fn ($value) => (string) $value->value)
            ->filter(fn (string $part) => $part !== '')
            ->implode(' / ');
    }

    private function mapMediaCollection(Product $product): array
    {
        if (! $product->relationLoaded('media')) {
            return [];
        }

        return $product->media->map(fn ($media) => $this->mapMedia($media))->values()->all();
    }

    private function mapMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'fileType' => $media->fileType,
        ];
    }

    /** @return array{success: false, message: string, http_status: int} */
    private function fail(string $message, int $httpStatus): array
    {
        return [
            'success' => false,
            'message' => $message,
            'http_status' => $httpStatus,
        ];
    }
}
