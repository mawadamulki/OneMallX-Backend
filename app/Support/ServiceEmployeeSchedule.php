<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Service;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Intersects the service's single daily open/close window with an employee's per-weekday interval.
 */
final class ServiceEmployeeSchedule
{
    /**
     * @return array{0: Carbon, 1: Carbon}|null [windowStart, windowEnd] on $date's calendar day
     */
    public static function intersectionForDate(Service $service, Employee $employee, Carbon $date): ?array
    {
        $iso = (int) $date->dayOfWeekIso;

        if (! $service->isOpenOnWeekdayForDisplay($iso)) {
            return null;
        }

        $empDay = $employee->workingDays->firstWhere('weekday', $iso);
        if ($empDay === null) {
            return null;
        }

        $day = $date->format('Y-m-d');

        $serviceStart = Carbon::parse($day.' '.self::normalizeTimeString($service->openTime));
        $serviceEnd = Carbon::parse($day.' '.self::normalizeTimeString($service->closeTime));

        $empStart = Carbon::parse($day.' '.self::normalizeTimeString($empDay->starts_at));
        $empEnd = Carbon::parse($day.' '.self::normalizeTimeString($empDay->ends_at));

        $start = $serviceStart->greaterThan($empStart) ? $serviceStart : $empStart;
        $end = $serviceEnd->lessThan($empEnd) ? $serviceEnd : $empEnd;

        if ($start >= $end) {
            return null;
        }

        return [$start, $end];
    }

    public static function canOfferOnDate(Service $service, Employee $employee, Carbon $date): bool
    {
        return self::intersectionForDate($service, $employee, $date) !== null;
    }

    /**
     * Booking validation window (more permissive than display when schedules are missing).
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public static function intersectionForBooking(Service $service, Employee $employee, Carbon $date): ?array
    {
        $iso = (int) $date->dayOfWeekIso;

        if (! $service->allowsBookingOnWeekday($iso)) {
            return null;
        }

        if ($employee->hasWorkingDaySchedule() && ! $employee->worksOnIsoWeekday($iso)) {
            return null;
        }

        if (! self::hasValidServiceWindow($service->openTime, $service->closeTime)) {
            return null;
        }

        $day = $date->format('Y-m-d');

        $serviceStart = Carbon::parse($day.' '.self::normalizeTimeString($service->openTime));
        $serviceEnd = Carbon::parse($day.' '.self::normalizeTimeString($service->closeTime));

        if (! $employee->hasWorkingDaySchedule()) {
            return [$serviceStart, $serviceEnd];
        }

        $empDay = $employee->workingDays->firstWhere('weekday', $iso);
        if ($empDay === null) {
            return null;
        }

        $empStart = Carbon::parse($day.' '.self::normalizeTimeString($empDay->starts_at));
        $empEnd = Carbon::parse($day.' '.self::normalizeTimeString($empDay->ends_at));

        $start = $serviceStart->greaterThan($empStart) ? $serviceStart : $empStart;
        $end = $serviceEnd->lessThan($empEnd) ? $serviceEnd : $empEnd;

        if ($start >= $end) {
            return null;
        }

        return [$start, $end];
    }

    /**
     * Human-readable reason when a booking slot is rejected.
     */
    public static function bookingRejectionReason(
        Service $service,
        Employee $employee,
        string $dateYmd,
        string $timeHi,
        int $durationMinutes
    ): ?string {
        $date = Carbon::parse($dateYmd);
        $iso = (int) $date->dayOfWeekIso;

        if (! $service->allowsBookingOnWeekday($iso)) {
            return 'Service is closed on this day';
        }

        if ($employee->hasWorkingDaySchedule() && ! $employee->worksOnIsoWeekday($iso)) {
            return 'Employee is not working on this day';
        }

        if (! self::hasValidServiceWindow($service->openTime, $service->closeTime)) {
            return 'Service opening hours are not configured';
        }

        $intersection = self::intersectionForBooking($service, $employee, $date);
        if ($intersection === null) {
            return 'No overlapping working hours for this employee on this day';
        }

        [$windowStart, $windowEnd] = $intersection;

        $bookingStart = Carbon::parse($dateYmd.' '.self::normalizeTimeString($timeHi));
        $bookingEnd = (clone $bookingStart)->addMinutes($durationMinutes);

        if ($bookingStart < $windowStart) {
            return 'Booking starts before working hours (opens at '.$windowStart->format('H:i').')';
        }

        if ($bookingEnd > $windowEnd) {
            return 'Booking ends after working hours (closes at '.$windowEnd->format('H:i').')';
        }

        return null;
    }

