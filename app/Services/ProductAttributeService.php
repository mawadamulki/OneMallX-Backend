<?php

namespace App\Services;

use App\DAO\ProductAttributeInterface;
use App\DAO\ProductInterface;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Str;

class ProductAttributeService
{
    public function __construct(
        protected ProductAttributeInterface $productAttributeClass,
        protected ProductInterface $productClass,
    ) {}

    public function listForOwner(int $userId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $attributes = $this->productAttributeClass
            ->listForStore((int) $store->id)
            ->map(fn (ProductAttribute $attribute) => $this->toAttributeArray($attribute));

        return [
            'success' => true,
            'attributes' => $attributes,
        ];
    }

    public function createAttributeForOwner(int $userId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $code = $this->normalizeCode((string) ($payload['code'] ?? $payload['name']));

        if ($this->productAttributeClass->codeExistsInStore((int) $store->id, $code)) {
            return $this->fail("Attribute code \"{$code}\" already exists in your store.", 422);
        }

        $valuesData = $this->normalizeValuesInput($payload);
        $duplicateValueError = $this->validateDuplicateValues($valuesData);

        if ($duplicateValueError !== null) {
            return $this->fail($duplicateValueError, 422);
        }

        $attribute = $this->productAttributeClass->createForStore((int) $store->id, [
            'name' => $payload['name'],
            'code' => $code,
            'sortOrder' => (int) ($payload['sortOrder'] ?? 0),
        ]);

        foreach ($valuesData as $index => $valueData) {
            $this->productAttributeClass->createValue($attribute, [
                'value' => $valueData['value'],
                'sortOrder' => (int) ($valueData['sortOrder'] ?? $index),
            ]);
        }

        $attribute = $this->productAttributeClass->findForStore((int) $attribute->id, (int) $store->id);

        return [
            'success' => true,
            'message' => 'Attribute created.',
            'attribute' => $this->toAttributeArray($attribute),
            'http_status' => 201,
        ];
    }

    public function updateAttributeForOwner(int $userId, int $attributeId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $attribute = $this->productAttributeClass->findForStore($attributeId, (int) $store->id);

        if ($attribute === null) {
            return $this->fail('Attribute not found.', 404);
        }

        $data = [];

        if (array_key_exists('name', $payload)) {
            $data['name'] = $payload['name'];
        }

        if (array_key_exists('code', $payload)) {
            $code = $this->normalizeCode((string) $payload['code']);

            if ($this->productAttributeClass->codeExistsInStore((int) $store->id, $code, (int) $attribute->id)) {
                return $this->fail("Attribute code \"{$code}\" already exists in your store.", 422);
            }

            $data['code'] = $code;
        }

        if (array_key_exists('sortOrder', $payload)) {
            $data['sortOrder'] = (int) $payload['sortOrder'];
        }

        $updated = $this->productAttributeClass->updateAttribute($attribute, $data);

        return [
            'success' => true,
            'message' => 'Attribute updated.',
            'attribute' => $this->toAttributeArray($updated),
        ];
    }

    public function deleteAttributeForOwner(int $userId, int $attributeId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $attribute = $this->productAttributeClass->findForStore($attributeId, (int) $store->id);

        if ($attribute === null) {
            return $this->fail('Attribute not found.', 404);
        }

        $this->productAttributeClass->deleteAttribute($attribute);

        return [
            'success' => true,
            'message' => 'Attribute deleted.',
            'deleted' => true,
        ];
    }

    public function createValueForOwner(int $userId, int $attributeId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $attribute = $this->productAttributeClass->findForStore($attributeId, (int) $store->id);

        if ($attribute === null) {
            return $this->fail('Attribute not found.', 404);
        }

        $value = $this->productAttributeClass->createValue($attribute, [
            'value' => $payload['value'],
            'sortOrder' => (int) ($payload['sortOrder'] ?? 0),
        ]);

        return [
            'success' => true,
            'message' => 'Attribute value created.',
            'value' => $this->toValueArray($value),
            'http_status' => 201,
        ];
    }

    public function updateValueForOwner(int $userId, int $valueId, array $payload): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $value = $this->productAttributeClass->findValueForStore($valueId, (int) $store->id);

        if ($value === null) {
            return $this->fail('Attribute value not found.', 404);
        }

        $data = [];

        if (array_key_exists('value', $payload)) {
            $data['value'] = $payload['value'];
        }

        if (array_key_exists('sortOrder', $payload)) {
            $data['sortOrder'] = (int) $payload['sortOrder'];
        }

        $updated = $this->productAttributeClass->updateValue($value, $data);

        return [
            'success' => true,
            'message' => 'Attribute value updated.',
            'value' => $this->toValueArray($updated),
        ];
    }

    public function deleteValueForOwner(int $userId, int $valueId): array
    {
        $store = $this->productClass->findStoreByOwnerId($userId);

        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        $value = $this->productAttributeClass->findValueForStore($valueId, (int) $store->id);

        if ($value === null) {
            return $this->fail('Attribute value not found.', 404);
        }

        $this->productAttributeClass->deleteValue($value);

        return [
            'success' => true,
            'message' => 'Attribute value deleted.',
            'deleted' => true,
        ];
    }

    private function toAttributeArray(ProductAttribute $attribute): array
    {
        return [
            'id' => $attribute->id,
            'name' => $attribute->name,
            'code' => $attribute->code,
            'sortOrder' => $attribute->sortOrder,
            'values' => $attribute->relationLoaded('values')
                ? $attribute->values->map(fn (ProductAttributeValue $value) => $this->toValueArray($value))->values()->all()
                : [],
        ];
    }

    private function toValueArray(ProductAttributeValue $value): array
    {
        return [
            'id' => $value->id,
            'attributeID' => $value->attributeID,
            'value' => $value->value,
            'sortOrder' => $value->sortOrder,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeValuesInput(array $payload): array
    {
        if (isset($payload['value']) && is_array($payload['value'])) {
            return [$payload['value']];
        }

        if (isset($payload['values']) && is_array($payload['values'])) {
            return $payload['values'];
        }

        return [];
    }

    /** @param  array<int, array<string, mixed>>  $valuesData */
    private function validateDuplicateValues(array $valuesData): ?string
    {
        $seen = [];

        foreach ($valuesData as $valueData) {
            $value = trim((string) ($valueData['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $key = mb_strtolower($value);

            if (isset($seen[$key])) {
                return "Duplicate attribute value \"{$value}\" in request.";
            }

            $seen[$key] = true;
        }

        return null;
    }

    private function normalizeCode(string $code): string
    {
        return Str::slug($code, '_') ?: 'attribute';
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
