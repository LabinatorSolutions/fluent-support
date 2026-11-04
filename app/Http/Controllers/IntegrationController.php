<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Modules\IntegrationSettingsModule;
use FluentSupport\Framework\Http\Request\Request;

/**
 * Settings of the notification integrations an add-on registers
 * (IntegrationSettingsModule::addIntegration), by integration_key.
 */
class IntegrationController extends Controller
{
    public function getSettings(Request $request)
    {
        $settings = IntegrationSettingsModule::getSettings($request->getSafe('integration_key', 'sanitize_text_field'), true);
        if ($settings === false) {
            throw ApiException::notFound();
        }

        return $settings;
    }

    /**
     * Every value is plain text. The integration may refuse (an error) or
     * adjust what it saves (switched off without its credentials).
     */
    public function saveSettings(Request $request)
    {
        $settings = map_deep((array) $request->get('settings', []), 'sanitize_text_field');
        $settings = IntegrationSettingsModule::saveSettings($request->getSafe('integration_key', 'sanitize_text_field'), $settings);

        if (is_wp_error($settings)) {
            throw ApiException::invalid($settings->get_error_message());
        }
        if (!$settings) {
            throw ApiException::invalid(__('Settings failed to save', 'fluent-support'));
        }

        return [
            'message'  => __('Settings has been updated', 'fluent-support'),
            'settings' => $settings
        ];
    }
}
