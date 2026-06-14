<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    public const LINE_TYPE_PRODUCT = 'product';

    public const LINE_TYPE_SERVICE = 'service';

    protected $fillable = [
        'orderID',
        'lineType',
        'itemType',
        'itemID',
        'storeID',
        'serviceID',
        'quantity',
        'unitPrice',
        'lineTotal',
        'sku',
        'itemName',
        'variantName',
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

    public function order()
    {
        return $this->belongsTo(Order::class, 'orderID');
    }

    public function item()
    {
        return $this->morphTo(__FUNCTION__, 'itemType', 'itemID');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'serviceID');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employeeID');
    }
}
