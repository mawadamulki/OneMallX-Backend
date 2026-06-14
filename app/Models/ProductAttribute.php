<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAttribute extends Model
{
    use SoftDeletes;

    protected $table = 'attributes';

    protected $fillable = [
        'storeID',
        'name',
        'code',
        'sortOrder',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function values()
    {
        return $this->hasMany(ProductAttributeValue::class, 'attributeID');
    }
}
