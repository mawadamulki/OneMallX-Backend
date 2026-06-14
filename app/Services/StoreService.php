<?php

namespace App\Services;

use App\DAO\StoreInterface;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StoreService
{
    public function __construct(
        protected StoreInterface $storeClass
    ) {}

    public function listForCustomer(int $perPage, ?int $areaId): LengthAwarePaginator
    {
        return $this->storeClass->paginateVisibleToCustomers($perPage, $areaId)
            ->through(fn (Store $store) => $this->toCustomerArray($store));
    }

    public function showForCustomer(Store $store): ?array
    {
        $store = $this->storeClass->getStoreForCustomerDetail($store);

        if ($store === null) {
            return null;
        }

        return $this->toCustomerArray($store);
    }


    public function adminStoresSummaryList(int $perPage): LengthAwarePaginator
    {
        return $this->storeClass->paginateAdminStoresSummary($perPage)
            ->through(fn (Store $store) => $this->toAdminStoreSummaryArray($store));
    }


    public function adminStoreFull(int $storeId): array
    {
        $store = $this->storeClass->getAdminStoreFull($storeId);

        return $this->toAdminStoreFullArray($store);
    }


    public function adminStoreProductsSummary(int $storeId, int $perPage): LengthAwarePaginator
    {
        return $this->storeClass->paginateStoreProductsSummary($storeId, $perPage)
            ->through(fn (Product $product) => $this->toAdminProductSummaryArray($product));
    }


    public function adminProductFull(int $productId): ?array
    {
        $product = $this->storeClass->findAdminProductById($productId);

        if ($product === null) {
            return null;
        }

        return $this->toAdminProductFullArray($product);
    }

    private function toCustomerArray(Store $store): array
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
            'media' => $this->mapMediaCollection($store),
        ];
    }

    private function toAdminStoreSummaryArray(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'media' => $this->mapMediaCollection($store),
        ];
    }

    private function toAdminStoreFullArray(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'description' => $store->description,
            'storeOwnerID' => $store->storeOwnerID,
            'areaID' => $store->areaID,
            'status' => $store->status,
            'accountStatus' => $store->accountStatus,
            'paymentAccount' => $store->paymentAccount,
            'created_at' => $store->created_at,
            'updated_at' => $store->updated_at,
            'area' => $store->relationLoaded('area') && $store->area
                ? [
                    'id' => $store->area->id,
                    'name' => $store->area->name,
                    'number' => $store->area->number,
                    'floorID' => $store->area->floorID,
                    'usageType' => $store->area->usageType,
                    'category' => $store->area->category,
                    'maxCapacity' => $store->area->maxCapacity,
                    'floor' => $store->area->relationLoaded('floor') && $store->area->floor
                        ? [
                            'id' => $store->area->floor->id,
                            'name' => $store->area->floor->name,
                            'number' => $store->area->floor->number,
                            'mallID' => $store->area->floor->mallID,
                        ]
                        : null,
                ]
                : null,
            'media' => $this->mapMediaCollection($store),
            'owner' => $store->relationLoaded('owner') && $store->owner
                ? [
                    'id' => $store->owner->id,
                    'name' => $store->owner->name,
                    'email' => $store->owner->email,
                    'phoneNumber' => $store->owner->phoneNumber,
                    'status' => $store->owner->status,
                ]
                : null,
        ];
    }

    private function toAdminProductSummaryArray(Product $product): array
    {
        $defaultVariant = $product->relationLoaded('defaultVariant') ? $product->defaultVariant : null;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
            'sku' => $defaultVariant?->sku,
            'price' => $defaultVariant?->price,
            'quantity' => $defaultVariant?->quantity,
            'media' => $this->mapMediaCollection($product),
        ];
    }

    private function toAdminProductFullArray(Product $product): array
    {
        $store = $product->relationLoaded('store') ? $product->store : null;

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
                ? $product->variants->map(fn (ProductVariant $variant) => $this->toAdminVariantArray($variant))->values()->all()
                : [],
            'store' => $store
                ? [
                    'id' => $store->id,
                    'name' => $store->name,
                    'description' => $store->description,
                    'areaID' => $store->areaID,
                    'status' => $store->status,
                    'accountStatus' => $store->accountStatus,
                    'storeOwnerID' => $store->storeOwnerID,
                ]
                : null,
        ];
    }

    private function toAdminVariantArray(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'name' => $variant->name,
            'price' => $variant->price,
            'compareAtPrice' => $variant->compareAtPrice,
            'quantity' => $variant->quantity,
            'reservedQuantity' => $variant->reservedQuantity,
            'availableQuantity' => $variant->availableQuantity(),
            'weight' => $variant->weight,
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

    private function mapMediaCollection(Store|Product|ProductVariant $model): array
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
