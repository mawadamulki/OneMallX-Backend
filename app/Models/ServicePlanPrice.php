<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ServicePlanPrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'service_plan_prices';

    protected $fillable = [
        'serviceSubscriptionPlanID',
        'duration',
        'price',
    ];

    public function serviceSubscriptionPlan()
    {
        return $this->belongsTo(ServiceSubscriptionPlan::class, 'serviceSubscriptionPlanID');
    }

}
