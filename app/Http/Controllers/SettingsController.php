<?php

namespace FluentSupport\App\Http\Controllers;


use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Hooks\Handlers\ReCaptchaHandler;

/**
 *  SettingsController class is responsible for all settings
 * This class is responsible for all request related to settings under global settings tab
 * @package FluentSupport\App\Http\Controllers
 *
 * @version 1.0.0
 */
class SettingsController extends Controller
{
    /**
     * getSettings method will return the settings by settings key
     * @param Request $request
     * @return array|array[]
     */
    public function getSettings(Request $request)
    {
        $settingsKey = $request->getSafe('settings_key', 'sanitize_text_field');

        return (new Settings)->get($settingsKey);
    }

    /**
     * getIntegrationSettings method will return the settings for integration
     * @param Request $request
     * @return array
     */
    public function getIntegrationSettings(Request $request)
    {
        $settings = Meta::where('object_type', 'integration_settings')->get();
        $integrationSettings = [];
        foreach ($settings as $index => $setting) {
            $data = maybe_unserialize($setting->value);
            if (!empty($data['status']) && $data && $data['status'] == 'yes') {
                $integrationSettings[] = $setting->key;
            }
        }
        return $integrationSettings;
    }

    /**
     * saveSettings method will save the requested settings data by setting key
     * @param Request $request
     * @return array
     */
    public function saveSettings(Request $request)
    {
        $settingsKey = $request->getSafe('settings_key', 'sanitize_text_field');
        $settings = wp_unslash($request->getSafe('settings', null, []));
        (new Settings)->save($settingsKey, $settings);

        return [
            'message' => __('Settings has been updated', 'fluent-support')
        ];
    }

    /**
     * getPages method will return the list of pages created in WP
     * @return array
     */
    public function getPages()
    {
        return [
            'pages' => Helper::getWPPages()
        ];
    }

    /**
     * setupPortal method will setup the support portal
     * @param Request $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function setupPortal(Request $request)
    {
        $mailbox = $request->getSafe('mailbox');
        $this->validate($mailbox, [
            'name'     => 'required',
            'email'    => 'required|email',
            'box_type' => 'required'
        ]);

        $settings = $request->getSafe('global_settings');

        $createPage = $settings['create_portal_page'] == 'yes';

        if (!$createPage && empty($settings['portal_page_id'])) {
            return $this->sendError([
                'message' => __('Please select a page or enable create page', 'fluent-support')
            ]);
        }

        if ($createPage) {
            // we have to create the page
            $page_id = wp_insert_post(
                array(
                    'comment_status' => 'close',
                    'ping_status'    => 'close',
                    'post_author'    => get_current_user_id(),
                    'post_title'     => __('Support Portal', 'fluent-support'),
                    'post_status'    => 'publish',
                    'post_content'   => '<!-- wp:shortcode -->[fluent_support_portal]<!-- /wp:shortcode -->',
                    'post_type'      => 'page'
                )
            );
        } else {
            $page_id = intval($settings['portal_page_id']);
        }

        $newMailBox = MailBox::first();
        if (!$newMailBox) {
            $mailbox['is_default'] = 'yes';
            $mailbox['created_by'] = get_current_user_id();
            $mailbox['settings']['admin_email_address'] = $mailbox['email'];
            $newMailBox = MailBox::create($mailbox);
        }

        $settingsClass = new Settings();
        $globalSettings = $settingsClass->globalBusinessSettings();

        $globalSettings['portal_page_id'] = $page_id;

        $settingsClass->save('global_business_settings', $globalSettings);


        if (defined('WC_PLUGIN_FILE')) {
            // URL Flash
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
     * getFluentCRMSettings method will return the settings for Fluent CRM
     * @param Request $request
     * @return array
     */
    public function getFluentCRMSettings(Request $request)
    {
        if (defined('FLUENTCRM')) {
            $settingDefault = [
                'enabled'        => 'no',
                'default_status' => 'subscribed',
                'assigned_list'  => '',
                'assigned_tags'  => []
            ];

            $settings = Helper::getOption('_fluentcrm_intergration_settings');

            $settings = wp_parse_args($settings, $settingDefault);

            $settingsFields = [
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
                        [
                            'id'    => 'subscribed',
                            'label' => __('Subscribed', 'fluent-support')
                        ],
                        [
                            'id'    => 'pending',
                            'label' => __('Pending', 'fluent-support')
                        ]
                    ],
                    'dependency'  => [
                        'depends_on' => 'enabled',
                        'operator'   => '=',
                        'value'      => 'yes'
                    ],
                    'inline_help' => __('Select the default status for new contacts. If you select pending and it\'s a new contact then a double optin email will be sent', 'fluent-support')
                ],
                'assigned_list'  => [
                    'type'       => 'input-options',
                    'label'      => __('Add to FluentCRM list (optional)', 'fluent-crm'),
                    'options'    => \FluentCrm\App\Models\Lists::select(['id', 'title'])->orderBy('title', 'ASC')->get(),
                    'dependency' => [
                        'depends_on' => 'enabled',
                        'operator'   => '=',
                        'value'      => 'yes'
                    ],
                ],
                'assigned_tags'  => [
                    'type'       => 'input-options',
                    'multiple'   => true,
                    'label'      => __('Add to Tags', 'fluent-crm'),
                    'options'    => \FluentCrm\App\Models\Tag::select(['id', 'title'])->orderBy('title', 'ASC')->get(),
                    'dependency' => [
                        'depends_on' => 'enabled',
                        'operator'   => '=',
                        'value'      => 'yes'
                    ]
                ]
            ];

