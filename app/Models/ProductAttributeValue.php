<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAttributeValue extends Model
{
    use SoftDeletes;

    protected $table = 'attribute_values';

    protected $fillable = [
        'attributeID',
        'value',
        'sortOrder',
    ];

    public function attribute()
    {
        return $this->belongsTo(ProductAttribute::class, 'attributeID');
    }

    public function variants()
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_attribute_value',
            'attributeValueID',
            'productVariantID'
        )->withTimestamps();
    }
}
