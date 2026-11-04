<?php
/**
 * Settings endpoints (settings/*, AdminSettingsPolicy).
 *
 * - settings reads and saves the settings pages: global business settings,
 *   in-app notifications and FluentCRM. Only those keys can be saved; values
 *   are plain text except the login message (allowed HTML).
 * - setup (the onboarding wizard) creates the first mailbox and sets the
 *   portal page; it needs a page or "create page".
 * - reCAPTCHA, AI provider, remote upload, integration lists and the settings
 *   menu answer the shapes their pages read.
 *
 * Outgoing HTTP (reCAPTCHA verify, the opt-in) is faked.
 */

use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Integrations\AI\AIProviderFactory;

return function () {
    $adminId = get_current_user_id();
    $userIds = [];
    $options = ['global_business_settings', '_internal_notification_settings', '_fluentcrm_intergration_settings', 'auto_close_settings', '_share_essential', 'storage_async_upload', 'fs_test_settings_key'];
    $saved = [];
    foreach ($options as $key) {
        $saved[$key] = Meta::where('object_type', 'option')->where('key', $key)->value('value');
    }
    $savedMeta = [];
    foreach (['_fs_recaptcha_settings', '_fs_ai_provider_settings'] as $type) {
        $savedMeta[$type] = Meta::where('object_type', $type)->get(['key', 'value'])->toArray();
    }
    $pageIds = [];

    $rest = function ($method, $route, array $params = []) {
        $result = FsTest::rest($method, $route, $params);
        $result['data'] = json_decode(wp_json_encode($result['data']), true);
        return $result;
    };
    $http = function (callable $fn, $answer = ['success' => true]) {
        $hits = [];
        $fake = function ($pre, $args, $url) use (&$hits, $answer) {
            $hits[] = ['url' => $url, 'body' => $args['body'] ?? null];
            return ['headers' => [], 'body' => wp_json_encode($answer), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
        };
        add_filter('pre_http_request', $fake, 10, 3);
        try {
            $fn();
        } finally {
            remove_filter('pre_http_request', $fake, 10);
        }
        return $hits;
    };

    try {
        FsTest::case('settings: the settings pages read their settings and fields', function () use ($rest) {
            $global = $rest('GET', '/settings', ['settings_key' => 'global_business_settings'])['data'];
            FsTest::assert(isset($global['settings']['portal_page_id'], $global['settings']['accepted_file_types'], $global['fields']), 'global business settings');
            $notifications = $rest('GET', '/settings', ['settings_key' => '_internal_notification_settings'])['data'];
            FsTest::assert(isset($notifications['settings'], $notifications['fields']), 'in-app notifications');
            FsTest::assertSame(404, $rest('GET', '/settings', ['settings_key' => 'auto_close_settings'])['status'], 'another key is unknown here');
        });

        FsTest::case('settings: global settings save plain text, the login message keeps its HTML, the serial floor is 1', function () use ($rest) {
            $current = $rest('GET', '/settings', ['settings_key' => 'global_business_settings'])['data']['settings'];
            $result = $rest('POST', '/settings', ['settings_key' => 'global_business_settings', 'settings' => array_merge($current, [
                'login_message'     => '<p>Hi <strong>there</strong></p><script>alert(1)</script>',
                'ticket_prefix'     => '<b>TK</b>',
                'min_serial_number' => '-5',
            ])]);
            FsTest::assertHealthy($result, 'save');
            FsTest::assertSame('Settings has been updated', $result['message'], 'message');
            $stored = Helper::getOption('global_business_settings');
            FsTest::assertSame('<p>Hi <strong>there</strong></p>alert(1)', $stored['login_message'], 'login message: allowed HTML only');
            FsTest::assertSame(['TK', 1], [$stored['ticket_prefix'], $stored['min_serial_number']], 'prefix plain, serial floor');
        });

        FsTest::case('settings: only the settings pages\' keys can be saved', function () use ($rest) {
            Helper::updateOption('auto_close_settings', ['enabled' => 'no']);
            FsTest::assertSame(422, $rest('POST', '/settings', ['settings_key' => 'auto_close_settings', 'settings' => ['enabled' => 'yes', 'evil' => 'x']])['status'], 'another feature\'s option refused');
            FsTest::assertSame(['enabled' => 'no'], Helper::getOption('auto_close_settings'), 'left alone');
            $rest('POST', '/settings', ['settings_key' => 'fs_test_settings_key', 'settings' => ['a' => 'b']]);
            FsTest::assertSame(0, Meta::where('object_type', 'option')->where('key', 'fs_test_settings_key')->count(), 'no new option');
        });

        FsTest::case('settings: FluentCRM settings keep only their fields', function () use ($rest) {
            $result = $rest('POST', '/settings', ['settings_key' => '_fluentcrm_intergration_settings', 'settings' => ['enabled' => 'yes', 'default_status' => 'pending', 'assigned_list' => '3', 'assigned_tags' => ['4', 'x5'], 'evil' => 'x']]);
            FsTest::assertHealthy($result, 'save');
            FsTest::assertSame(['enabled' => 'yes', 'default_status' => 'pending', 'assigned_list' => 3, 'assigned_tags' => [4, 5]], Helper::getOption('_fluentcrm_intergration_settings'), 'stored');
            $rest('POST', '/settings', ['settings_key' => '_fluentcrm_intergration_settings', 'settings' => ['enabled' => 'maybe', 'default_status' => 'other']]);
            FsTest::assertSame(['enabled' => 'no', 'default_status' => 'subscribed', 'assigned_list' => 0, 'assigned_tags' => []], Helper::getOption('_fluentcrm_intergration_settings'), 'unknown values fall back');

            $page = $rest('GET', '/settings/fluentcrm-settings')['data'];
            FsTest::assertSame(defined('FLUENTCRM'), $page['is_installed'], 'is_installed');
            FsTest::assert(isset($page['fluentcrm_logo']), 'logo');
        });

        FsTest::case('settings: setup sets the portal page, creates a page on request and keeps the existing mailbox', function () use ($rest, &$pageIds) {
            $mailbox = ['name' => 'Support', 'email' => 'support@example.test', 'box_type' => 'web'];
            FsTest::assertSame(422, $rest('POST', '/settings/setup', ['mailbox' => ['name' => '', 'email' => 'nope', 'box_type' => 'web'], 'global_settings' => ['portal_page_id' => 1]])['status'], 'mailbox fields checked');

            $none = $rest('POST', '/settings/setup', ['mailbox' => $mailbox, 'global_settings' => ['create_portal_page' => 'no', 'portal_page_id' => 0]]);
            FsTest::assertSame([422, 'Please select a page or enable create page'], [$none['status'], $none['message']], 'a page is needed');

            $before = MailBox::count();
            $pageId = wp_insert_post(['post_title' => 'FS portal', 'post_type' => 'page', 'post_status' => 'publish']);
            $pageIds[] = $pageId;
            $result = $rest('POST', '/settings/setup', ['mailbox' => $mailbox, 'global_settings' => ['create_portal_page' => 'no', 'portal_page_id' => $pageId]]);
            FsTest::assertHealthy($result, 'setup');
            FsTest::assertSame(['mailbox', 'global_settings', 'mailboxes', 'has_fluentform'], array_keys($result['data']), 'keys');
            FsTest::assertSame((int) $pageId, (int) Helper::getOption('global_business_settings')['portal_page_id'], 'portal page saved');
            FsTest::assertSame($before, MailBox::count(), 'existing mailbox kept');

            $created = $rest('POST', '/settings/setup', ['mailbox' => $mailbox, 'global_settings' => ['create_portal_page' => 'yes']]);
            $newPage = get_post((int) Helper::getOption('global_business_settings')['portal_page_id']);
            $pageIds[] = $newPage ? $newPage->ID : 0;
            FsTest::assertHealthy($created, 'setup with a new page');
            FsTest::assert($newPage && strpos($newPage->post_content, '[fluent_support_portal]') !== false && $newPage->ID != $pageId, 'new portal page');
        });

        FsTest::case('settings: the install step saves data sharing and sends the opt-in', function () use ($rest, $http) {
            $result = null;
            $hits = $http(function () use ($rest, &$result) {
                $result = $rest('POST', '/settings/setup-installation', ['share_essentials' => 'yes', 'optin_email' => 'owner@example.test', 'install_fluentform' => 'no']);
            });
            FsTest::assertSame('Installation has been completed', $result['message'], 'message');
            FsTest::assertSame('yes', Helper::getOption('_share_essential'), 'data sharing saved');
            FsTest::assertSame(1, count(array_filter($hits, fn($hit) => strpos($hit['url'], 'wpmanageninja.com') !== false && strpos(urldecode($hit['url']), 'owner@example.test') !== false)), 'opt-in sent');
        });

        FsTest::case('settings: reCAPTCHA is verified once, then updated, and can be cleared', function () use ($rest, $http) {
            Meta::where('object_type', '_fs_recaptcha_settings')->delete();
            FsTest::assertSame([], $rest('GET', '/settings/recaptcha-settings')['data'], 'none yet');
            $data = ['reCaptchaVersion' => 'v2', 'siteKey' => 'site', 'secretKey' => 'secret', 'reCaptchaEnabled' => 'yes', 'formContainingReCaptcha' => ['create_ticket']];

            $unverified = $rest('POST', '/settings/recaptcha-settings', ['reCaptcha' => $data]);
            FsTest::assertSame([422, 'Please verify reCAPTCHA before saving.'], [$unverified['status'], $unverified['message']], 'first save needs a check');
            $http(function () use ($rest, $data, &$bad) {
                $bad = $rest('POST', '/settings/recaptcha-settings', ['reCaptcha' => $data + ['captchaResponse' => 'tok']]);
            }, ['success' => false]);
            FsTest::assertSame('Your reCAPTCHA settings are not valid.', $bad['message'], 'failed check');
            $http(function () use ($rest, $data, &$added) {
                $added = $rest('POST', '/settings/recaptcha-settings', ['reCaptcha' => $data + ['captchaResponse' => 'tok']]);
            });
            FsTest::assertSame('Your reCAPTCHA settings added successfully.', $added['message'], 'added');
            FsTest::assertSame('Your reCAPTCHA settings updated successfully.', $rest('POST', '/settings/recaptcha-settings', ['reCaptcha' => ['siteKey' => 'site2'] + $data])['message'], 'updated without a new check');
            FsTest::assertSame(['reCaptcha_version' => 'v2', 'siteKey' => 'site2', 'secretKey' => 'secret', 'formContainingReCaptcha' => ['create_ticket'], 'is_enabled' => 'yes'], $rest('GET', '/settings/recaptcha-settings')['data'], 'read back');

            FsTest::assertSame(422, $rest('POST', '/settings/recaptcha-settings', ['reCaptcha' => 'junk'])['status'], 'invalid data');
            FsTest::assertSame('Your reCAPTCHA settings deleted successfully.', $rest('POST', '/settings/recaptcha-settings', ['reCaptcha' => 'clear-reCaptcha-settings'])['message'], 'cleared');
            FsTest::assertSame(0, Meta::where('object_type', '_fs_recaptcha_settings')->count(), 'gone');
        });

        FsTest::case('settings: the AI provider key is never sent back and a masked key keeps the saved one', function () use ($rest) {
            Meta::where('object_type', '_fs_ai_provider_settings')->delete();
            AIProviderFactory::clearCache();
            FsTest::assertSame(422, $rest('POST', '/settings/ai-integration', ['provider' => 'other'])['status'], 'unknown provider');

            $saved = $rest('POST', '/settings/ai-integration', ['provider' => 'openai', 'enabled' => 'yes', 'api_key' => 'sk-test-1234', 'model' => 'gpt-4o-mini']);
            FsTest::assertSame('AI settings have been successfully saved.', $saved['message'], 'saved');
            $read = $rest('GET', '/settings/ai-integration')['data'];
            FsTest::assertSame(['yes', 'openai', '****1234', 'gpt-4o-mini'], [$read['enabled'], $read['provider'], $read['api_key'], $read['model']], 'masked');
            FsTest::assert(isset($read['available_models']['openai'], $read['available_models']['anthropic'], $read['available_models']['gemini']), 'models');

            $rest('POST', '/settings/ai-integration', ['provider' => 'openai', 'enabled' => 'yes', 'api_key' => '****1234', 'model' => 'gpt-4o']);
            FsTest::assertSame('sk-test-1234', Helper::getAIProviderSettings()['api_key'], 'masked key keeps the saved key');
            FsTest::assertSame(422, $rest('POST', '/settings/ai-integration', ['provider' => 'anthropic', 'enabled' => 'yes', 'api_key' => '****1234'])['status'], 'another provider does not inherit it');
            FsTest::assertSame('sk-test-1234', Helper::getAIProviderSettings()['api_key'], 'a refused save keeps the saved key');
            FsTest::assertSame(422, $rest('POST', '/settings/ai-integration', ['provider' => 'gemini', 'enabled' => 'yes', 'api_key' => ''])['status'], 'enabled with no key is refused');
            $rest('POST', '/settings/ai-integration', ['provider' => 'gemini', 'enabled' => 'no', 'api_key' => '']);
            FsTest::assertSame(['no', ''], [Helper::getAIProviderSettings()['enabled'], Helper::getAIProviderSettings()['api_key']], 'disabled saves without a key');
        });

        FsTest::case('settings: remote upload options and retry', function () use ($rest) {
            $page = $rest('GET', '/settings/remote-upload-settings')['data'];
            FsTest::assertSame(['drivers', 'enabled_driver', 'async_upload', 'can_async'], array_keys($page), 'page keys');
            FsTest::assertSame('yes', $rest('POST', '/settings/remote-upload-options', ['async_upload' => 'yes'])['data']['async_upload'], 'async on');
            FsTest::assertSame('no', $rest('POST', '/settings/remote-upload-options', ['async_upload' => 'maybe'])['data']['async_upload'], 'anything else is off');
            FsTest::assertSame(422, $rest('POST', '/settings/remote-upload-retry', ['driver' => 'local'])['status'], 'local cannot retry');
            FsTest::assertSame(422, $rest('POST', '/settings/update-remote-upload-driver', ['driver' => 'nope'])['status'], 'unknown driver');
        });

        FsTest::case('settings: the integration lists, the settings menu and the WordPress pages', function () use ($rest) {
            Helper::updateIntegrationOption('fs_test_on_settings', ['status' => 'yes']);
            Helper::updateIntegrationOption('fs_test_off_settings', ['status' => 'no']);
            try {
                $active = $rest('GET', '/settings/integration-settings')['data'];
                FsTest::assert(in_array('fs_test_on_settings', $active, true) && !in_array('fs_test_off_settings', $active, true), 'switched-on integrations');
            } finally {
                Meta::where('object_type', 'integration_settings')->whereIn('key', ['fs_test_on_settings', 'fs_test_off_settings'])->delete();
            }
            FsTest::assert(is_array($rest('GET', '/settings/integration-statuses')['data']['connections'] ?? null), 'connections');
            FsTest::assert(is_array($rest('GET', '/settings/settings-menu')['data']), 'menu');
            FsTest::assert(is_array($rest('GET', '/settings/pages')['data']['pages'] ?? null), 'pages');
        });

        FsTest::case('settings: agents without fst_manage_settings are refused', function () use ($rest, $adminId, &$userIds) {
            $login = FsTest::uniq('fs-settings');
            $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24, true, true), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
            $userIds[] = $userId;
            FsFactory::agent(['user_id' => $userId]);
            PermissionManager::attachPermissions($userId, ['fst_view_tickets', 'fst_manage_own_tickets']);
            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);
            try {
                FsTest::assertSame(403, $rest('POST', '/settings', ['settings_key' => 'global_business_settings', 'settings' => []])['status'], 'save refused');
                FsTest::assertSame(403, $rest('GET', '/settings/ai-integration')['status'], 'AI settings refused');
            } finally {
                wp_set_current_user($adminId);
                PermissionManager::currentUserPermissions(false);
            }
        });
    } finally {
        foreach ($saved as $key => $value) {
            Meta::where('object_type', 'option')->where('key', $key)->delete();
            if ($value !== null) {
                Meta::insert(['object_type' => 'option', 'key' => $key, 'value' => $value]);
            }
        }
        foreach ($savedMeta as $type => $rows) {
            Meta::where('object_type', $type)->delete();
            foreach ($rows as $row) {
                Meta::insert(['object_type' => $type, 'key' => $row['key'], 'value' => $row['value']]);
            }
        }
        AIProviderFactory::clearCache();
        foreach (array_filter($pageIds) as $pageId) {
            wp_delete_post($pageId, true);
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
