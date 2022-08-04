<?php

/**
 * Add only the plugin specific bindings here.
 * 
 * $app
 * @var WPFluent\Foundation\Application
 */

$app->singleton(\FluentSupport\App\Services\ProfileInfoService::class, function($app) {
    return new \FluentSupport\App\Services\ProfileInfoService();
});