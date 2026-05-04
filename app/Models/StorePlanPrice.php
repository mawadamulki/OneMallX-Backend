<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StorePlanPrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'store_plan_prices';

    protected $fillable = [
        'storeSubscriptionPlanID',
        'duration',
        'price',
    ];

    public function storeSubscriptionPlan()
    {
        return $this->belongsTo(StoreSubscriptionPlan::class, 'storeSubscriptionPlanID');
    }

}
