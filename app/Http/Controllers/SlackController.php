<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Modules\IntegrationSettingsModule;
use FluentSupport\Framework\Request\Request;

class SlackController extends Controller
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
            $errorMessage = (is_wp_error($settings)) ? $settings->get_error_message() : 'Settings failed to save';
            return $this->sendError([
                'message' => $errorMessage
            ]);
        }

        return [
            'message' => __('Settings has been updated', 'fluent-support'),
            'settings' => $settings
        ];
    }
}
