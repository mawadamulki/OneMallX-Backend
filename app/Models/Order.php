<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'basketID',
        'userID',
        'status',
        'totalPrice',
    ];

    public function basket()
    {
        return $this->belongsTo(Basket::class, 'basketID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userID');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'orderID');
    }

    public function productItems()
    {
        return $this->hasMany(OrderItem::class, 'orderID')
            ->where('lineType', OrderItem::LINE_TYPE_PRODUCT);
    }

    public function serviceItems()
    {
        return $this->hasMany(OrderItem::class, 'orderID')
            ->where('lineType', OrderItem::LINE_TYPE_SERVICE);
    }

    public function customerPayment()
    {
        return $this->hasOne(CustomerPayment::class, 'orderID');
    }
}
