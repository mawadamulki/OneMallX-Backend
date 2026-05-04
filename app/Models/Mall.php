<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mall extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'country',
        'mallOwnerID',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'mallOwnerID');
    }

    public function floors()
    {
        return $this->hasMany(Floor::class, 'mallID');
    }
}

