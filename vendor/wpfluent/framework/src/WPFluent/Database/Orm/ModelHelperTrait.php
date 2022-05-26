<?php

namespace FluentSupport\Framework\Database\Orm;

trait ModelHelperTrait
{
    public static function classBasename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }

    public static function classUsesRecursive($class)
    {
        $results = [];

        foreach (array_merge([$class => $class], class_parents($class)) as $class) {
            if ($class != get_class()) {
                $results += static::traitUsesRecursive($class);
            }
        }

        return array_unique($results);
    }

    public static function traitUsesRecursive($trait)
    {
        $traits = class_uses($trait);

        foreach ($traits as $trait) {
            $traits += static::traitUsesRecursive($trait);
        }

        return $traits;
    }

    public function getTimezone()
    {
        return wp_timezone();
    }

    public function createFromTimestamp($value = null)
    {
        $value = is_null($value) ? "now" : "@$value";
        $date = new \DateTime($value, $this->getTimezone());
        return $date->format($this->getDateFormat());
    }

    public function createFromFormat($value, $format = null)
    {
        $date = new \DateTime($value, $this->getTimezone());
        return $date->format($format ?: $this->getDateFormat());
    }

    public function createFromDateString($value)
    {
        $date = new \DateTime($value, $this->getTimezone());

        return $date->setTime('00', '00', '00', '000000')->format('Y-m-d');
    }

    public function newDateTimeString($value = null)
    {
        $value = is_null($value) ? "now" : "@$value";
        $date = new \DateTime($value, $this->getTimezone());
        return $date->format('Y-m-d H:i:s.u');
    }
}
