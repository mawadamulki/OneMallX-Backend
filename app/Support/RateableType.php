<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\Store;

class RateableType
{
    public const ALIASES = [
        'store' => Store::class,
        'product' => Product::class,
        'service' => Service::class,
        'service_item' => ServiceItem::class,
    ];

    public static function resolveClass(string $alias): ?string
    {
        return self::ALIASES[$alias] ?? null;
    }

    public static function aliasForClass(string $class): ?string
    {
        $flip = array_flip(self::ALIASES);

        return $flip[$class] ?? null;
    }

    /** @return list<string> */
    public static function allowedAliases(): array
    {
        return array_keys(self::ALIASES);
    }
}
