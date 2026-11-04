<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Hooks\Handlers\ReCaptchaHandler;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Modules\Notifications\NotificationService;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Integrations\AI\AIProviderFactory;
use FluentSupport\App\Services\Storage\StorageManager;
use FluentSupport\Database\Migrations\AIActivityLogsMigrator;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Support\Arr;

/**
 * The settings pages (settings/*): global business settings, in-app
 * notifications, FluentCRM, the onboarding wizard, reCAPTCHA, the AI provider
 * and remote file storage.
 */
class SettingsController extends Controller
{
    /**
     * The settings pages that read and save through GET/POST settings.
     */
    const PAGES = ['global_business_settings', NotificationService::OPTION_KEY];

    const FLUENTCRM_KEY = '_fluentcrm_intergration_settings';

    const FLUENTCRM_LOGO = 'assets/images/fluentcrm-logo.svg';

    public function getSettings(Request $request)
    {
        $key = $request->getSafe('settings_key', 'sanitize_text_field');
        if (!in_array($key, self::PAGES, true)) {
            throw ApiException::notFound();
        }

        return (new Settings())->get($key);
    }

    /**
     * Saves one settings page. Values are plain text, except the login
     * message (allowed HTML); FluentCRM keeps only its own fields.
     */
    public function saveSettings(Request $request)
    {
        $key = $request->getSafe('settings_key', 'sanitize_text_field');
        $input = $request->get('settings', []);
        $input = is_array($input) ? $input : [];

        if ($key === self::FLUENTCRM_KEY) {
            $tags = array_values(array_filter(array_map('absint', array_map(fn($tag) => preg_replace('/\D/', '', (string) $tag), (array) Arr::get($input, 'assigned_tags', [])))));
            Helper::updateOption($key, [
                'enabled'        => Arr::get($input, 'enabled') === 'yes' ? 'yes' : 'no',
                'default_status' => Arr::get($input, 'default_status') === 'pending' ? 'pending' : 'subscribed',
                'assigned_list'  => absint(Arr::get($input, 'assigned_list', 0)),
                'assigned_tags'  => $tags,
            ]);
        } elseif (in_array($key, self::PAGES, true)) {
            $settings = map_deep($input, 'sanitize_text_field');
            if (isset($input['login_message'])) {
                $settings['login_message'] = wp_kses_post($input['login_message']);
            }
            (new Settings())->save($key, $settings);
        } else {
            throw ApiException::invalid(__('Unknown settings.', 'fluent-support'));
        }

        return ['message' => __('Settings has been updated', 'fluent-support')];
    }

    /**
     * The keys of the switched-on chat integrations (the agent form shows
     * their fields).
     */
    public function getIntegrationSettings()
    {
        $keys = [];
        foreach (Meta::where('object_type', 'integration_settings')->get(['key', 'value']) as $row) {
            if (Arr::get((array) Helper::safeUnserialize($row->value), 'status') === 'yes') {
                $keys[] = $row->key;
            }
        }

        return $keys;
    }

    public function getPages()
    {
        return ['pages' => Helper::getWPPages()];
    }

    /**
     * The onboarding wizard: the first mailbox (only when there is none) and
     * the portal page, picked or created.
     */
    public function setupPortal(Request $request)
    {
        $mailbox = (array) $request->get('mailbox', []);
        $mailbox = [
            'name'       => sanitize_text_field(Arr::get($mailbox, 'name', '')),
            'email'      => sanitize_email(Arr::get($mailbox, 'email', '')),
            'box_type'   => sanitize_key(Arr::get($mailbox, 'box_type', '')),
            'is_default' => 'yes',
        ];
        $errors = array_filter([
            'name'     => $mailbox['name'] ? null : ['required' => __('The name field is required.', 'fluent-support')],
            'email'    => is_email($mailbox['email']) ? null : ['email' => __('The email must be a valid email address.', 'fluent-support')],
            'box_type' => $mailbox['box_type'] ? null : ['required' => __('The box type field is required.', 'fluent-support')],
        ]);
        if ($errors) {
            throw new ApiException(current(current($errors)), 422, ['errors' => $errors]);
        }

        $page = (array) $request->get('global_settings', []);
        if (Arr::get($page, 'create_portal_page') === 'yes') {
            $pageId = wp_insert_post([
                'comment_status' => 'close',
                'ping_status'    => 'close',
                'post_author'    => get_current_user_id(),
                'post_title'     => __('Support Portal', 'fluent-support'),
                'post_status'    => 'publish',
                'post_content'   => '<!-- wp:shortcode -->[fluent_support_portal]<!-- /wp:shortcode -->',
                'post_type'      => 'page'
            ]);
        } else {
            $pageId = absint(Arr::get($page, 'portal_page_id', 0));
        }
        if (!$pageId) {
            throw ApiException::invalid(__('Please select a page or enable create page', 'fluent-support'));
        }

        $newMailBox = MailBox::first();
        if (!$newMailBox) {
            $newMailBox = MailBox::create($mailbox + [
                'created_by' => get_current_user_id(),
                'settings'   => ['admin_email_address' => $mailbox['email']],
            ]);
        }

        $settings = new Settings();
        $globalSettings = ['portal_page_id' => $pageId] + $settings->globalBusinessSettings();
        $settings->save('global_business_settings', $globalSettings);

        if (defined('WC_PLUGIN_FILE')) {
            flush_rewrite_rules(false);
        }

        return [
            'mailbox'         => $newMailBox,
            'global_settings' => $globalSettings,
            'mailboxes'       => MailBox::select(['id', 'name', 'settings'])->get(),
            'has_fluentform'  => defined('FLUENTFORM')
        ];
    }

