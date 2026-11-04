<?php

namespace FluentSupport\App\Modules\TicketImport;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Modules\TicketImport\Importers\BaseImporter;
use FluentSupport\Framework\Http\Request\Request;

/**
 * Import tickets from other helpdesks: WordPress plugins (Awesome Support,
 * SupportCandy, JS Help Desk) and cloud services (Help Scout, Freshdesk, Zendesk).
 * The screen calls import page by page until has_more is false.
 */
class ImportController extends Controller
{
    /**
     * The sources that can be imported here. The plugin ones only while the plugin is active.
     */
    public function stats()
    {
        $handlers = array_merge(
            defined('WPAS_VERSION') ? ['awesome-support'] : [],
            defined('WPSC_VERSION') ? ['support-candy'] : [],
            defined('JSST_PLUGIN_PATH') ? ['js-helpdesk'] : [],
            ['helpscout', 'freshdesk', 'zendesk']
        );

        return [
            'stats' => array_map(function ($handler) {
                return BaseImporter::make($handler)->stats();
            }, $handlers)
        ];
    }

    public function import(Request $request)
    {
        $handler = $request->getSafe('handler', 'sanitize_key');
        $query = (array) $request->get('query', []);

        $importer = BaseImporter::make($handler, array_filter([
            'access_token'     => isset($query['access_token']) ? sanitize_text_field($query['access_token']) : null,
            'mailbox'          => isset($query['mailbox']) ? intval($query['mailbox']) : null,
            'domain'           => isset($query['domain']) ? sanitize_text_field($query['domain']) : null,
            'email'            => isset($query['email']) ? sanitize_email($query['email']) : null,
            'cursor'           => isset($query['cursor']) ? sanitize_text_field($query['cursor']) : null,
            'include_archived' => !empty($query['include_archived']) ? true : null,
        ], function ($value) {
            return $value !== null;
        }));

        return $importer->doMigration($request->getSafe('page', 'intval'), $handler);
    }

    /**
     * Delete the source plugin's own tickets after an import (Awesome Support,
     * SupportCandy, JS Help Desk). Does nothing for cloud sources.
     */
    public function deleteSourceTickets(Request $request)
    {
        return BaseImporter::make($request->getSafe('handler', 'sanitize_key'))->deleteTickets($request->getSafe('page', 'intval'));
    }

    /**
     * Swap a HelpScout authorization code for an access token. Done here so the
     * App Secret goes to HelpScout in a server request body, never in a browser URL.
     *
     * @param Request $request app_id, app_secret, code.
     * @return array access_token, refresh_token, token_type, expires_in.
     * @throws ApiException
     */
    public function helpScoutToken(Request $request)
    {
        $input = $request->getSafe([
            'app_id'     => 'sanitize_text_field',
            'app_secret' => 'sanitize_text_field',
            'code'       => 'sanitize_text_field',
        ]);

        if (!$input['app_id'] || !$input['app_secret'] || !$input['code']) {
            throw ApiException::invalid(__('App ID, App Secret and Authorization Code are required.', 'fluent-support'));
        }

        $response = wp_remote_post('https://api.helpscout.net/v2/oauth2/token', [
            'timeout' => 30,
            'body'    => [
                'grant_type'    => 'authorization_code',
                'code'          => $input['code'],
                'client_id'     => $input['app_id'],
                'client_secret' => $input['app_secret'],
            ],
        ]);

        if (is_wp_error($response)) {
            throw ApiException::invalid($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200 || empty($body['access_token'])) {
            throw ApiException::invalid(
                (is_array($body) && !empty($body['error_description'])) ? sanitize_text_field($body['error_description']) : __('HelpScout did not return an access token.', 'fluent-support')
            );
        }

        return [
            'access_token'  => sanitize_text_field($body['access_token']),
            'refresh_token' => sanitize_text_field($body['refresh_token'] ?? ''),
            'token_type'    => sanitize_text_field($body['token_type'] ?? ''),
            'expires_in'    => (int) ($body['expires_in'] ?? 0),
        ];
    }
}
