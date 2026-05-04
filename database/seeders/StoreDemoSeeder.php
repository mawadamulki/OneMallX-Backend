<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreDemoSeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'onemallx.local';

    /** @var int Minimum 10+ stores per retail area */
    private const STORES_PER_AREA = 12;

    /** @var int Minimum 10+ products per store */
    private const PRODUCTS_PER_STORE = 12;

    /**
     * One Store Owner user per store (matches subscription flow: one email ↔ one store).
     * Many stores per retail area (usageType store), many products per store. No media.
     * Re-running removes previous seeded store-owner users (demo-area-* and legacy demo-store-owner).
     */
    public function run(): void
    {
        $storeAreas = Area::query()
            ->where('usageType', 'store')
            ->with('floor')
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
                    'description' => "Seeded unit {$unit} in {$area->name} ({$area->category}).",
                    'status' => 'active',
                    'accountStatus' => 'active',
                    'paymentAccount' => null,
                ]);
                $storeCount++;

                for ($p = 1; $p <= self::PRODUCTS_PER_STORE; $p++) {
                    Product::query()->create([
                        'storeID' => $store->id,
                        'name' => "SKU-{$area->id}-{$unit}-{$p}",
                        'detail' => "Demo product {$p} for {$store->name}.",
                        'price' => 499 + ($p * 397) + ($unit * 13),
                        'quantity' => 8 + ($p * 4) + ($unit % 7),
                    ]);
                    $productCount++;
                }
            }
        }

        $this->command?->info(sprintf(
            'StoreDemoSeeder: %d retail area(s), %d owner account(s), %d store(s), %d product(s). Login any owner: password "%s".',
            $storeAreas->count(),
            $ownerCount,
            $storeCount,
            $productCount,
            'password'
        ));
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
