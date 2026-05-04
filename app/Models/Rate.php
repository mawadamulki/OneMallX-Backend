<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'userID',
        'rateableType',
        'rateableID',
        'score',
        'comment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userID');
    }

    public function rateable()
    {
        return $this->morphTo(__FUNCTION__, 'rateableType', 'rateableID');
    }
}

