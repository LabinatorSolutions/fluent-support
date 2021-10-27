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


        return $class->getSettings($withFields);
    }

    public static function saveSettings($integrationKey, $settings)
    {
        $integrationClasses = self::$integrations;

        $class = (isset($integrationClasses[$integrationKey])) ? $integrationClasses[$integrationKey] : false;

        if(!$class) {
            return false;
        }

        return $class->saveSettings($settings);
    }

    public static function addIntegration($class)
    {
        self::$integrations[$class->getKey()] = $class;
    }

}
