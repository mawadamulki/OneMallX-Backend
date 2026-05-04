<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscriptionID',
        'methodID',
        'price',
    ];

    public function subscription()
    {
        return $this->belongsTo(StoreSubscription::class, 'subscriptionID');
    }

    public function method()
    {
        return $this->belongsTo(PaymentMethod::class, 'methodID');
    }
}

