<?php

namespace App\DAO;

use Carbon\Carbon;

interface AdminAnalyticsInterface
{
    public function getDashboardData(Carbon $from, Carbon $to): array;

    public function getOverviewStats(Carbon $monthStart, Carbon $monthEnd): array;
}
