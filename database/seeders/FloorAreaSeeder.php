<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\BusinessCategory;
use App\Models\Floor;
use App\Models\Mall;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class FloorAreaSeeder extends Seeder
{
    /** Minimal 1×1 transparent PNG (valid image for demos). */
    private const PLACEHOLDER_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * Seed demo floors and areas for the default mall (frontend integration).
     */
    public function run(): void
    {
        $mall = Mall::query()->first();

        if (! $mall) {
            $this->command?->warn('FloorAreaSeeder skipped: no mall found. Run MallSeeder first.');

            return;
        }

        $categoryIds = BusinessCategory::query()->pluck('id', 'slug');

        $floorDefinitions = [
            ['name' => 'Ground Floor', 'number' => 0, 'areas' => [
                ['name' => 'Main Entrance Plaza', 'number' => 1, 'usageType' => 'service', 'categorySlug' => 'common', 'maxCapacity' => 200],
                ['name' => 'West Retail Wing', 'number' => 2, 'usageType' => 'store', 'categorySlug' => 'retail', 'maxCapacity' => 15],
                ['name' => 'Food Court Central', 'number' => 3, 'usageType' => 'store', 'categorySlug' => 'food_beverage', 'maxCapacity' => 12],
            ]],
            ['name' => 'First Floor', 'number' => 1, 'areas' => [
                ['name' => 'Fashion Corridor A', 'number' => 10, 'usageType' => 'store', 'categorySlug' => 'fashion', 'maxCapacity' => 20],
                ['name' => 'Electronics Zone', 'number' => 11, 'usageType' => 'store', 'categorySlug' => 'electronics', 'maxCapacity' => 10],
                ['name' => 'Customer Service Desk', 'number' => 12, 'usageType' => 'service', 'categorySlug' => 'customer_service', 'maxCapacity' => 50],
            ]],
            ['name' => 'Second Floor', 'number' => 2, 'areas' => [
                ['name' => 'Entertainment Hub', 'number' => 20, 'usageType' => 'service', 'categorySlug' => 'entertainment', 'maxCapacity' => 300],
                ['name' => 'Beauty & Wellness Alley', 'number' => 21, 'usageType' => 'store', 'categorySlug' => 'beauty', 'maxCapacity' => 8],
                ['name' => 'Kids Play Corner', 'number' => 22, 'usageType' => 'service', 'categorySlug' => 'family', 'maxCapacity' => 40],
            ]],
        ];

        foreach ($floorDefinitions as $def) {
            $areas = $def['areas'];
            unset($def['areas']);

            $floor = Floor::create([
                ...$def,
                'mallID' => $mall->id,
            ]);

            $this->attachSeedPhoto($floor, 'floors', 'floor-'.$floor->id);

            foreach ($areas as $area) {
                $categorySlug = $area['categorySlug'];
                unset($area['categorySlug']);

                $areaModel = Area::create([
                    ...$area,
                    'categoryID' => $categoryIds[$categorySlug],
                    'floorID' => $floor->id,
                ]);

                $this->attachSeedPhoto($areaModel, 'areas', 'area-'.$areaModel->id);
            }
        }
    }

    /**
     * One demo image per floor/area (public disk + media row), same pattern as API uploads.
     */
    private function attachSeedPhoto(Model $model, string $folder, string $basename): void
    {
        $disk = Storage::disk('public');
        $path = "{$folder}/{$model->id}/{$basename}.png";
        $disk->put($path, base64_decode(self::PLACEHOLDER_PNG));

        $model->media()->create([
            'fileType' => 'image/png',
            'url' => $path,
        ]);
    }
}
