<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'productVariantID',
        'storeID',
        'type',
        'quantityChange',
        'quantityAfter',
        'referenceType',
        'referenceID',
        'note',
        'createdBy',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'productVariantID');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy');
    }
}
