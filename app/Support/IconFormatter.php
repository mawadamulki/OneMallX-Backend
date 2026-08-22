<?php

namespace App\Support;

use App\Models\Icon;

class IconFormatter
{
    public static function toArray(?Icon $icon): ?array
    {
        if ($icon === null) {
            return null;
        }

        return [
            'id' => $icon->id,
            'name' => $icon->name,
            'slug' => $icon->slug,
            'url' => $icon->url,
        ];
    }
}
