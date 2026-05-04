<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customerID',
        'orderID',
        'methodID',
        'price',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customerID');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'orderID');
    }

    public function method()
    {
        return $this->belongsTo(PaymentMethod::class, 'methodID');
    }
}

