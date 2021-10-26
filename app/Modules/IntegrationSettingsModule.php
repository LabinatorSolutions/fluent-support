<?php

namespace FluentSupport\App\Modules;

class IntegrationSettingsModule
{
    private static $integrations = [];

    public static function getSettings($integrationKey, $withFields = false)
    {
        $integrationClasses = self::$integrations;

        $class = (isset($integrationClasses[$integrationKey])) ? $integrationClasses[$integrationKey] : false;

        if(!$class) {
            return false;
        }

        $instance = new $class();

        return $instance->getSettings($withFields);
    }

    public static function saveSettings($integrationKey, $settings)
    {
        $integrationClasses = self::$integrations;

        $class = (isset($integrationClasses[$integrationKey])) ? $integrationClasses[$integrationKey] : false;

        if(!$class) {
            return false;
        }

        $instance = new $class();

        return $instance->saveSettings($settings);
    }

    public static function addIntegration($key, $className)
    {
        self::$integrations[$key] = $className;
    }

}
