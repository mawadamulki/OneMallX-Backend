<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Advertisement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'storeID',
        'serviceID',
        'title',
        'image',
        'targetType',
        'targetID',
        'placement',
        'startDate',
        'endDate',
    ];

    protected function casts(): array
    {
        return [
            'startDate' => 'date',
            'endDate' => 'date',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'serviceID');
    }
}
