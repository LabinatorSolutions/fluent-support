<?php

namespace FluentSupport\App\Http\Controllers;


use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\Framework\Request\Request;

class SettingsController extends Controller
{
    public function getSettings(Request $request)
    {
        $settingsKey = $request->get('settings_key');

        return (new Settings)->get($settingsKey);
    }

    public function saveSettings(Request $request)
    {
        $settingsKey = $request->get('settings_key');
        $settings = wp_unslash($request->get('settings'));

        (new Settings)->save($settingsKey, $settings);

        return [
            'message' => __('Settings has been updated', 'fluent-support')
        ];
    }
}
