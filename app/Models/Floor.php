<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Floor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'number',
        'mallID',
    ];

    public function mall()
    {
        return $this->belongsTo(Mall::class, 'mallID');
    }

    public function areas()
    {
        return $this->hasMany(Area::class, 'floorID');
    }

    public function stores()
    {
        return $this->hasManyThrough(Store::class, Area::class, 'floorID', 'areaID', 'id', 'id');
    }

    public function services()
    {
        return $this->hasManyThrough(Service::class, Area::class, 'floorID', 'areaID', 'id', 'id');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable', 'mediableType', 'mediableID');
    }
}

