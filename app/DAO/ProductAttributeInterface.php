<?php

namespace App\DAO;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Collection;

interface ProductAttributeInterface
{
    public function listForStore(int $storeId): Collection;

    public function findForStore(int $attributeId, int $storeId): ?ProductAttribute;

    public function findValueForStore(int $valueId, int $storeId): ?ProductAttributeValue;

    public function createForStore(int $storeId, array $data): ProductAttribute;

    public function updateAttribute(ProductAttribute $attribute, array $data): ProductAttribute;

    public function deleteAttribute(ProductAttribute $attribute): bool;

    public function createValue(ProductAttribute $attribute, array $data): ProductAttributeValue;

    public function updateValue(ProductAttributeValue $value, array $data): ProductAttributeValue;

    public function deleteValue(ProductAttributeValue $value): bool;

    public function codeExistsInStore(int $storeId, string $code, ?int $excludeAttributeId = null): bool;

    /** @param  int[]  $attributeValueIds */
    public function allValuesBelongToStore(int $storeId, array $attributeValueIds): bool;
}
