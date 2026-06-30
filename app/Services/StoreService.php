<?php

namespace App\Services;

use App\DAO\RateInterface;
use App\DAO\StoreInterface;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rate;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Support\BusinessCategoryFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class StoreService
{
    public function __construct(
        protected StoreInterface $storeClass,
        protected RateInterface $rateDAO
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

    public function getStoreRate(int $storeId, int $perPage = 10): array
    {
        $summary = $this->storeClass->getStoreRateSummary($storeId);

        if ($summary === null) {
            return $this->fail('Store not found.', 404);
        }

        $rates = $this->rateDAO->paginateForRateable(Store::class, $storeId, $perPage);

        return [
            'success' => true,
            'summary' => $summary,
            'rates' => $rates->through(fn (Rate $rate) => $this->formatRateForAdmin($rate)),
        ];
    }

    public function getProductRate(int $productId, int $perPage = 10): array
    {
        $summary = $this->storeClass->getProductRateSummary($productId);

        if ($summary === null) {
            return $this->fail('Product not found.', 404);
        }

        $rates = $this->rateDAO->paginateForRateable(Product::class, $productId, $perPage);

        return [
            'success' => true,
            'summary' => $summary,
            'rates' => $rates->through(fn (Rate $rate) => $this->formatRateForAdmin($rate)),
        ];
    }

    private function formatRateForAdmin(Rate $rate): array
    {
        return [
            'id' => $rate->id,
            'user_id' => $rate->userID,
            'score' => (int) $rate->score,
            'comment' => $rate->comment,
            'created_at' => $rate->created_at,
            'user' => $rate->relationLoaded('user') && $rate->user
                ? [
                    'id' => $rate->user->id,
                    'name' => $rate->user->name,
                    'image' => (new \App\Models\Media(['url' => $rate->user->image_url]))->url,
                ]
                : null,
        ];
    }

    public function showForOwner(int $userId): array
    {
        $store = $this->storeClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        return [
            'success' => true,
            'store' => $this->toOwnerArray($store),
        ];
    }

    public function updateForOwner(int $userId, array $payload): array
    {
        $store = $this->storeClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $data = [];

        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if ($data === []) {
            return $this->fail('No fields to update.', 422);
        }

        $updated = $this->storeClass->updateStore($store, $data);

        return [
            'success' => true,
            'message' => 'Store updated.',
            'store' => $this->toOwnerArray($updated),
        ];
    }

    public function updateCustomizationForOwner(int $userId, array $payload): array
    {
        $store = $this->storeClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $data = [];

        foreach (['customization', 'customizationData'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if ($data === []) {
            return $this->fail('No fields to update.', 422);
        }

        $updated = $this->storeClass->updateStore($store, $data);

        return [
            'success' => true,
            'message' => 'Customization saved.',
            'customization' => $updated->customization,
            'customizationData' => $updated->customizationData,
        ];
    }

    public function updateDetailCustomizationForOwner(int $userId, array $payload): array
    {
        $store = $this->storeClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $data = [];

        foreach (['detailCustomization', 'detailCustomizationData'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if ($data === []) {
            return $this->fail('No fields to update.', 422);
        }

        $updated = $this->storeClass->updateStore($store, $data);

        return [
            'success' => true,
            'message' => 'Detail customization saved.',
            'detailCustomization' => $updated->detailCustomization,
            'detailCustomizationData' => $updated->detailCustomizationData,
        ];
    }

    public function planForOwner(int $userId): array
    {
        $subscription = StoreSubscription::query()
            ->with(['storeSubscriptionPlan.floor', 'planPrice'])
            ->whereHas('store', fn ($q) => $q->where('storeOwnerID', $userId))
            ->orderByDesc('endDate')
            ->orderByDesc('id')
            ->first();

        if ($subscription === null) {
            return $this->fail('No subscription found for this account.', 404);
        }

        return [
            'success' => true,
            'plan' => $this->toOwnerPlanArray($subscription),
        ];
    }

    public function attachMediaForOwner(int $userId, UploadedFile $file): array
    {
        $store = $this->storeClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $path = $file->store("stores/{$store->id}", 'public');

        $media = $store->media()->create([
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

    public function attachLogoForOwner(int $userId, UploadedFile $file): array
    {
        $store = $this->storeClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        if ($store->logo) {
            Storage::disk('public')->delete($store->logo);
        }

        $path = $file->store("stores/{$store->id}/logo", 'public');
        $updated = $this->storeClass->updateStore($store, ['logo' => $path]);

        return [
            'success' => true,
            'message' => 'Logo uploaded.',
            'logo' => $this->resolvePublicUrl($updated->logo),
            'http_status' => 201,
        ];
    }

    public function deleteLogoForOwner(int $userId): array
    {
        $store = $this->storeClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        if ($store->logo) {
            Storage::disk('public')->delete($store->logo);
            $this->storeClass->updateStore($store, ['logo' => null]);
        }

        return [
            'success' => true,
            'message' => 'Logo deleted.',
            'deleted' => true,
        ];
    }

    public function deleteMediaForOwner(int $userId, int $mediaId): array
    {
        $store = $this->storeClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $media = Media::query()
            ->whereKey($mediaId)
            ->where('mediableType', Store::class)
            ->where('mediableID', $store->id)
            ->first();

        if ($media === null) {
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

    private function toCustomerArray(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'description' => $store->description,
            'logo' => $this->resolvePublicUrl($store->logo),
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
            'rating' => $store->rates_avg_score !== null ? round((float) $store->rates_avg_score, 1) : null,
            'rating_count' => (int) ($store->rates_count ?? 0),
            'customization' => $store->customization,
            'customizationData' => $store->customizationData,
            'detailCustomization' => $store->detailCustomization,
            'detailCustomizationData' => $store->detailCustomizationData,
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
                    'category' => BusinessCategoryFormatter::toArray($store->area->category),
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

    private function toOwnerArray(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'description' => $store->description,
            'logo' => $this->resolvePublicUrl($store->logo),
            'status' => $store->status,
            'accountStatus' => $store->accountStatus,
            'media' => $this->mapMediaCollection($store),
            'customization' => $store->customization,
            'customizationData' => $store->customizationData,
            'detailCustomization' => $store->detailCustomization,
            'detailCustomizationData' => $store->detailCustomizationData,
        ];
    }

    private function toOwnerPlanArray(StoreSubscription $subscription): array
    {
        $plan = $subscription->storeSubscriptionPlan;
        $price = $subscription->planPrice;
        $floor = $plan?->floor;

        return [
            'id' => $plan?->id,
            'name' => $plan?->name,
            'storeSpace' => $plan?->storeSpace,
            'adsNumber' => $plan?->adsNumber,
            'adsDuration' => $plan?->adsDuration,
            'adsPlacement' => $plan?->adsPlacement,
            'startDate' => $this->formatSubscriptionDate($subscription->startDate),
            'endDate' => $this->formatSubscriptionDate($subscription->endDate),
            'autoRenew' => (bool) $subscription->autoRenew,
            'durationMonths' => $price ? (int) $price->duration : null,
            'price' => $price?->price,
            'floor' => $floor
                ? [
                    'id' => $floor->id,
                    'name' => $floor->name,
                    'number' => $floor->number,
                ]
                : null,
        ];
    }

    private function formatSubscriptionDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function toAdminProductSummaryArray(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
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
            'discountPercentage' => $variant->discountPercentage,
            'quantity' => $variant->quantity,
            'reservedQuantity' => $variant->reservedQuantity,
            'availableQuantity' => $variant->availableQuantity(),
            'weight' => $variant->weight,
            'attributeName' => $variant->attributeName,
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

    private function mapMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'fileType' => $media->fileType,
        ];
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
