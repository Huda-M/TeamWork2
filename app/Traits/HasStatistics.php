<?php

namespace App\Traits;

use Carbon\Carbon;

trait HasStatistics
{

    public function updateStatistics(): void
    {
    }

    public function getPerformanceReport(): array
    {
        return [];
    }

    public function getPerformanceTrend($days = 7): string
    {
        return 'stable';
    }

    public function isActiveInPeriod($startDate, $endDate): bool
    {
        return true;
    }

    protected function calculateCompletionRate($completed, $total): float
    {
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    protected function calculateEfficiencyRate($estimated, $actual): float
    {
        if ($estimated === 0) {
            return 0;
        }

        $efficiency = (($estimated - max(0, $actual - $estimated)) / $estimated) * 100;
        return round(max(0, min(100, $efficiency)), 2);
    }
}
