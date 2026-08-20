<?php

namespace App\Services;

use App\DAO\ProductInterface;
use App\Models\FavoriteProduct;
use App\Models\Product;
use App\Models\User;

class FavoriteProductService
{
    public function __construct(
        protected ProductInterface $productClass,
        protected ProductService $productService,
    ) {}

    public function list(int $userId, int $perPage): array
    {
        $customerCheck = $this->ensureCustomer($userId);
        if ($customerCheck !== null) {
            return $customerCheck;
        }

        $favorites = FavoriteProduct::query()
            ->where('userID', $userId)
            ->with(['product' => fn ($q) => $q
                ->with($this->customerProductRelations())
                ->withCount('rates')
                ->withAvg('rates', 'score')])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (FavoriteProduct $favorite) => [
                'id' => $favorite->id,
                'productId' => $favorite->productID,
                'created_at' => $favorite->created_at,
                'product' => $favorite->product instanceof Product
                    ? $this->productService->formatCustomerSummary($favorite->product)
                    : null,
            ]);

        return [
            'success' => true,
            'favorites' => $favorites,
        ];
    }

    public function add(int $userId, int $productId): array
    {
        $customerCheck = $this->ensureCustomer($userId);
        if ($customerCheck !== null) {
            return $customerCheck;
        }

        if ($this->productClass->findVisibleProductForCustomer($productId) === null) {
            return $this->fail('Product not found.', 404);
        }

        $favorite = FavoriteProduct::query()->firstOrCreate([
            'userID' => $userId,
            'productID' => $productId,
        ]);

        return [
            'success' => true,
            'message' => $favorite->wasRecentlyCreated
                ? 'Product added to favorites.'
                : 'Product is already in favorites.',
            'favorite' => [
                'id' => $favorite->id,
                'productId' => $favorite->productID,
            ],
            'http_status' => $favorite->wasRecentlyCreated ? 201 : 200,
        ];
    }

    public function remove(int $userId, int $productId): array
    {
        $customerCheck = $this->ensureCustomer($userId);
        if ($customerCheck !== null) {
            return $customerCheck;
        }

        $favorite = FavoriteProduct::query()
            ->where('userID', $userId)
            ->where('productID', $productId)
            ->first();

        if ($favorite === null) {
            return $this->fail('Favorite not found.', 404);
        }

        $favorite->delete();

        return [
            'success' => true,
            'message' => 'Product removed from favorites.',
            'deleted' => true,
        ];
    }

    /** @return array{success: false, message: string, http_status: int}|null */
    private function ensureCustomer(int $userId): ?array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return $this->fail('User not found.', 404);
        }

        if (! $user->hasRole('Customer')) {
            return $this->fail('Only customers can manage favorites.', 403);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function customerProductRelations(): array
    {
        return [
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