    /**
     * The wizard's last step: Fluent Forms, data sharing, the newsletter opt-in.
     */
    public function setupInstallation(Request $request)
    {
        if ($request->getSafe('install_fluentform', 'sanitize_text_field') === 'yes' && !defined('FLUENTFORM') && current_user_can('install_plugins')) {
            $this->installPlugin('fluentform', 'fluentform.php');
        }

        if ($request->getSafe('share_essentials', 'sanitize_text_field') === 'yes') {
            Helper::updateOption('_share_essential', 'yes');
        }

        $email = $request->getSafe('optin_email', 'sanitize_email');
        if ($email && is_email($email)) {
            $user = wp_get_current_user();
            wp_remote_post(add_query_arg([
                'answers'    => [
                    'website'        => site_url(),
                    'email'          => $email,
                    'first_name'     => $user->first_name,
                    'last_name'      => $user->last_name,
                    'name'           => $user->display_name,
                    'has_fluentform' => defined('FLUENTFORM') ? 'yes' : 'no'
                ],
                'questions'  => array_combine($keys = ['website', 'first_name', 'last_name', 'email', 'name', 'has_fluentform'], $keys),
                'user'       => ['email' => $email],
                'fb_capture' => 1,
                'form_id'    => 77
            ], 'https://wpmanageninja.com/'));
        }

        return ['message' => __('Installation has been completed', 'fluent-support')];
    }

    /**
     * The FluentCRM page: its settings and the lists and tags to pick from,
     * or only that FluentCRM isn't installed.
     */
    public function getFluentCRMSettings()
    {
        $logo = FLUENT_SUPPORT_PLUGIN_URL . self::FLUENTCRM_LOGO;
        if (!defined('FLUENTCRM')) {
            return ['is_installed' => false, 'fluentcrm_logo' => $logo];
        }

        $dependency = ['depends_on' => 'enabled', 'operator' => '=', 'value' => 'yes'];

        return [
            'is_installed'    => true,
            'settings'        => wp_parse_args(Helper::getOption(self::FLUENTCRM_KEY), ['enabled' => 'no', 'default_status' => 'subscribed', 'assigned_list' => '', 'assigned_tags' => []]),
            'settings_fields' => [
                'enabled'        => [
                    'type'           => 'inline-checkbox',
                    'true_label'     => 'yes',
                    'false_label'    => 'no',
                    'checkbox_label' => __('Enable FluentCRM Integration', 'fluent-support')
                ],
                'default_status' => [
                    'type'        => 'input-radio',
                    'label'       => __('Default status for new contacts', 'fluent-support'),
                    'options'     => [
                        ['id' => 'subscribed', 'label' => __('Subscribed', 'fluent-support')],
                        ['id' => 'pending', 'label' => __('Pending', 'fluent-support')]
                    ],
                    'dependency'  => $dependency,
                    'inline_help' => __('Select the default status for new contacts. If you select pending and it\'s a new contact then a double optin email will be sent', 'fluent-support')
                ],
                'assigned_list'  => [
                    'type'       => 'input-options',
                    'label'      => __('Add to FluentCRM list (optional)', 'fluent-support'),
                    'options'    => \FluentCrm\App\Models\Lists::select(['id', 'title'])->orderBy('title', 'ASC')->get(),
                    'dependency' => $dependency,
                ],
                'assigned_tags'  => [
                    'type'       => 'input-options',
                    'multiple'   => true,
                    'label'      => __('Add to Tags', 'fluent-support'),
                    'options'    => \FluentCrm\App\Models\Tag::select(['id', 'title'])->orderBy('title', 'ASC')->get(),
                    'dependency' => $dependency
                ]
            ],
            'fluentcrm_logo'  => $logo
        ];
    }

