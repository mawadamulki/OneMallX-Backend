<?php

namespace App\Services;

use App\DAO\CollectionInterface;
use App\DAO\ProductInterface;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CollectionService
{
    public function __construct(
        protected CollectionInterface $collectionClass,
        protected ProductInterface $productClass,
    ) {}

    public function listForOwner(int $userId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $collections = $this->collectionClass
            ->listForStore((int) $store->id)
            ->map(fn (ProductCollection $collection) => $this->toSummaryArray($collection));

        return [
            'success' => true,
            'collections' => $collections->values()->all(),
        ];
    }

    public function showForOwner(int $userId, int $collectionId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $collection = $this->collectionClass->findForStore($collectionId, (int) $store->id);

        if ($collection === null) {
            return $this->fail('Collection not found.', 404);
        }

        return [
            'success' => true,
            'collection' => $this->toDetailArray($collection),
        ];
    }

    public function createForOwner(int $userId, array $payload, UploadedFile $image): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $productIds = $this->normalizeProductIds($payload['productIds'] ?? []);

        if (! $this->collectionClass->allProductsBelongToStore((int) $store->id, $productIds)) {
            return $this->fail('One or more products do not belong to your store.', 422);
        }

        $path = $image->store("collections/stores/{$store->id}", 'public');

        $collection = $this->collectionClass->createForStore((int) $store->id, [
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'image' => $path,
        ]);

        if ($productIds !== []) {
            $this->collectionClass->syncProducts($collection, $productIds);
            $collection->unsetRelation('products');
        }

        $collection = $this->collectionClass->findForStore((int) $collection->id, (int) $store->id);

        return [
            'success' => true,
            'message' => 'Collection created.',
            'collection' => $this->toDetailArray($collection),
            'http_status' => 201,
        ];
    }

    public function updateForOwner(int $userId, int $collectionId, array $payload, ?UploadedFile $image): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $collection = $this->collectionClass->findForStore($collectionId, (int) $store->id);

        if ($collection === null) {
            return $this->fail('Collection not found.', 404);
        }

        if (array_key_exists('productIds', $payload)) {
            $productIds = $this->normalizeProductIds($payload['productIds']);

            if (! $this->collectionClass->allProductsBelongToStore((int) $store->id, $productIds)) {
                return $this->fail('One or more products do not belong to your store.', 422);
            }

            $this->collectionClass->syncProducts($collection, $productIds);
            $collection->unsetRelation('products');
        }

        $data = [];

        if (array_key_exists('name', $payload)) {
            $data['name'] = $payload['name'];
        }

        if (array_key_exists('description', $payload)) {
            $data['description'] = $payload['description'];
        }

        if ($image !== null) {
            if ($collection->image) {
                Storage::disk('public')->delete($collection->image);
            }

            $data['image'] = $image->store("collections/stores/{$store->id}", 'public');
        }

        if ($data !== []) {
            $this->collectionClass->update($collection, $data);
        }

        $collection = $this->collectionClass->findForStore($collectionId, (int) $store->id);

        return [
            'success' => true,
            'message' => 'Collection updated.',
            'collection' => $this->toDetailArray($collection),
        ];
    }

    public function deleteForOwner(int $userId, int $collectionId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $collection = $this->collectionClass->findForStore($collectionId, (int) $store->id);

        if ($collection === null) {
            return $this->fail('Collection not found.', 404);
        }

        if ($collection->image) {
            Storage::disk('public')->delete($collection->image);
        }

        $this->collectionClass->delete($collection);

        return [
            'success' => true,
            'message' => 'Collection deleted.',
            'deleted' => true,
        ];
    }

    private function toSummaryArray(ProductCollection $collection): array
    {
        return [
            'id' => $collection->id,
            'name' => $collection->name,
            'description' => $collection->description,
            'image' => $this->resolvePublicUrl($collection->image),
            'productsCount' => (int) ($collection->products_count ?? 0),
        ];
    }

    private function toDetailArray(ProductCollection $collection): array
    {
        return [
            'id' => $collection->id,
            'name' => $collection->name,
            'description' => $collection->description,
            'image' => $this->resolvePublicUrl($collection->image),
            'productsCount' => (int) ($collection->products_count ?? $collection->products->count()),
            'products' => $collection->relationLoaded('products')
                ? $collection->products->map(fn (Product $product) => $this->toProductSummary($product))->values()->all()
                : [],
        ];
    }

    private function toProductSummary(Product $product): array
    {
        $variants = $product->relationLoaded('variants') ? $product->variants : collect();
        $prices = $variants->pluck('price')->filter(fn ($price) => $price !== null);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'status' => $product->status,
            'media' => $product->relationLoaded('media')
                ? $product->media->pluck('url')->filter()->values()->all()
                : [],
            'priceRange' => $prices->isEmpty()
                ? null
                : [
                    'min' => (int) $prices->min(),
                    'max' => (int) $prices->max(),
                ],
        ];
    }

    /** @return list<int> */
    private function normalizeProductIds(array $productIds): array
    {
        return array_values(array_unique(array_map('intval', $productIds)));
    }

    private function resolvePublicUrl(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        return (new Media(['url' => $stored]))->url;
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
