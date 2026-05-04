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

    public function products()
    {
        return $this->hasMany(OrderProduct::class, 'orderID');
    }

    public function customerPayment()
    {
        return $this->hasOne(CustomerPayment::class, 'orderID');
    }
}

