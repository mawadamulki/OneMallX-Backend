<?php

namespace App\Services;

use App\DAO\SearchInterface;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Service;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchService
{
    public function __construct(
        protected SearchInterface $searchClass,
    ) {}

    public function search(string $query, string $type, int $perPage, ?int $storeId = null): array
    {
        $type = in_array($type, ['all', 'stores', 'products', 'services'], true) ? $type : 'all';

        $stores = null;
        $products = null;
        $services = null;

        if ($type === 'all' || $type === 'stores') {
            $stores = $this->searchClass
                ->searchStores($query, $perPage)
                ->through(fn (Store $store) => $this->formatStore($store));
        }

        if ($type === 'all' || $type === 'products') {
            $products = $this->searchClass
                ->searchProducts($query, $perPage, $storeId)
                ->through(fn (Product $product) => $this->formatProduct($product));
        }

        if ($type === 'all' || $type === 'services') {
            $services = $this->searchClass
                ->searchServices($query, $perPage)
                ->through(fn (Service $service) => $this->formatService($service));
        }

        return [
            'query' => $query,
            'type' => $type,
            'stores' => $stores,
            'products' => $products,
            'services' => $services,
        ];
    }

    private function formatStore(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'description' => $store->description,
            'area' => $store->relationLoaded('area') && $store->area
                ? [
                    'id' => $store->area->id,
                    'name' => $store->area->name,
                    'floor' => $store->area->relationLoaded('floor') && $store->area->floor
                        ? [
                            'id' => $store->area->floor->id,
                            'name' => $store->area->floor->name,
                            'number' => $store->area->floor->number,
                        ]
                        : null,
                ]
                : null,
            'media' => $this->mapMedia($store),
            'rating' => $store->rates_avg_score !== null ? round((float) $store->rates_avg_score, 1) : null,
            'rating_count' => (int) ($store->rates_count ?? 0),
        ];
    }

    private function formatProduct(Product $product): array
    {
        $variants = $product->relationLoaded('variants') ? $product->variants : collect();
        $prices = $variants->pluck('price')->filter(fn ($price) => $price !== null);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'shortDetail' => $product->shortDetail,
            'isFeatured' => (bool) $product->isFeatured,
            'store' => $product->relationLoaded('store') && $product->store
                ? [
                    'id' => $product->store->id,
                    'name' => $product->store->name,
                ]
                : null,
            'media' => $this->mapMedia($product),
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
                'quantity' => $variant->quantity,
                'isDefault' => (bool) $variant->isDefault,
                'attributeName' => $variant->attributeName,
            ])->values()->all(),
            'rating' => $product->rates_avg_score !== null ? round((float) $product->rates_avg_score, 1) : null,
            'rating_count' => (int) ($product->rates_count ?? 0),
        ];
    }

    private function formatService(Service $service): array
    {
        $media = $this->mapMedia($service);

        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'image' => $media[0]['url'] ?? null,
            'area' => $service->relationLoaded('area') && $service->area
                ? [
                    'id' => $service->area->id,
                    'name' => $service->area->name,
                    'floor' => $service->area->relationLoaded('floor') && $service->area->floor
                        ? [
                            'id' => $service->area->floor->id,
                            'name' => $service->area->floor->name,
                            'number' => $service->area->floor->number,
                        ]
                        : null,
                ]
                : null,
            'media' => $media,
            'rating' => $service->rates_avg_score !== null ? round((float) $service->rates_avg_score, 1) : null,
            'rating_count' => (int) ($service->rates_count ?? 0),
        ];
    }

    private function mapMedia(Store|Product|Service $model): array
    {
        if (! $model->relationLoaded('media')) {
            return [];
        }

        return $model->media->map(fn ($m) => [
            'id' => $m->id,
            'url' => $m->url,
            'fileType' => $m->fileType,
        ])->values()->all();
    }
}