    public function installFluentCRM()
    {
        if (!defined('FLUENTCRM')) {
            if (!current_user_can('install_plugins')) {
                throw ApiException::invalid(__('Sorry! you do not have permission to install plugin', 'fluent-support'));
            }
            if (!$this->installPlugin('fluent-crm', 'fluent-crm.php')) {
                throw ApiException::invalid(__('Sorry! FluentCRM could not be installed. Please install manually', 'fluent-support'));
            }
        }

        return [
            'is_installed' => true,
            'message'      => __('FluentCRM plugin has been installed and activated successfully', 'fluent-support')
        ];
    }

    /**
     * Saves the reCAPTCHA keys. The first save needs a passing check with
     * them (captchaResponse); 'clear-reCaptcha-settings' deletes them.
     */
    public function saveReCaptchaSettings(Request $request)
    {
        $data = $request->get('reCaptcha');

        if ($data === 'clear-reCaptcha-settings') {
            if (!Meta::where('object_type', '_fs_recaptcha_settings')->delete()) {
                throw ApiException::invalid(__('Unable to delete reCAPTCHA settings, try again', 'fluent-support'));
            }
            return ['message' => __('Your reCAPTCHA settings deleted successfully.', 'fluent-support')];
        }

        if (!is_array($data)) {
            throw ApiException::invalid(__('Invalid reCAPTCHA data.', 'fluent-support'));
        }

        $settings = [
            'reCaptcha_version'       => sanitize_text_field($data['reCaptchaVersion'] ?? ''),
            'siteKey'                 => sanitize_text_field($data['siteKey'] ?? ''),
            'secretKey'               => sanitize_text_field($data['secretKey'] ?? ''),
            'formContainingReCaptcha' => array_map('sanitize_text_field', (array) ($data['formContainingReCaptcha'] ?? [])),
            'is_enabled'              => sanitize_text_field($data['reCaptchaEnabled'] ?? 'no'),
        ];

        $exists = Meta::where('object_type', '_fs_recaptcha_settings')->exists();
        $token = sanitize_text_field($data['captchaResponse'] ?? '');

        if ($token && !ReCaptchaHandler::validateRecaptcha($token, $settings['secretKey'], $settings['reCaptcha_version'])) {
            throw ApiException::invalid(__('Your reCAPTCHA settings are not valid.', 'fluent-support'));
        }
        if (!$token && !$exists) {
            throw ApiException::invalid(__('Please verify reCAPTCHA before saving.', 'fluent-support'));
        }

        if ($exists) {
            Meta::where('object_type', '_fs_recaptcha_settings')->update(['value' => maybe_serialize($settings)]);
            return ['message' => __('Your reCAPTCHA settings updated successfully.', 'fluent-support')];
        }

        Meta::insert(['object_type' => '_fs_recaptcha_settings', 'key' => '_fs_recaptcha_data', 'value' => maybe_serialize($settings)]);

        return ['message' => __('Your reCAPTCHA settings added successfully.', 'fluent-support')];
    }

    public function getReCaptchaSettings()
    {
        $row = Meta::where('object_type', '_fs_recaptcha_settings')->first();

        return $row ? Helper::safeUnserialize($row->value) : [];
    }

    /**
     * The AI provider settings. The API key goes back masked to its last 4
     * characters, never whole.
     */
    public function getAIProviderSettings()
    {
        $settings = Helper::getAIProviderSettings();
        $apiKey = $settings['api_key'] ?? '';

        $models = [];
        foreach (AIProviderFactory::getAllowedProviders() as $provider) {
            $instance = AIProviderFactory::make($provider, '', '');
            if (!is_wp_error($instance)) {
                $models[$provider] = $instance->getModelOptions();
            }
        }

        return [
            'enabled'          => $settings['enabled'] ?? ($apiKey ? 'yes' : 'no'),
            'provider'         => $settings['provider'] ?? 'openai',
            'api_key'          => $apiKey ? '****' . substr($apiKey, -4) : '',
            'model'            => ($settings['model'] ?? '') ?: 'default',
            'available_models' => $models,
        ];
    }