            return [
                'is_installed'    => true,
                'settings'        => $settings,
                'settings_fields' => $settingsFields,
                'fluentcrm_logo'  => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/fluentcrm-logo.svg'
            ];
        }

        return [
            'is_installed'   => false,
            'fluentcrm_logo' => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/fluentcrm-logo.svg'
        ];

    }

    public function setupInstallation(Request $request)
    {
        $installFluentForm = $request->get('install_fluentform', 'no');

        if ($installFluentForm == 'yes' && !defined('FLUENTFORM')) {
            $this->installFluentForm();
        }

        $optinEmail = $request->getSafe('optin_email', 'sanitize_text_field', 'no');
        if ($optinEmail && is_email($optinEmail)) {
            $this->shareEmail($optinEmail);
        }

        $shareEssential = $request->getSafe('share_essentials', 'sanitize_text_field', 'no');
        if ($shareEssential == 'yes') {
            Helper::updateOption('_share_essential', $shareEssential);
        }

        return $this->sendSuccess([
            'message' => __('Installation has been completed', 'fluent-support')
        ]);

    }

    public function saveReCaptchaSettings(Request $request)
    {
        $data = $request->get('reCaptcha');

        if ('clear-reCaptcha-settings' == $data) {
            if (Meta::where('object_type', '_fs_recaptcha_settings')->delete()) {
                return $this->sendSuccess([
                    'message' => __('Your reCAPTCHA settings deleted successfully.', 'fluent-support'),
                ]);
            }

            return $this->sendError([
                'message' => __('Unable to delete reCAPTCHA settings, try again', 'fluent-support'),
            ]);
        }

        $reCaptchaData = [
            'reCaptcha_version'       => sanitize_text_field($data['reCaptchaVersion']),
            'siteKey'                 => sanitize_text_field($data['siteKey']),
            'secretKey'               => sanitize_text_field($data['secretKey']),
            'formContainingReCaptcha' => array_map('sanitize_text_field', $data['formContainingReCaptcha']),
            'is_enabled'              => sanitize_text_field($data['reCaptchaEnabled'], 'no'),
        ];

        $previousValue = Meta::where('object_type', '_fs_recaptcha_settings')->first();

        if ($previousValue === $reCaptchaData) {
            return $this->sendError([
                'message' => __('Your recaptcha details are already saved.', 'fluent-support'),
            ]);
        }

        $verifyReCaptcha = ReCaptchaHandler::validateRecaptcha($data['captchaResponse'], $data['secretKey'], $data['reCaptchaVersion']);

        if (!$verifyReCaptcha) {
            return $this->sendError([
                'message' => __('Your reCAPTCHA settings are not valid.', 'fluent-support'),
            ]);
        }

        if ($previousValue) {
            Meta::where('object_type', '_fs_recaptcha_settings')->update([
                'value' => maybe_serialize($reCaptchaData)
            ]);
            return $this->sendSuccess([
                'message' => __('Your reCAPTCHA settings updated successfully.', 'fluent-support'),
            ]);
        } else {
            Meta::insert([
                'object_type' => '_fs_recaptcha_settings',
                'key'         => '_fs_recaptcha_data',
                'value'       => maybe_serialize($reCaptchaData)
            ]);
        }

        return $this->sendSuccess([
            'message' => __('Your reCAPTCHA settings added successfully.', 'fluent-support'),
        ]);
    }

    public function getReCaptchaSettings()
    {
        $reCaptchaSettingsData = Meta::where('object_type', '_fs_recaptcha_settings')->first();
        if ($reCaptchaSettingsData) {
            $settings = maybe_unserialize($reCaptchaSettingsData->value);
            return $this->sendSuccess($settings);
        }

        return [];
    }

    private function shareEmail($optinEmail)
    {
        $user = get_user_by('ID', get_current_user_id());
        $data = [
            'answers'    => [
                'website'        => site_url(),
                'email'          => $optinEmail,
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
                'name'           => $user->display_name,
                'has_fluentform' => defined('FLUENTFORM') ? 'yes' : 'no'
            ],
            'questions'  => [
                'website'        => 'website',
                'first_name'     => 'first_name',
                'last_name'      => 'last_name',
                'email'          => 'email',
                'name'           => 'name',
                'has_fluentform' => 'has_fluentform'
            ],
            'user'       => [
                'email' => $optinEmail
            ],
            'fb_capture' => 1,
            'form_id'    => 77
        ];

        $url = add_query_arg($data, 'https://wpmanageninja.com/');

        wp_remote_post($url, [
            'sslverify' => false
        ]);
    }

    /**
     * installFluentCRM method will install Fluent CRM plugin
     * @return array
     */
    public function installFluentCRM()
    {

        if (defined('FLUENTCRM')) {
            return [
                'is_installed' => true,
                'message'      => __('FluentCRM plugin has been installed and activated successfully', 'fluent-support')
            ];
        }

        if (!current_user_can('install_plugins')) {
            return $this->sendError([
                'message' => __('Sorry! you do not have permission to install plugin', 'fluent-crm')
            ]);
        }

        $plugin_id = 'fluent-crm';
        $plugin = [
            'name'      => 'Fluent CRM',
            'repo-slug' => 'fluent-crm',
            'file'      => 'fluent-crm.php',
        ];

        $this->backgroundInstaller($plugin, $plugin_id);

        if (defined('FLUENTCRM')) {
            return [
                'is_installed' => true,
                'message'      => __('FluentCRM plugin has been installed and activated successfully', 'fluent-support')
            ];
        } else {
            return $this->sendError([
                'message' => __('Sorry! FluentCRM could not be installed. Please install manually', 'fluent-support')
            ]);
        }
    }

    public function installFluentForm()
    {

        if (defined('FLUENTFORM')) {
            return [
                'is_installed' => true,
                'message'      => __('Fluent Forms plugin has been installed and activated successfully', 'fluent-support')
            ];
        }

        if (!current_user_can('install_plugins')) {
            return $this->sendError([
                'message' => __('Sorry! you do not have permission to install plugin', 'fluent-crm')
            ]);
        }

        $plugin_id = 'fluent-crm';
        $plugin = [
            'name'      => 'Fluent CRM',
            'repo-slug' => 'fluent-crm',
            'file'      => 'fluent-crm.php',
        ];

        $this->backgroundInstaller($plugin, $plugin_id);

        if (defined('FLUENTCRM')) {
            return [
                'is_installed' => true,
                'message'      => __('Fluent Forms plugin has been installed and activated successfully', 'fluent-support')
            ];
        } else {
            return [
                'is_installed' => false,
                'message'      => __('Fluent Forms could not be installed', 'fluent-support')
            ];
        }
    }

    private function backgroundInstaller($plugin_to_install, $plugin_id)
    {
        if (!empty($plugin_to_install['repo-slug'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            WP_Filesystem();

            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \WP_Upgrader($skin);
            $installed_plugins = array_reduce(array_keys(\get_plugins()), array($this, 'associate_plugin_file'), array());
            $plugin_slug = $plugin_to_install['repo-slug'];
            $plugin_file = isset($plugin_to_install['file']) ? $plugin_to_install['file'] : $plugin_slug . '.php';
            $installed = false;
            $activate = false;

            // See if the plugin is installed already.
            if (isset($installed_plugins[$plugin_file])) {
                $installed = true;
                $activate = !is_plugin_active($installed_plugins[$plugin_file]);
            }

            // Install this thing!
            if (!$installed) {
                // Suppress feedback.
                ob_start();

                try {
                    $plugin_information = plugins_api(
                        'plugin_information',
                        array(
                            'slug'   => $plugin_slug,
                            'fields' => array(
                                'short_description' => false,
                                'sections'          => false,
                                'requires'          => false,
                                'rating'            => false,
                                'ratings'           => false,
                                'downloaded'        => false,
                                'last_updated'      => false,
                                'added'             => false,
                                'tags'              => false,
                                'homepage'          => false,
                                'donate_link'       => false,
                                'author_profile'    => false,
                                'author'            => false,
                            ),
                        )
                    );

                    if (is_wp_error($plugin_information)) {
                        throw new \Exception($plugin_information->get_error_message());
                    }

                    $package = $plugin_information->download_link;
                    $download = $upgrader->download_package($package);

                    if (is_wp_error($download)) {
                        throw new \Exception($download->get_error_message());
                    }

                    $working_dir = $upgrader->unpack_package($download, true);

                    if (is_wp_error($working_dir)) {
                        throw new \Exception($working_dir->get_error_message());
                    }

                    $result = $upgrader->install_package(
                        array(
                            'source'                      => $working_dir,
                            'destination'                 => WP_PLUGIN_DIR,
                            'clear_destination'           => false,
                            'abort_if_destination_exists' => false,
                            'clear_working'               => true,
                            'hook_extra'                  => array(
                                'type'   => 'plugin',
                                'action' => 'install',
                            ),
                        )
                    );

                    if (is_wp_error($result)) {
                        throw new \Exception($result->get_error_message());
                    }

                    $activate = true;

                } catch (\Exception $e) {
                }

                // Discard feedback.
                ob_end_clean();
            }

            wp_clean_plugins_cache();

            // Activate this thing.
            if ($activate) {
                try {
                    $result = activate_plugin($installed ? $installed_plugins[$plugin_file] : $plugin_slug . '/' . $plugin_file);

                    if (is_wp_error($result)) {
                        throw new \Exception($result->get_error_message());
                    }
                } catch (\Exception $e) {
                }
            }
        }
    }

    private function associate_plugin_file($plugins, $key)
    {
        $path = explode('/', $key);
        $filename = end($path);
        $plugins[$filename] = $key;
        return $plugins;
    }

    public function getRemoteUploadSettings(Request $request)
    {
        $dropBoxConfigured = false;
        $googleDriveConfigured = false;

        if (defined('FLUENTSUPPORTPRO')) {
            $dropBoxSettings = Helper::getIntegrationOption('dropbox_settings');
            $dropBoxConfigured = $dropBoxSettings && !empty($dropBoxSettings['access_token']);

            $googleDriveSettings = Helper::getIntegrationOption('google_drive_settings');
            $googleDriveConfigured = $googleDriveSettings && !empty($googleDriveSettings['access_token']);
        }

        $drivers = apply_filters('fluent_support/storage_drivers_info', [
            'local'        => [
                'title'         => 'Default WordPress Storage',
                'is_disabled'   => false,
                'is_configured' => true,
                'icon'          => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/folder.svg',
                'description'   => __('Upload and store the files to your WordPress File System Storage.', 'fluent-support')
            ],
            'dropbox'      => [
                'meta_key'      => 'dropbox_settings',
                'title'         => 'Dropbox',
                'has_config'    => true,
                'is_configured' => $dropBoxConfigured,
                'require_pro'   => !defined('FLUENTSUPPORTPRO'),
                'icon'          => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/dbox.svg',
                'description'   => __('Upload and store the files to your Dropbox Storage.', 'fluent-support')
            ],
            'google_drive' => [
                'meta_key'      => 'google_drive_settings',
                'title'         => 'Google Drive',
                'has_config'    => true,
                'is_configured' => $googleDriveConfigured,
                'require_pro'   => !defined('FLUENTSUPPORTPRO'),
                'upgrade_url'   => 'https://fluentsupport.com/pricing',
                'description'   => __('Upload and store the files to your Google Drive Storage.', 'fluent-support'),
                'icon'          => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/drive.svg',
            ]
        ]);

        return [
            'drivers'        => $drivers,
            'enabled_driver' => Helper::getUploadDriverKey()
        ];
    }

    public function updateRemoteUploadDriver(Request $request)
    {
        $driver = $request->getSafe('driver', 'sanitize_text_field');
        Helper::updateOption('file_upload_driver', $driver);

        return [
            'message' => 'Upload driver has been updated successfully',
            'driver'  => $driver
        ];
    }

    /**
     * getIntegrationLogs method will return the integration logs
     * @return array
     */
    public function integrationLogs()
    {
        $connections = [
            'woocommerce'     => [
                'title'          => __('Woo Commerce', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/woocommerce.png',
                'is_integrated'   => defined('WC_PLUGIN_FILE'),
                'description'    => __('The most popular e-commerce platform for WordPress', 'fluent-support')
            ],
            'lifter-lms'     => [
                'title'          => __('Lifter Lms', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/lifter-lms.png',
                'is_integrated'   => defined('LLMS_PLUGIN_FILE'),
                'description'    => __('Course and e-learning platform built for WordPress', 'fluent-support')
            ],
            'slack' => [
                'title'          => __('Slack', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/slack.png',
                'is_integrated'   => Helper::getFSIntegrationStatus('slack_settings'),
                'description'    => __('Business communication platform designed to scale', 'fluent-support')
            ],
            'pm-pro'  => [
                'title'          => __('PMPro', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/pmpro.png',
                'is_integrated'   => defined('PMPRO_VERSION'),
                'description'    => __('The ultimate platform for any member-focused business', 'fluent-support')
            ],
            'tutor-lms'  => [
                'title'          => __('Tutor LMS', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/tutor-lms.png',
                'is_integrated'   => defined('TUTOR_VERSION'),
                'description'    => __('Course and e-learning platform built for WordPress', 'fluent-support')
            ],
            'telegram'  => [
                'title'          => __('Telegram', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/telegram.jpeg',
                'is_integrated'  => Helper::getFSIntegrationStatus('telegram_settings'),
                'description'    => __('Business communication platform designed for security', 'fluent-support')
            ],
            'fluent-crm'  => [
                'title'          => __('Fluent CRM', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/fluent-crm.png',
                'is_integrated'   => defined('FLUENTCRM'),
                'description'    => __('Self-hosted email and marketing automation for WordPress', 'fluent-support')
            ],
            'fluent-forms'  => [
                'title'          => __('Fluent FORMS', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/fluent-forms.png',
                'is_integrated'   => defined('FLUENTFORM'),
                'description'    => __('A robust form plugin suitable for any business', 'fluent-support')
            ],
            'buddy-boss'  => [
                'title'          => __('Buddy Boss', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/buddy-boss.png',
                'is_integrated'   => defined('BP_PLUGIN_DIR'),
                'description'    => __('Powerful platform for any member-focused business', 'fluent-support')
            ],
            'discord'  => [
                'title'          => __('Discord', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/discord.png',
                'is_integrated'   => Helper::getFSIntegrationStatus('discord_settings'),
                'description'    => __('Business communication platform designed for tech', 'fluent-support')
            ],
            'wishlist-member'  => [
                'title'          => __('Wishlist Member', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/wishlist-member.png',
                'is_integrated'   => defined('WLM3_PLUGIN_VERSION'),
                'description'    => __('Powerful platform for any member-focused business', 'fluent-support')
            ],
            'easy-digital-downloads'  => [
                'title'          => __('Easy Digital Downloads', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/easy-digital-downloads.png',
                'is_integrated'   => class_exists('\Easy_Digital_Downloads'),
                'description'    => __('The ultimate WordPress platform for digital products', 'fluent-support')
            ],
            'restrict-content-pro'  => [
                'title'          => __('Restrict Content pro', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/restrict-content-pro.png',
                'is_integrated'   => class_exists('\Restrict_Content_Pro' ),
                'description'    => __('Powerful platform for any member-focused business', 'fluent-support')
            ],
            'better-docs'  => [
                'title'          => __('Better Docs', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/better-docs.png',
                'is_integrated'   => false,
                'description'    => __('The standard plugin for knowledge base and documentation', 'fluent-support')
            ],
            'whatsapp'  => [
                'title'          => __('Whatsapp', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/whatsapp.jpeg',
                'is_integrated'   => Helper::getFSIntegrationStatus('twilio_settings'),
                'description'    => __('Business communication platform designed for privacy', 'fluent-support')
            ],
            'paymattic'  => [
                'title'          => __('Paymattic', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/paymattic.png',
                'is_integrated'   => defined('WPPAYFORM_VERSION'),
                'description'    => __('All-in-one payment gateway designed for WordPress', 'fluent-support')
            ],
            'learn-dash'  => [
                'title'          => __('LearnDash', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/learn-dash.png',
                'is_integrated'   => defined('LEARNDASH_VERSION'),
                'description'    => __('The leading course platform built for WordPress', 'fluent-support')
            ],
            'learn-press'  => [
                'title'          => __('Learn Press', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/learn-press.png',
                'is_integrated'   => defined('LP_PLUGIN_FILE'),
                'description'    => __('Course and e-learning platform built for WordPress', 'fluent-support')
            ],
            'google-drive'  => [
                'title'          => __('Google Drive', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/google-drive.jpeg',
                'is_integrated'   => Helper::getFSIntegrationStatus('google_drive_settings'),
                'description'    => __('A cloud storage service by Google for storing, syncing, and sharing files.', 'fluent-support')
            ],
            'dropbox'  => [
                'title'          => __('Dropbox', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/dropbox.png',
                'is_integrated'   => Helper::getFSIntegrationStatus('dropbox_settings'),
                'description'    => __('A cloud-based file storage and sharing service that allows users to store files online and sync them across devices.', 'fluent-support')
            ],
            'member-press'  => [
                'title'          => __('Member Press', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/member-press.png',
                'is_integrated'   => class_exists('MeprUtils'),
                'description'    => __('A WordPress plugin that enables the creation and management of membership sites, including content access control and subscription billing.', 'fluent-support')
            ],
            'google-recaptcha'  => [
                'title'          => __('Google reCAPTCHA', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/google-recaptcha.png',
                'is_integrated'   => Helper::getFSIntegrationStatus('recaptcha_setting'),
                'description'    => __('A security service by Google designed to protect websites from bots and abuse by using challenges to distinguish between human and automated access.', 'fluent-support')
            ],
            'fluent-boards'  => [
                'title'          => __('Fluent Boards', 'fluent-support'),
                'logo'           => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/integrations/fluent-boards.png',
                'is_integrated'   =>  defined('FLUENT_BOARDS'),
                'description'    => __('A project management tool designed to streamline workflows and collaboration through customizable, kanban-style boards.', 'fluent-support')
            ],
        ];

        return [
            'connections'  => $connections
        ];
    }
}
