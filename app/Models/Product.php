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
        'detail',
        'price',
        'quantity',
        'storeID',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable', 'mediableType', 'mediableID');
    }

    public function rates()
    {
        return $this->morphMany(Rate::class, 'rateable', 'rateableType', 'rateableID');
    }

    public function basketProducts()
    {
        return $this->hasMany(BasketProduct::class, 'productID');
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class, 'productID');
    }

    public function favoriteProducts()
    {
        return $this->hasMany(FavoriteProduct::class, 'productID');
    }
}

