<?php

namespace App\Models;

use App\Support\ServiceEmployeeSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'serviceID',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'serviceID');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'employeeID');
    }

    public function workingDays(): HasMany
    {
        return $this->hasMany(EmployeeWorkingDay::class, 'employee_id');
    }

    public function worksOnIsoWeekday(int $iso): bool
    {
        if ($this->relationLoaded('workingDays')) {
            return $this->workingDays->contains('weekday', $iso);
        }

        return $this->workingDays()->where('weekday', $iso)->exists();
    }

    public function hasWorkingDaySchedule(): bool
    {
        return $this->relationLoaded('workingDays')
            ? $this->workingDays->isNotEmpty()
            : $this->workingDays()->exists();
    }

    public function isWorkingOnWeekdayForDisplay(int $isoWeekday): bool
    {
        if (! $this->hasWorkingDaySchedule()) {
            return false;
        }

        return $this->worksOnIsoWeekday($isoWeekday);
    }

    /**
     * Employee shift overlaps the service window on this calendar day (after intersecting intervals).
     */
    public function canOfferServiceOnDate(Service $service, Carbon $date): bool
    {
        return ServiceEmployeeSchedule::canOfferOnDate($service, $this, $date);
    }

    public function allowsBookingOnWeekday(int $isoWeekday): bool
    {
        if (! $this->hasWorkingDaySchedule()) {
            return true;
        }

        return $this->worksOnIsoWeekday($isoWeekday);
    }
}
