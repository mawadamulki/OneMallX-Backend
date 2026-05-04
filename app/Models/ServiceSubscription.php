<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceSubscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'serviceID',
        'serviceSubscriptionPlanID',
        'planPriceID',
        'startDate',
        'endDate',
        'autoRenew',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'serviceID');
    }

    public function serviceSubscriptionPlan()
    {
        return $this->belongsTo(ServiceSubscriptionPlan::class, 'serviceSubscriptionPlanID');
    }

    public function servicePlanPrice()
    {
        return $this->belongsTo(ServicePlanPrice::class, 'planPriceID');
    }

    public function payments()
    {
        return $this->hasMany(ServiceSubscriptionPayment::class, 'subscriptionID');
    }
}

