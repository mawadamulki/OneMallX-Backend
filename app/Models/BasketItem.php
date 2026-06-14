<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BasketItem extends Model
{
    use HasFactory;

    public const LINE_TYPE_PRODUCT = 'product';

    public const LINE_TYPE_SERVICE = 'service';

    protected $fillable = [
        'basketID',
        'lineType',
        'lineKey',
        'itemType',
        'itemID',
        'quantity',
        'unitPrice',
        'employeeID',
        'scheduledDate',
        'scheduledTime',
    ];

    protected function casts(): array
    {
        return [
            'scheduledDate' => 'date',
        ];
    }

    public function basket()
    {
        return $this->belongsTo(Basket::class, 'basketID');
    }

    public function item()
    {
        return $this->morphTo(__FUNCTION__, 'itemType', 'itemID');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employeeID');
    }

    public static function buildLineKey(
        string $lineType,
        string $itemType,
        int $itemId,
        ?int $employeeId = null,
        ?string $scheduledDate = null,
        ?string $scheduledTime = null
    ): string {
        if ($lineType === self::LINE_TYPE_PRODUCT) {
            return 'product:'.$itemType.':'.$itemId;
        }

        return implode(':', array_filter([
            'service',
            $itemType,
            (string) $itemId,
            $employeeId !== null ? (string) $employeeId : null,
            $scheduledDate,
            $scheduledTime,
        ], fn ($part) => $part !== null && $part !== ''));
    }
}
