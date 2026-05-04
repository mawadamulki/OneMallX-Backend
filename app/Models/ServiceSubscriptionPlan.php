<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceSubscriptionPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'floorID',
        'serviceSpace',
        'adsNumber',
        'accepting_subscriptions',
    ];

    protected function casts(): array
    {
        return [
            'accepting_subscriptions' => 'boolean',
        ];
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class, 'floorID');
    }

    public function areas()
    {
        return $this->morphMany(Area::class, 'planable', 'planable_type', 'planable_id');
    }


    public function subscriptions()
    {
        return $this->hasMany(ServiceSubscription::class, 'serviceSubscriptionPlanID');
    }

    public function prices()
    {
        return $this->hasMany(ServicePlanPrice::class, 'serviceSubscriptionPlanID');
    }
}

