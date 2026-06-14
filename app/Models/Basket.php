<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Basket extends Model
{
    use HasFactory;

    protected $fillable = [
        'userID',
        'status',
        'totalPrice',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userID');
    }

    public function items()
    {
        return $this->hasMany(BasketItem::class, 'basketID');
    }

    public function productItems()
    {
        return $this->hasMany(BasketItem::class, 'basketID')
            ->where('lineType', BasketItem::LINE_TYPE_PRODUCT);
    }

    public function serviceItems()
    {
        return $this->hasMany(BasketItem::class, 'basketID')
            ->where('lineType', BasketItem::LINE_TYPE_SERVICE);
    }

    public function order()
    {
        return $this->hasOne(Order::class, 'basketID');
    }
}