    /**
     * Saves the provider, model and key. An empty or masked key keeps the
     * saved key for the same provider (never for another one).
     */
    public function saveAIProviderSettings(Request $request)
    {
        $provider = $request->getSafe('provider', 'sanitize_text_field', 'openai');
        if (!in_array($provider, AIProviderFactory::getAllowedProviders(), true)) {
            throw ApiException::invalid(__('Invalid AI provider selected.', 'fluent-support'));
        }

        $apiKey = $request->getSafe('api_key', 'sanitize_text_field', '');
        if (!$apiKey || strpos($apiKey, '****') === 0) {
            $existing = Helper::getAIProviderSettings();
            $apiKey = ($existing['provider'] ?? '') === $provider ? ($existing['api_key'] ?? '') : '';
        }

        $enabled = $request->getSafe('enabled', 'sanitize_text_field', 'yes');
        if ($enabled !== 'no' && !$apiKey) {
            throw ApiException::invalid(__('Please enter your API key.', 'fluent-support'));
        }

        Helper::saveAIProviderSettings([
            'enabled'  => $enabled,
            'provider' => $provider,
            'api_key'  => $apiKey,
            'model'    => $request->getSafe('model', 'sanitize_text_field', ''),
        ]);
        AIProviderFactory::clearCache();
        AIActivityLogsMigrator::migrate();

        return ['message' => __('AI settings have been successfully saved.', 'fluent-support')];
    }

    public function getRemoteUploadSettings()
    {
        return [
            'drivers'        => StorageManager::getDriversInfo(),
            'enabled_driver' => Helper::getUploadDriverKey(),
            'async_upload'   => Helper::getOption('storage_async_upload') === 'yes' ? 'yes' : 'no',
            'can_async'      => function_exists('as_schedule_single_action'),
        ];
    }

    /**
     * Switches the upload driver: local, or a connected remote storage.
     */
    public function updateRemoteUploadDriver(Request $request)
    {
        $driver = $request->getSafe('driver', 'sanitize_text_field');
        if (!StorageManager::isValidDriverKey($driver)) {
            throw ApiException::invalid(__('Invalid storage driver.', 'fluent-support'));
        }

        $info = Arr::get(StorageManager::getDriversInfo(), $driver, []);
        $registered = StorageManager::getDriver($driver);
        $configured = $registered ? $registered->isConfigured() : !empty($info['is_configured']);
        if ($driver !== 'local' && (!$configured || !empty($info['require_pro']))) {
            throw ApiException::invalid(__('Please connect this storage first.', 'fluent-support'));
        }

        Helper::updateOption('file_upload_driver', $driver);
        StorageManager::clearFailure($driver);

        return [
            'message' => __('Upload driver updated.', 'fluent-support'),
            'driver'  => $driver
        ];
    }

    /**
     * Queues the files that failed to reach remote storage again.
     */
    public function retryRemoteUploads(Request $request)
    {
        $driver = $request->getSafe('driver', 'sanitize_text_field');
        if ($driver === 'local' || !StorageManager::isValidDriverKey($driver)) {
            throw ApiException::invalid(__('Invalid storage driver.', 'fluent-support'));
        }

        $count = StorageManager::retryFailed($driver);

        return [
            'message' => $count
                // translators: %d: number of files
                ? sprintf(_n('%d file will be uploaded again in the background.', '%d files will be uploaded again in the background.', $count, 'fluent-support'), $count)
                : __('The warning was cleared. New uploads will try again.', 'fluent-support'),
            'queued'  => $count,
        ];
    }

    public function updateRemoteUploadOptions(Request $request)
    {
        $async = $request->getSafe('async_upload', 'sanitize_text_field') === 'yes' ? 'yes' : 'no';
        Helper::updateOption('storage_async_upload', $async);

        return [
            'message'      => __('Storage settings have been saved.', 'fluent-support'),
            'async_upload' => $async
        ];
    }

    public function integrationStatuses()
    {
        return ['connections' => Helper::getIntegrationStatuses()];
    }

    public function getSettingsMenu()
    {
        return Helper::getGlobalSettingsMenu();
    }

    /**
     * Installs (when missing) and activates a wordpress.org plugin.
     *
     * @return bool whether the plugin is active afterwards
     */
    private function installPlugin($slug, $file)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Already installed, maybe in another folder.
        $pluginFile = current(array_filter(array_keys(get_plugins()), fn($path) => basename($path) === $file));
        if (!$pluginFile) {
            $pluginFile = $slug . '/' . $file;
            $info = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            if (is_wp_error($info)) {
                return false;
            }

            WP_Filesystem();
            ob_start();
            $installed = (new \Plugin_Upgrader(new \Automatic_Upgrader_Skin()))->install($info->download_link);
            ob_end_clean();
            wp_clean_plugins_cache();
            if (!$installed || is_wp_error($installed)) {
                return false;
            }
        }

        return is_plugin_active($pluginFile) || !is_wp_error(activate_plugin($pluginFile));
    }
}
