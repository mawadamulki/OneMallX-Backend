<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copy legacy `daysOfWeek` CSV from `services` and `employees` into the new tables, then drop those columns.
     */
    public function up(): void
    {
        /** @var array<string, int> ISO weekday: 1 = Monday … 7 = Sunday */
        $abbrevToIso = [
            'mon' => 1,
            'tue' => 2,
            'wed' => 3,
            'thu' => 4,
            'fri' => 5,
            'sat' => 6,
            'sun' => 7,
        ];

        $now = now();

        foreach (DB::table('services')->select('id', 'daysOfWeek')->cursor() as $row) {
            if ($row->daysOfWeek === null || trim((string) $row->daysOfWeek) === '') {
                continue;
            }
            foreach (explode(',', $row->daysOfWeek) as $token) {
                $key = strtolower(trim($token));
                if (! isset($abbrevToIso[$key])) {
                    continue;
                }
                DB::table('service_working_days')->insertOrIgnore([
                    'service_id' => $row->id,
                    'weekday' => $abbrevToIso[$key],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (DB::table('employees')
            ->select('employees.id', 'employees.daysOfWeek', 'services.openTime', 'services.closeTime')
            ->join('services', 'employees.serviceID', '=', 'services.id')
            ->cursor() as $row) {
            if ($row->daysOfWeek === null || trim((string) $row->daysOfWeek) === '') {
                continue;
            }
            foreach (explode(',', $row->daysOfWeek) as $token) {
                $key = strtolower(trim($token));
                if (! isset($abbrevToIso[$key])) {
                    continue;
                }
                DB::table('employee_working_days')->insertOrIgnore([
                    'employee_id' => $row->id,
                    'weekday' => $abbrevToIso[$key],
                    'starts_at' => $row->openTime,
                    'ends_at' => $row->closeTime,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('daysOfWeek');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('daysOfWeek');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('daysOfWeek')->nullable();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('daysOfWeek')->nullable();
        });

        // Legacy CSV is not reconstructed from `service_working_days` / `employee_working_days`.
    }
};