    /**
     * True if booking fits fully inside the intersected window for that employee on that date.
     */
    public static function bookingFitsWindow(
        Service $service,
        Employee $employee,
        string $dateYmd,
        string $timeHi,
        int $durationMinutes
    ): bool {
        return self::bookingRejectionReason($service, $employee, $dateYmd, $timeHi, $durationMinutes) === null;
    }

    public static function normalizeTimeString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $s = trim((string) $value);
        if ($s === '') {
            return '00:00:00';
        }

        if (preg_match('/(?:\d{4}-\d{2}-\d{2}[ T])?(\d{1,2}):(\d{2})(?::(\d{2}))?/', $s, $matches)) {
            $seconds = $matches[3] ?? '00';

            return sprintf('%02d:%02d:%s', (int) $matches[1], (int) $matches[2], $seconds);
        }

        if (strlen($s) === 5) {
            return $s.':00';
        }

        return $s;
    }

    /**
     * Format a stored time value for API responses (HH:MM).
     */
    public static function formatTimeForApi(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $normalized = self::normalizeTimeString($value);

        return substr($normalized, 0, 5);
    }

    /**
     * @return array{0: string, 1: string} H:i:s strings for DB time columns
     */
    private static function normalizedEmployeeInterval(string $startsAt, string $endsAt): array
    {
        return [
            self::normalizeTimeString($startsAt),
            self::normalizeTimeString($endsAt),
        ];
    }

    private static function hasValidServiceWindow(mixed $openTime, mixed $closeTime): bool
    {
        if ($openTime === null || $closeTime === null) {
            return false;
        }

        if (trim((string) $openTime) === '' || trim((string) $closeTime) === '') {
            return false;
        }

        $openCarbon = Carbon::parse('1970-01-01 '.self::normalizeTimeString($openTime));
        $closeCarbon = Carbon::parse('1970-01-01 '.self::normalizeTimeString($closeTime));

        return $openCarbon->lessThan($closeCarbon);
    }

    /**
     * Clamp employee interval to service open/close (same for every service day).
     *
     * @return array{0: string, 1: string} H:i or H:i:s strings for DB time columns
     */
    public static function clampEmployeeIntervalToService(Service $service, string $startsAt, string $endsAt): array
    {
        if (! self::hasValidServiceWindow($service->openTime, $service->closeTime)) {
            return self::normalizedEmployeeInterval($startsAt, $endsAt);
        }

        $open = Carbon::parse('1970-01-01 '.self::normalizeTimeString($service->openTime));
        $close = Carbon::parse('1970-01-01 '.self::normalizeTimeString($service->closeTime));
        $a = Carbon::parse('1970-01-01 '.self::normalizeTimeString($startsAt));
        $b = Carbon::parse('1970-01-01 '.self::normalizeTimeString($endsAt));

        if ($a->greaterThanOrEqualTo($b)) {
            return self::normalizedEmployeeInterval($startsAt, $endsAt);
        }

        $start = $a->greaterThan($open) ? $a : $open;
        $end = $b->lessThan($close) ? $b : $close;

        if ($start->greaterThanOrEqualTo($end)) {
            return self::normalizedEmployeeInterval($startsAt, $endsAt);
        }

        return [$start->format('H:i:s'), $end->format('H:i:s')];
    }
}
