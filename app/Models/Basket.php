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

    public function products()
    {
        return $this->hasMany(BasketProduct::class, 'basketID');
    }

    public function order()
    {
        return $this->hasOne(Order::class, 'basketID');
    }
}

