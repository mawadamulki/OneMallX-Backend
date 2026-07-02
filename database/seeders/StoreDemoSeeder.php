<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreDemoSeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'onemallx.local';

    /** Minimal 1×1 transparent PNG (valid image for demo collection covers). */
    private const PLACEHOLDER_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var int Stores per retail area */
    private const STORES_PER_AREA = 12;

    /** @var int Products per store */
    private const PRODUCTS_PER_STORE = 12;

    /** @var list<string> */
    private const CATEGORY_NAMES = ['New Arrivals', 'Best Sellers', 'Sale', 'Featured'];

    /** @var list<string> */
    private const COLLECTION_NAMES = ['Staff Picks', 'Season Highlights'];

    private bool $loggedMobileTestStore = false;

    /**
     * One Store Owner user per store (matches subscription flow: one email ↔ one store).
     * Seeds categories, collections, offers, and customization on the first store for mobile API testing.
     * Re-running removes previous seeded store-owner users (demo-area-* and legacy demo-store-owner).
     */
    public function run(): void
    {
        $storeAreas = Area::query()
            ->where('usageType', 'store')
            ->with(['floor', 'category'])
            ->orderBy('floorID')
            ->orderBy('number')
            ->get();

        if ($storeAreas->isEmpty()) {
            $this->command?->warn('StoreDemoSeeder skipped: no store areas. Run FloorAreaSeeder first.');

            return;
        }

        $this->purgeSeededStoreOwnerUsers();

        $storeCount = 0;
        $productCount = 0;
        $ownerCount = 0;
        $offerCount = 0;

        foreach ($storeAreas as $area) {
            $floorLabel = $area->floor?->name ?? 'Floor';

            for ($unit = 1; $unit <= self::STORES_PER_AREA; $unit++) {
                $email = $this->seededOwnerEmail($area->id, $unit);

                $owner = User::query()->create([
                    'name' => "Owner — {$area->name} Shop {$unit}",
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'phoneNumber' => $this->seededOwnerPhone($area->id, $unit),
                    'status' => 'active',
                    'is_verified' => true,
                ]);
                $owner->assignRole('Store Owner');
                $ownerCount++;

                $store = Store::query()->create([
                    'storeOwnerID' => $owner->id,
                    'areaID' => $area->id,
                    'name' => "{$area->name} — Shop {$unit} ({$floorLabel})",
                    'description' => "Seeded unit {$unit} in {$area->name} ({$area->category?->name}).",
                    'status' => 'active',
                    'accountStatus' => 'active',
                    'paymentAccount' => null,
                ]);
                $storeCount++;

                if (! $this->loggedMobileTestStore) {
                    $this->seedMobileTestStoreCustomization($store);
                }

                $categories = $this->seedCategoriesForStore($store);
                $collections = $this->seedCollectionsForStore($store);

                for ($p = 1; $p <= self::PRODUCTS_PER_STORE; $p++) {
                    $result = $this->seedProduct($store, $area, $unit, $p, $categories, $collections);
                    $productCount++;
                    if ($result['hasOffer']) {
                        $offerCount++;
                    }
                }

                if (! $this->loggedMobileTestStore) {
                    $this->logMobileTestEndpoints($store, $categories, $collections, $email);
                    $this->loggedMobileTestStore = true;
                }
            }
        }

        $this->command?->info(sprintf(
            'StoreDemoSeeder: %d retail area(s), %d owner account(s), %d store(s), %d product(s), %d offer product(s). Login any owner: password "%s".',
            $storeAreas->count(),
            $ownerCount,
            $storeCount,
            $productCount,
            $offerCount,
            'password'
        ));
    }

    private function seedMobileTestStoreCustomization(Store $store): void
    {
        $store->update([
            'customization' => [
                'theme' => 'light',
                'primaryColor' => '#2563EB',
                'accentColor' => '#F59E0B',
            ],
            'customizationData' => [
                'sections' => [
                    ['type' => 'banner', 'enabled' => true, 'order' => 1],
                    ['type' => 'featuredProducts', 'enabled' => true, 'order' => 2],
                    ['type' => 'collections', 'enabled' => true, 'order' => 3],
                ],
            ],
            'detailCustomization' => [
                'layout' => 'standard',
                'showReviews' => true,
                'showRelatedProducts' => true,
            ],
            'detailCustomizationData' => [
                'sections' => [
                    ['type' => 'gallery', 'enabled' => true],
                    ['type' => 'description', 'enabled' => true],
                    ['type' => 'variants', 'enabled' => true],
                ],
            ],
        ]);
    }

    /** @return list<Category> */
    private function seedCategoriesForStore(Store $store): array
    {
        $categories = [];

        foreach (self::CATEGORY_NAMES as $index => $name) {
            $categories[] = Category::query()->create([
                'storeID' => $store->id,
                'name' => $name,
                'slug' => Str::slug($name).'-'.$store->id,
                'parentID' => null,
                'sortOrder' => $index,
            ]);
        }

        return $categories;
    }

    /** @return list<ProductCollection> */
    private function seedCollectionsForStore(Store $store): array
    {
        $collections = [];

        foreach (self::COLLECTION_NAMES as $name) {
            $collections[] = ProductCollection::query()->create([
                'storeID' => $store->id,
                'name' => $name,
                'description' => "Demo collection for {$store->name}.",
                'image' => $this->seedCollectionImage($store->id, $name),
            ]);
        }

        return $collections;
    }

    /**
     * @param  list<Category>  $categories
     * @param  list<ProductCollection>  $collections
     * @return array{hasOffer: bool}
     */
    private function seedProduct(
        Store $store,
        Area $area,
        int $unit,
        int $productIndex,
        array $categories,
        array $collections,
    ): array {
        $productName = "Demo Product {$productIndex}";
        $sku = strtoupper("SKU-{$area->id}-{$unit}-{$productIndex}");
        $basePrice = 499 + ($productIndex * 397) + ($unit * 13);
        $quantity = 8 + ($productIndex * 4) + ($unit % 7);
        $hasOffer = $productIndex % 3 === 0;
        $discountPercentage = $hasOffer ? (10 + ($productIndex % 3) * 5) : 0;

        $variantData = [
            'storeID' => $store->id,
            'sku' => $sku,
            'quantity' => $quantity,
            'isDefault' => true,
            'status' => 'active',
            'discountPercentage' => 0,
        ];

        if ($hasOffer) {
            $variantData['compareAtPrice'] = $basePrice;
            $variantData['price'] = (int) round($basePrice * (1 - $discountPercentage / 100));
            $variantData['discountPercentage'] = $discountPercentage;
        } else {
            $variantData['price'] = $basePrice;
            $variantData['compareAtPrice'] = null;
        }

        $product = Product::query()->create([
            'storeID' => $store->id,
            'name' => $productName,
            'slug' => Str::slug($productName).'-'.$area->id.'-'.$unit.'-'.$productIndex,
            'detail' => "Demo product {$productIndex} for {$store->name}. Full description for mobile product detail screens.",
            'shortDetail' => "Short summary for product {$productIndex}.",
            'status' => 'active',
            'isFeatured' => $productIndex <= 2,
            'publishedAt' => Carbon::now()->subDays(self::PRODUCTS_PER_STORE - $productIndex),
        ]);

        ProductVariant::query()->create([
            'productID' => $product->id,
            ...$variantData,
        ]);

        if ($categories !== []) {
            $category = $categories[($productIndex - 1) % count($categories)];
            $product->categories()->sync([$category->id]);
        }

        if ($collections !== []) {
            $collection = $collections[($productIndex - 1) % count($collections)];
            $collection->products()->syncWithoutDetaching([$product->id]);
        }

        return ['hasOffer' => $hasOffer];
    }

    private function seedCollectionImage(int $storeId, string $name): string
    {
        $path = "collections/stores/{$storeId}/".Str::slug($name).'.png';
        Storage::disk('public')->put($path, base64_decode(self::PLACEHOLDER_PNG));

        return $path;
    }

    /**
     * @param  list<Category>  $categories
     * @param  list<ProductCollection>  $collections
     */
    private function logMobileTestEndpoints(Store $store, array $categories, array $collections, string $ownerEmail): void
    {
        $category = $categories[0] ?? null;
        $collection = $collections[0] ?? null;

        $this->command?->info('--- Mobile API test store (first seeded store) ---');
        $this->command?->info("Store ID: {$store->id} | Owner: {$ownerEmail} | password: password");
        $this->command?->line("GET /api/storeDetails/{$store->id}");
        $this->command?->line("GET /api/productsInStore/{$store->id}");
        $this->command?->line("GET /api/productsWithOffersInStore/{$store->id}");
        $this->command?->line("GET /api/categoriesInStore/{$store->id}");
        $this->command?->line("GET /api/collectionsInStore/{$store->id}");
        $this->command?->line("GET /api/storeCustomization/{$store->id}");

        if ($category !== null) {
            $this->command?->line("GET /api/productsInCategory/{$category->id}  (category: {$category->name})");
        }

        if ($collection !== null) {
            $this->command?->line("GET /api/productsInCollection/{$collection->id}  (collection: {$collection->name})");
        }
    }

    private function seededOwnerEmail(int $areaId, int $unit): string
    {
        return "demo-area-{$areaId}-shop-{$unit}@".self::EMAIL_DOMAIN;
    }

    private function seededOwnerPhone(int $areaId, int $unit): string
    {
        $n = ($areaId * 100) + $unit;

        return '09'.str_pad((string) $n, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Remove users created by this seeder so re-runs stay idempotent.
     * Deleting users cascades to their stores; stores cascade (or we rely on store delete) for products.
     */
    private function purgeSeededStoreOwnerUsers(): void
    {
        User::query()
            ->where(function ($q): void {
                $q->where('email', 'like', 'demo-area-%@'.self::EMAIL_DOMAIN)
                    ->orWhere('email', 'demo-store-owner@'.self::EMAIL_DOMAIN);
            })
            ->each(function (User $user): void {
                $user->syncRoles([]);
                $user->delete();
            });
    }
}
