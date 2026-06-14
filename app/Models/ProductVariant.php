<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'productID',
        'storeID',
        'sku',
        'barcode',
        'name',
        'price',
        'compareAtPrice',
        'costPrice',
        'quantity',
        'reservedQuantity',
        'weight',
        'isDefault',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'isDefault' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'productID');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function attributeValues()
    {
        return $this->belongsToMany(
            ProductAttributeValue::class,
            'product_variant_attribute_value',
            'productVariantID',
            'attributeValueID'
        )->withTimestamps();
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'productVariantID');
    }

    public function basketItems()
    {
        return $this->morphMany(BasketItem::class, 'item', 'itemType', 'itemID');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable', 'mediableType', 'mediableID');
    }

    public function availableQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->reservedQuantity);
    }
}
