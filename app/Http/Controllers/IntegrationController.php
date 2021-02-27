<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Modules\IntegrationSettingsModule;
use FluentSupport\Framework\Request\Request;

class IntegrationController extends Controller
{
    public function getSettings(Request $request)
    {
        $settingsKey = $request->get('integration_key');

        return IntegrationSettingsModule::getSettings($settingsKey, true);
    }

    public function saveSettings(Request $request)
    {
        $settingsKey = $request->get('integration_key');
        $settings = wp_unslash($request->get('settings'));

        $settings = IntegrationSettingsModule::saveSettings($settingsKey, $settings);

        if(!$settings || is_wp_error($settings)) {
            return $this->sendError([
                'message' => 'Settings failed to save',
                'errors' => (is_wp_error($settings)) ? $settings->get_error_message() : ''
            ]);
        }

        return [
            'message' => __('Settings has been updated', 'fluent-support'),
            'settings' => $settings
        ];
    }
}
