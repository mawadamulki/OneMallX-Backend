<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'providerName',
        'active',
    ];

    public function storeSubscriptionPayments()
    {
        return $this->hasMany(StoreSubscriptionPayment::class, 'methodID');
    }

    public function serviceSubscriptionPayments()
    {
        return $this->hasMany(ServiceSubscriptionPayment::class, 'methodID');
    }

    public function customerPayments()
    {
        return $this->hasMany(CustomerPayment::class, 'methodID');
    }
}

