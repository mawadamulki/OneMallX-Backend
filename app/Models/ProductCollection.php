<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCollection extends Model
{
    use SoftDeletes;

    protected $table = 'collections';

    protected $fillable = [
        'storeID',
        'name',
        'image',
        'description',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'collection_product', 'collectionID', 'productID')
            ->withTimestamps();
    }
}
