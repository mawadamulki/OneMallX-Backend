<?php

namespace Database\Seeders;

use App\Models\Icon;
use Illuminate\Database\Seeder;

class IconSeeder extends Seeder
{
    public function run(): void
    {
        $icons = config('category_icons', []);

        foreach ($icons as $icon) {
            Icon::query()->updateOrCreate(
                ['slug' => $icon['slug']],
                [
                    'name' => $icon['name'],
                    'url' => $icon['url'] ?? null,
                    'sortOrder' => $icon['sortOrder'] ?? 0,
                    'isActive' => $icon['isActive'] ?? true,
                ]
            );
        }
    }
}
