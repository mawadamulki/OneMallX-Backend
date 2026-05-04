<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Service;
use Carbon\Carbon;

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
     * True if booking fits fully inside the intersected window for that employee on that date.
     */
    public static function bookingFitsWindow(
        Service $service,
        Employee $employee,
        string $dateYmd,
        string $timeHi,
        int $durationMinutes
    ): bool {
        $intersection = self::intersectionForDate($service, $employee, Carbon::parse($dateYmd));
        if ($intersection === null) {
            return false;
        }

        [$windowStart, $windowEnd] = $intersection;

        $bookingStart = Carbon::parse($dateYmd.' '.self::normalizeTimeString($timeHi));
        $bookingEnd = (clone $bookingStart)->addMinutes($durationMinutes);

        return $bookingStart >= $windowStart && $bookingEnd <= $windowEnd;
    }

    public static function normalizeTimeString(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        $s = trim((string) $value);
        if ($s === '') {
            return '00:00:00';
        }

        if (strlen($s) === 5) {
            return $s.':00';
        }

        return $s;
    }

    /**
     * Clamp employee interval to service open/close (same for every service day).
     *
     * @return array{0: string, 1: string} H:i or H:i:s strings for DB time columns
     */
    public static function clampEmployeeIntervalToService(Service $service, string $startsAt, string $endsAt): array
    {
        $open = Carbon::parse('1970-01-01 '.self::normalizeTimeString($service->openTime));
        $close = Carbon::parse('1970-01-01 '.self::normalizeTimeString($service->closeTime));
        $a = Carbon::parse('1970-01-01 '.self::normalizeTimeString($startsAt));
        $b = Carbon::parse('1970-01-01 '.self::normalizeTimeString($endsAt));

        $start = $a->greaterThan($open) ? $a : $open;
        $end = $b->lessThan($close) ? $b : $close;

        if ($start >= $end) {
            return [$open->format('H:i:s'), $close->format('H:i:s')];
        }

        return [$start->format('H:i:s'), $end->format('H:i:s')];
    }
}
