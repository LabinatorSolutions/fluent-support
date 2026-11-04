<?php

namespace FluentSupport\App\Modules;

/**
 * Registry of notification integrations and their settings.
 */
class IntegrationSettingsModule
{
    private static $integrations = [];

    /**
     * @param string $integrationKey
     * @param bool $withFields
     * @return mixed|false False when the integration isn't registered.
     */
    public static function getSettings($integrationKey, $withFields = false)
    {
        $integrationClasses = self::$integrations;

        $class = (isset($integrationClasses[$integrationKey])) ? $integrationClasses[$integrationKey] : false;

        if(!$class) {
            return false;
        }

        return $class->getSettings($withFields);
    }

    /**
     * @param string $integrationKey
     * @param array $settings
     * @return mixed|false False when the integration isn't registered.
     */
    public static function saveSettings($integrationKey, $settings)
    {
        $integrationClasses = self::$integrations;

        $class = (isset($integrationClasses[$integrationKey])) ? $integrationClasses[$integrationKey] : false;

        if(!$class) {
            return false;
        }

        return $class->saveSettings($settings);
    }

    /**
     * @param \FluentSupport\App\Services\Integrations\NotificationIntegrationBase $class
     */
    public static function addIntegration($class)
    {
        self::$integrations[$class->getKey()] = $class;
    }

}
