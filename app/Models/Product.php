<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'detail',
        'shortDetail',
        'storeID',
        'status',
        'isFeatured',
        'publishedAt',
    ];

    protected function casts(): array
    {
        return [
            'isFeatured' => 'boolean',
            'publishedAt' => 'datetime',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'productID');
    }

    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class, 'productID')->where('isDefault', true);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_category', 'productID', 'categoryID')
            ->withTimestamps();
    }

    public function collections()
    {
        return $this->belongsToMany(ProductCollection::class, 'collection_product', 'productID', 'collectionID')
            ->withTimestamps();
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable', 'mediableType', 'mediableID');
    }

    public function rates()
    {
        return $this->morphMany(Rate::class, 'rateable', 'rateableType', 'rateableID');
    }

    public function favoriteProducts()
    {
        return $this->hasMany(FavoriteProduct::class, 'productID');
    }
}
