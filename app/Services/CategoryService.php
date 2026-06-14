<?php

namespace App\Services;

use App\DAO\CategoryInterface;
use App\DAO\ProductInterface;
use App\Models\Category;
use Illuminate\Support\Str;

class CategoryService
{
    public function __construct(
        protected CategoryInterface $categoryClass,
        protected ProductInterface $productClass,
    ) {}

    public function listForOwner(int $userId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $categories = $this->categoryClass
            ->listForStore((int) $store->id)
            ->map(fn (Category $category) => $this->toArray($category));

        return [
            'success' => true,
            'categories' => $categories,
        ];
    }

    public function createForOwner(int $userId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        if (isset($payload['parentID'])) {
            $parent = $this->categoryClass->findForStore((int) $payload['parentID'], (int) $store->id);

            if ($parent === null) {
                return $this->fail('Parent category not found.', 422);
            }
        }

        $slug = $this->resolveSlug((int) $store->id, (string) $payload['name'], $payload['slug'] ?? null);

        $category = $this->categoryClass->createForStore((int) $store->id, [
            'name' => $payload['name'],
            'slug' => $slug,
            'parentID' => $payload['parentID'] ?? null,
            'sortOrder' => (int) ($payload['sortOrder'] ?? 0),
        ]);

        return [
            'success' => true,
            'message' => 'Category created.',
            'category' => $this->toArray($category),
            'http_status' => 201,
        ];
    }

    public function updateForOwner(int $userId, int $categoryId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $category = $this->categoryClass->findForStore($categoryId, (int) $store->id);

        if ($category === null) {
            return $this->fail('Category not found.', 404);
        }

        if (isset($payload['parentID'])) {
            if ((int) $payload['parentID'] === (int) $category->id) {
                return $this->fail('A category cannot be its own parent.', 422);
            }

            $parent = $this->categoryClass->findForStore((int) $payload['parentID'], (int) $store->id);

            if ($parent === null) {
                return $this->fail('Parent category not found.', 422);
            }
        }

        $data = [];

        if (array_key_exists('name', $payload)) {
            $data['name'] = $payload['name'];
        }

        if (array_key_exists('slug', $payload) || array_key_exists('name', $payload)) {
            $name = $payload['name'] ?? $category->name;
            $data['slug'] = $this->resolveSlug(
                (int) $store->id,
                $name,
                $payload['slug'] ?? null,
                (int) $category->id
            );
        }

        foreach (['parentID', 'sortOrder'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        $updated = $this->categoryClass->update($category, $data);

        return [
            'success' => true,
            'message' => 'Category updated.',
            'category' => $this->toArray($updated),
        ];
    }

    public function deleteForOwner(int $userId, int $categoryId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $category = $this->categoryClass->findForStore($categoryId, (int) $store->id);

        if ($category === null) {
            return $this->fail('Category not found.', 404);
        }

        $this->categoryClass->delete($category);

        return [
            'success' => true,
            'message' => 'Category deleted.',
            'deleted' => true,
        ];
    }

    private function toArray(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'parentID' => $category->parentID,
            'sortOrder' => $category->sortOrder,
            'parent' => $category->relationLoaded('parent') && $category->parent
                ? [
                    'id' => $category->parent->id,
                    'name' => $category->parent->name,
                    'slug' => $category->parent->slug,
                ]
                : null,
        ];
    }

    private function resolveSlug(int $storeId, string $name, ?string $requestedSlug, ?int $excludeCategoryId = null): string
    {
        $base = Str::slug($requestedSlug ?: $name) ?: 'category';

        $slug = $base;
        $suffix = 1;

        while ($this->categoryClass->slugExistsInStore($storeId, $slug, $excludeCategoryId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
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
