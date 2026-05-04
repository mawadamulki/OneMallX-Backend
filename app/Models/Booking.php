<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'serviceID',
        'serviceItemID',
        'customerID',
        'employeeID',
        'date',
        'time',
        'entryNumber',
        'status',
        'paymentStatus',
        'totalPrice',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'serviceID');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customerID');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employeeID');
    }
}
