<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Icon extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'url',
        'sortOrder',
        'isActive',
    ];

    protected function casts(): array
    {
        return [
            'sortOrder' => 'integer',
            'isActive' => 'boolean',
        ];
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'iconID');
    }

    public function scopeActive($query)
    {
        return $query->where('isActive', true);
    }
}
