<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Service;

/**
 * ISO weekday: 1 = Monday … 7 = Sunday (Carbon {@see \Carbon\Carbon::dayOfWeekIso}).
 */
final class WorkingWeekday
{
    /** @var array<string, int> */
    private const ABBREV_TO_ISO = [
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
        'sun' => 7,
    ];

    /**
     * @return list<int>
     */
    public static function parseCsvToIso(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        $out = [];
        foreach (explode(',', $csv) as $token) {
            $key = strtolower(trim($token));
            if (isset(self::ABBREV_TO_ISO[$key])) {
                $out[self::ABBREV_TO_ISO[$key]] = self::ABBREV_TO_ISO[$key];
            }
        }

        return array_values($out);
    }

    public static function isoToAbbrev(int $iso): string
    {
        return match ($iso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
            default => '',
        };
    }

    /**
     * @param  list<int>  $isoWeekdays
     */
    public static function syncForService(Service $service, array $isoWeekdays): void
    {
        $service->workingDays()->delete();
        foreach (array_unique($isoWeekdays) as $iso) {
            $iso = (int) $iso;
            if ($iso < 1 || $iso > 7) {
                continue;
            }
            $service->workingDays()->create(['weekday' => $iso]);
        }
    }

    /**
     * @param  list<int>  $isoWeekdays
     * @param  string|null  $startsAt  time string; defaults to parent service openTime
     * @param  string|null  $endsAt  time string; defaults to parent service closeTime
     */
    public static function syncForEmployee(Employee $employee, array $isoWeekdays, ?string $startsAt = null, ?string $endsAt = null): void
    {
        $employee->loadMissing('service');
        $service = $employee->service;
        if (! $service) {
            return;
        }

        $startsAt = $startsAt ?? $service->openTime ?? '09:00:00';
        $endsAt = $endsAt ?? $service->closeTime ?? '18:00:00';

        [$startsAt, $endsAt] = ServiceEmployeeSchedule::clampEmployeeIntervalToService($service, $startsAt, $endsAt);

        $employee->workingDays()->delete();
        foreach (array_unique($isoWeekdays) as $iso) {
            $iso = (int) $iso;
            if ($iso < 1 || $iso > 7) {
                continue;
            }
            $employee->workingDays()->create([
                'weekday' => $iso,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        }
    }

    public static function syncServiceFromLegacyCsv(Service $service, ?string $csv): void
    {
        self::syncForService($service, self::parseCsvToIso($csv));
    }

    public static function syncEmployeeFromLegacyCsv(Employee $employee, ?string $csv): void
    {
        self::syncForEmployee($employee, self::parseCsvToIso($csv), null, null);
    }
}
