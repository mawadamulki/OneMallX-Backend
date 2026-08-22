<?php

namespace App\Services;

use App\Models\Icon;
use App\Support\IconFormatter;

class IconService
{
    public function listActive(): array
    {
        $icons = Icon::query()
            ->active()
            ->orderBy('sortOrder')
            ->orderBy('name')
            ->get()
            ->map(fn (Icon $icon) => IconFormatter::toArray($icon));

        return [
            'success' => true,
            'icons' => $icons->values()->all(),
        ];
    }

    public function findActiveById(int $iconId): ?Icon
    {
        return Icon::query()
            ->active()
            ->whereKey($iconId)
            ->first();
    }
}
