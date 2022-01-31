<?php

namespace FluentSupport\App\Modules\Reporting;

/**
 * ReportingHelperTrait is act as helper of report
 * This will help to get frequency, several date format, get advanced or backdated date and other
 * @package FluentSupport\App\Modules\Reporting
 *
 * @version 1.0.0
 */

trait ReportingHelperTrait
{
    protected static $daily = 'P1D';
    protected static $weekly = 'P1W';
    protected static $monthly = 'P1M';

    protected function db() {
        return \FluentSupport\App\App::getInstance('db');
    }

    protected function makeFromDate($from)
    {
        $from = $from ?: '-30 days';

        return new \DateTime($from);
    }

    protected function makeToDate($to = false)
    {
        $to = $to ?: '+1 days';

        return new \DateTime($to);
    }

    protected function makeDatePeriod($from, $to, $interval = null)
    {
        $interval = $interval ?: static::$daily;

        return new \DatePeriod($from, new \DateInterval($interval), $to);
    }


    /**
     * getFrequency method will return the frequency
     * this function get date from and date to as parameter and find the frequency and return
     * @param $from
     * @param $to
     * @return string
     */
    protected function getFrequency($from, $to)
    {
        $numDays = $to->diff($from)->format("%a");
        //If number of days in the range is greater than 2 month and less than or equal to 3 months
        if ($numDays > 62 && $numDays <= 181) {
            return static::$weekly;
        } else if ($numDays > 181) {
            //Greater than 3 months
            return static::$monthly;
        }

        return static::$daily;
    }

    /**
     * prepareSelect method will prepare the field to select
     * @param $frequency
     * @param string $dateField
     * @return array
     */
    protected function prepareSelect($frequency, $dateField = 'created_at')
    {
        $select = [
            $this->db()->raw('COUNT(id) AS count'),
            $this->db()->raw('DATE('.$dateField.') AS date')
        ];

        if ($frequency == static::$weekly) {
            $select[] = $this->db()->raw('WEEK(created_at) week');
        } else if ($frequency == static::$monthly) {
            $select[] = $this->db()->raw('MONTH(created_at) month');
        }

        return $select;
    }

    /**
     * getGroupAndOrder method will return order by and group by value based on frequency
     * @param $frequency
     * @return string[]
     */
    protected function getGroupAndOrder($frequency)
    {
        $orderBy = $groupBy = 'date';

        if ($frequency == static::$weekly) {
            $orderBy = $groupBy = 'week';
        } else if ($frequency == static::$monthly) {
            $orderBy = $groupBy = 'month';
        }

        return [$groupBy, $orderBy];
    }

    protected function getDateRangeArray($period)
    {
        $range = [];

        $formatter = 'basicFormatter';

        if ($this->isMonthly($period)) {
            $formatter = 'monYearFormatter';
        }

        foreach ($period as $date) {
            $date = $this->{$formatter}($date);
            $range[$date] = 0;
        }

        return $range;
    }

    protected function getResult($period, $items)
    {
        $range = $this->getDateRangeArray($period);

        $formatter = 'basicFormatter';

        if ($this->isMonthly($period)) {
            $formatter = 'monYearFormatter';
        }

        foreach ($items as $item) {
            $date = $this->{$formatter}($item->date);
            $range[$date] = (int) $item->count;
        }

        return $range;
    }

    protected function isMonthly($period)
    {
        return !!$period->getDateInterval()->m;
    }

    protected function basicFormatter($date)
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        return $date->format('Y-m-d');
    }

    protected function monYearFormatter($date)
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        return $date->format('M Y');
    }
}
