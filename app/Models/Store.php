<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'storeOwnerID',
        'areaID',
        'description',
        'logo',
        'status',
        'accountStatus',
        'paymentAccount',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'storeOwnerID');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'areaID');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'storeID');
    }

    public function productVariants()
    {
        return $this->hasMany(ProductVariant::class, 'storeID');
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'storeID');
    }

    public function productAttributes()
    {
        return $this->hasMany(ProductAttribute::class, 'storeID');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable', 'mediableType', 'mediableID');
    }

    public function rates()
    {
        return $this->morphMany(Rate::class, 'rateable', 'rateableType', 'rateableID');
    }

    public function subscriptions()
    {
        return $this->hasMany(StoreSubscription::class, 'storeID');
    }

    public function scopeVisibleToCustomers(Builder $query): void
    {
        $query->where('accountStatus', 'active');
    }

    public function isVisibleToCustomers(): bool
    {
        return $this->accountStatus === 'active';
    }
}
