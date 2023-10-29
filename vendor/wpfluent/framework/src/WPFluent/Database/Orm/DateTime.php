<?php

namespace FluentSupport\Framework\Database\Orm;

use DateTimeZone;
use DateTime as PHPDateTime;

Class DateTime extends PHPDateTime
{
    public function __construct($datetime = "now", $timezone = null)
    {
        $timezone = $timezone ?: $this->getTimezone();

        parent::__construct($datetime, $timezone);
    }

    #[\ReturnTypeWillChange]
    public function getTimezone()
    {
        // if site timezone string exists, return it
        if ($timezone = wp_timezone_string()) {
            return new DateTimeZone($timezone);
        }

        // get UTC offset, if it isn't set then return UTC
        $utcOffset = get_option('gmt_offset', 0);
        if ($utcOffset === 0) {
            return new DateTimeZone('UTC');
        }

        // Adjust UTC offset from hours to seconds
        $utcOffset *= 3600;

        // Attempt to guess the timezone string from the UTC offset
        $timezone = timezone_name_from_abbr('', $utcOffset, 0);
        if ($timezone) {
            return new DateTimeZone($timezone);
        }

        // Guess timezone string manually
        $isDst = date('I');
        foreach (timezone_abbreviations_list() as $abbr) {
            foreach ($abbr as $city) {
                if ($city['dst'] == $isDst && $city['offset'] == $utcOffset) {
                    $timezoneId = $city['timezone_id'];
                    $timezone = $timezoneId ?: timezone_name_from_abbr('', $timezoneId, 0);
                    if ($timezone) return new DateTimeZone($timezone);
                }
            }
        }

        // Fallback
        return new DateTimeZone('UTC');
    }

    public function getDateFormat()
    {   
        return 'Y-m-d H:i:s';
    }

    #[\ReturnTypeWillChange]
    public static function createFromFormat($format, $datetimeString, $timezone = null)
    {
        $timezone = $timezone ?: (new static)->getTimezone();

        $dateTime = new \DateTime($datetimeString, $timezone);

        if ($dateTime instanceof \DateTime) {
            return new static($dateTime->format($format), $timezone);
        }

        throw new \InvalidArgumentException('Unable to handle datetime.');
    }

    /**
     * Return the ISO-8601 string
     *
     * @see https://stackoverflow.com/a/11173072/741747
     *
     * @return mixed
     */
    public function toJSON()
    {
        return date('c', $this->getTimestamp());
    }

    public function __toString()
    {
        return $this->format($this->getDateFormat());
    }
}
