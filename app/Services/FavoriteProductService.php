<?php

namespace App\Services;

use App\DAO\ProductInterface;
use App\Models\FavoriteProduct;
use App\Models\Product;
use App\Models\ProductVariant;

class FavoriteProductService
{
    public function __construct(
        protected ProductInterface $productClass,
    ) {}

    public function listForUser(int $userId, int $perPage): array
    {
        $favorites = FavoriteProduct::query()
            ->where('userID', $userId)
            ->whereHas('product', function ($query) {
                $query->where('status', 'active')
                    ->whereHas('store', fn ($storeQuery) => $storeQuery->visibleToCustomers());
            })
            ->with([
                'product.media',
                'product.categories:id,name,slug',
                'product.variants' => fn ($query) => $query
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
            ->latest()
            ->paginate($perPage)
            ->through(fn (FavoriteProduct $favorite) => [
                'id' => $favorite->id,
                'productId' => $favorite->productID,
                'favoritedAt' => $favorite->created_at?->toIso8601String(),
                'product' => $this->toProductSummary($favorite->product),
            ]);

        return [
            'success' => true,
            'favorites' => $favorites,
        ];
    }

    public function add(int $userId, int $productId): array
    {
        if ($this->productClass->findVisibleProductForCustomer($productId) === null) {
            return $this->fail('Product not found.', 404);
        }

        $favorite = FavoriteProduct::query()->firstOrCreate([
            'userID' => $userId,
            'productID' => $productId,
        ]);

        return [
            'success' => true,
            'message' => 'Product added to favorites.',
            'favorite' => [
                'id' => $favorite->id,
                'productId' => $favorite->productID,
            ],
            'http_status' => $favorite->wasRecentlyCreated ? 201 : 200,
        ];
    }

    public function remove(int $userId, int $productId): array
    {
        $deleted = FavoriteProduct::query()
            ->where('userID', $userId)
            ->where('productID', $productId)
            ->delete();

        if ($deleted === 0) {
            return $this->fail('Favorite not found.', 404);
        }

        return [
            'success' => true,
            'message' => 'Product removed from favorites.',
        ];
    }

    private function toProductSummary(Product $product): array
    {
        $variants = $product->relationLoaded('variants') ? $product->variants : collect();
        $prices = $variants->pluck('price')->filter(fn ($price) => $price !== null);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'shortDetail' => $product->shortDetail,
            'isFeatured' => (bool) $product->isFeatured,
            'media' => $product->relationLoaded('media')
                ? $product->media->map(fn ($media) => [
                    'id' => $media->id,
                    'url' => $media->url,
                    'fileType' => $media->fileType,
                ])->values()->all()
                : [],
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
                    'slug' => $category->slug,
                ])->values()->all()
                : [],
            'variants' => $variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'price' => $variant->price,
                'compareAtPrice' => $variant->compareAtPrice,
                'discountPercentage' => (int) $variant->discountPercentage,
                'quantity' => $variant->quantity,
                'isDefault' => (bool) $variant->isDefault,
                'attributeName' => $variant->attributeName,
            ])->values()->all(),
        ];
    }

    private function fail(string $message, int $httpStatus): array
    {
        return [
            'success' => false,
            'message' => $message,
            'http_status' => $httpStatus,
        ];
    }
}
