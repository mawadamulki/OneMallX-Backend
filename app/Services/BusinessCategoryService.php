<?php

namespace App\Services;

use App\DAO\BusinessCategoryInterface;
use App\Models\BusinessCategory;
use App\Support\BusinessCategoryFormatter;

class BusinessCategoryService
{
    public function __construct(
        protected BusinessCategoryInterface $businessCategoryClass,
        protected StoreService $storeService,
        protected ServiceService $serviceService,
    ) {}

    public function listPublic(?string $type = null): array
    {
        if ($type !== null && ! in_array($type, ['store', 'service'], true)) {
            abort(422, 'Invalid type. Allowed values: store, service.');
        }

        $categories = $this->businessCategoryClass
            ->listActive($type)
            ->map(fn (BusinessCategory $category) => BusinessCategoryFormatter::toArray($category));

        return [
            'success' => true,
            'categories' => $categories->values()->all(),
        ];
    }

    public function listStoresForCustomer(int $categoryId, int $perPage): ?array
    {
        $category = $this->businessCategoryClass->findActiveForUsageType($categoryId, 'store');

        if ($category === null) {
            return null;
        }

        $paginator = $this->storeService->listForCustomerByBusinessCategory($perPage, $categoryId);

        return array_merge(
            [
                'success' => true,
                'category' => BusinessCategoryFormatter::toArray($category),
            ],
            $paginator->toArray()
        );
    }

    public function listServicesForCustomer(int $categoryId, int $perPage): ?array
    {
        $category = $this->businessCategoryClass->findActiveForUsageType($categoryId, 'service');

        if ($category === null) {
            return null;
        }

        $paginator = $this->serviceService->listForCustomerByBusinessCategory($perPage, $categoryId);

        return array_merge(
            [
                'success' => true,
                'category' => BusinessCategoryFormatter::toArray($category),
            ],
            $paginator->toArray()
        );
    }

    public function validateForArea(string $usageType, int $categoryId): ?string
    {
        if (! in_array($usageType, ['store', 'service'], true)) {
            return 'Invalid area usage type.';
        }

        $category = $this->businessCategoryClass->findActiveForUsageType($categoryId, $usageType);

        if ($category === null) {
            return 'Selected category is invalid for this area type.';
        }

        return null;
    }
}
