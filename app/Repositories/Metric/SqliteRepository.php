<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Repositories\Metric;

use CachetHQ\Cachet\Models\Metric;
use DateInterval;
use Illuminate\Support\Facades\DB;
use Jenssegers\Date\Date;
use Illuminate\Support\Collection;

class SqliteRepository extends AbstractMetricRepository implements MetricInterface
{
    /**
     * Returns metrics for the last hour.
     *
     * @param \CachetHQ\Cachet\Models\Metric $metric
     * @param int                            $hour
     * @param int                            $minute
     *
     * @return int
     */
    public function getPointsLastHour(Metric $metric, $minutes)
    {
        $metricPointsTableName = $this->getMetricPointsTableName();

        // Default metrics calculations.
        if (!isset($metric->calc_type) || $metric->calc_type == Metric::CALC_SUM) {
            $queryType = "sum($metricPointsTableName.value * $metricPointsTableName.counter)";
        } elseif ($metric->calc_type == Metric::CALC_AVG) {
            $queryType = "avg($metricPointsTableName.value)";
        } else {
            $queryType = "sum($metricPointsTableName.value * $metricPointsTableName.counter)";
        }

        $points = DB::select("select strftime('%H:%M', {$metricPointsTableName}.created_at) AS key, ".
                             "{$queryType} as value FROM {$this->getTableName()} m JOIN $metricPointsTableName ON ".
                             "$metricPointsTableName.metric_id = m.id WHERE m.id = :metricId ".
                             "AND strftime('%Y%m%d%H%M', {$metricPointsTableName}.created_at) >= strftime('%Y%m%d%H%M',datetime('now', 'localtime', '-{$minutes} minutes')) ".
                             "GROUP BY strftime('%H%M', {$metricPointsTableName}.created_at) ".
                             "ORDER BY {$metricPointsTableName}.created_at", [
                             'metricId'     => $metric->id]);

        $results = Collection::make($points);
        return $results->map(function ($point) use ($metric) {
            if (!$point->value) {
                $point->value = NULL;
            }
            $point->value = round($point->value, $metric->places);
            return $point;
        });
    }

    /**
     * Returns metrics for a given hour.
     *
     * @param \CachetHQ\Cachet\Models\Metric $metric
     * @param int                            $hour
     *
     * @return int
     */
    public function getPointsByHour(Metric $metric, $hour)
    {
        $dateTime = (new Date())->sub(new DateInterval('PT'.$hour.'H'));
        $metricPointsTableName = $this->getMetricPointsTableName();

        // Default metrics calculations.
        if (!isset($metric->calc_type) || $metric->calc_type == Metric::CALC_SUM) {
            $queryType = "sum($metricPointsTableName.value * $metricPointsTableName.counter)";
        } elseif ($metric->calc_type == Metric::CALC_AVG) {
            $queryType = "avg($metricPointsTableName.value * $metricPointsTableName.counter)";
        } else {
            $queryType = "sum($metricPointsTableName.value * $metricPointsTableName.counter)";
        }

        $value = NULL;
        $query = DB::select("select {$queryType} as value FROM {$this->getTableName()} m JOIN $metricPointsTableName ON $metricPointsTableName.metric_id = m.id WHERE m.id = :metricId AND strftime('%Y%m%d%H', $metricPointsTableName.created_at) = :timeInterval GROUP BY strftime('%H', $metricPointsTableName.created_at)", [
            'metricId'     => $metric->id,
            'timeInterval' => $dateTime->format('YmdH'),
        ]);

        if (isset($query[0])) {
            $value = $query[0]->value;
        }

        if (is_null($value)) {
            return NULL;
        }

        return round($value, $metric->places);
    }

    /**
     * Returns metrics for the week.
     *
     * @param \CachetHQ\Cachet\Models\Metric $metric
     *
     * @return int
     */
    public function getPointsForDayInWeek(Metric $metric, $day)
    {
        $dateTime = (new Date())->sub(new DateInterval('P'.$day.'D'));
        $metricPointsTableName = $this->getMetricPointsTableName();

        // Default metrics calculations.
        if (!isset($metric->calc_type) || $metric->calc_type == Metric::CALC_SUM) {
            $queryType = "sum($metricPointsTableName.value * $metricPointsTableName.counter)";
        } elseif ($metric->calc_type == Metric::CALC_AVG) {
            $queryType = "avg($metricPointsTableName.value * $metricPointsTableName.counter)";
        } else {
            $queryType = "sum($metricPointsTableName.value * $metricPointsTableName.counter)";
        }

        $value = NULL;
        $query = DB::select("select {$queryType} as value FROM {$this->getTableName()} m JOIN $metricPointsTableName ON $metricPointsTableName.metric_id = m.id WHERE m.id = :metricId AND $metricPointsTableName.created_at > date('now', '-7 day') AND strftime('%Y%m%d', $metricPointsTableName.created_at) = :timeInterval GROUP BY strftime('%Y%m%d', $metricPointsTableName.created_at)", [
            'metricId'     => $metric->id,
            'timeInterval' => $dateTime->format('Ymd'),
        ]);

        if (isset($query[0])) {
            $value = $query[0]->value;
        }

        if (is_null($value)) {
            return NULL;
        }

        return round($value, $metric->places);
    }
}
