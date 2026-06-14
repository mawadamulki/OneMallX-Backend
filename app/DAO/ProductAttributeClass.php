<?php

namespace App\DAO;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Collection;

class ProductAttributeClass implements ProductAttributeInterface
{
    public function listForStore(int $storeId): Collection
    {
        return ProductAttribute::query()
            ->where('storeID', $storeId)
            ->with(['values' => fn ($q) => $q->orderBy('sortOrder')->orderBy('value')])
            ->orderBy('sortOrder')
            ->orderBy('name')
            ->get();
    }

    public function findForStore(int $attributeId, int $storeId): ?ProductAttribute
    {
        return ProductAttribute::query()
            ->whereKey($attributeId)
            ->where('storeID', $storeId)
            ->with(['values' => fn ($q) => $q->orderBy('sortOrder')->orderBy('value')])
            ->first();
    }

    public function findValueForStore(int $valueId, int $storeId): ?ProductAttributeValue
    {
        return ProductAttributeValue::query()
            ->whereKey($valueId)
            ->whereHas('attribute', fn ($q) => $q->where('storeID', $storeId))
            ->with('attribute')
            ->first();
    }

    public function createForStore(int $storeId, array $data): ProductAttribute
    {
        return ProductAttribute::query()->create([
            ...$data,
            'storeID' => $storeId,
        ]);
    }

    public function updateAttribute(ProductAttribute $attribute, array $data): ProductAttribute
    {
        $attribute->update($data);

        return $attribute->fresh(['values']);
    }

    public function deleteAttribute(ProductAttribute $attribute): bool
    {
        return (bool) $attribute->delete();
    }

    public function createValue(ProductAttribute $attribute, array $data): ProductAttributeValue
    {
        return $attribute->values()->create($data);
    }

    public function updateValue(ProductAttributeValue $value, array $data): ProductAttributeValue
    {
        $value->update($data);

        return $value->fresh(['attribute']);
    }

    public function deleteValue(ProductAttributeValue $value): bool
    {
        return (bool) $value->delete();
    }

    public function codeExistsInStore(int $storeId, string $code, ?int $excludeAttributeId = null): bool
    {
        $query = ProductAttribute::query()
            ->where('storeID', $storeId)
            ->where('code', $code);

        if ($excludeAttributeId !== null) {
            $query->where('id', '!=', $excludeAttributeId);
        }

        return $query->exists();
    }

    public function allValuesBelongToStore(int $storeId, array $attributeValueIds): bool
    {
        if ($attributeValueIds === []) {
            return true;
        }

        $count = ProductAttributeValue::query()
            ->whereIn('id', $attributeValueIds)
            ->whereHas('attribute', fn ($q) => $q->where('storeID', $storeId))
            ->count();

        return $count === count(array_unique($attributeValueIds));
    }
}
