<?php

namespace App\DAO;

use Carbon\Carbon;

interface AdminAnalyticsInterface
{
    public function getDashboardData(Carbon $from, Carbon $to): array;
}
