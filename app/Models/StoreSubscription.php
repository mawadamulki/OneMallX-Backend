<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreSubscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'storeID',
        'storeSubscriptionPlanID',
        'planPriceID',
        'startDate',
        'endDate',
        'autoRenew',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function storeSubscriptionPlan()
    {
        return $this->belongsTo(StoreSubscriptionPlan::class, 'storeSubscriptionPlanID');
    }

    public function planPrice()
    {
        return $this->belongsTo(StorePlanPrice::class, 'planPriceID');
    }

    public function payments()
    {
        return $this->hasMany(StoreSubscriptionPayment::class, 'subscriptionID');
    }
}

