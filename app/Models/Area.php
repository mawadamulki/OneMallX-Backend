<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'number',
        'floorID',
        'usageType',
        'categoryID',
        'maxCapacity',
    ];

    public function floor()
    {
        return $this->belongsTo(Floor::class, 'floorID');
    }

    public function category()
    {
        return $this->belongsTo(BusinessCategory::class, 'categoryID');
    }

    public function stores()
    {
        return $this->hasMany(Store::class, 'areaID');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'areaID');
    }

    public function planable()
    {
        return $this->morphTo('planable', 'planable_type', 'planable_id');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable', 'mediableType', 'mediableID');
    }
}
