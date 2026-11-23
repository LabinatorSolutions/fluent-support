<?php
defined('ABSPATH') or die;

/**
 * @var $app \FluentSupport\Framework\Foundation\Application;
 */

add_filter('fluent_support/parse_smartcode_data', function ($string, $data) {
    return (new \FluentSupport\App\Services\Parser\Parser())->parse($string, $data);
}, 10, 2);

// A ticket the customer can't see yet (logged as an internal note) sends them no email.
add_filter('fluent_support/should_send_notification', function ($shouldSend, $channel = '', $type = '', $ticket = null) {
    if ($shouldSend && $channel === 'email' && substr((string) $type, -12) === '_to_customer'
        && $ticket instanceof \FluentSupport\App\Models\Ticket && $ticket->isHiddenFromCustomer()) {
        return false;
    }

    return $shouldSend;
}, 10, 4);

add_filter('fluent_support/dashboard_notice', function ($messages) {
    if(defined('FLUENTSUPPORTPRO_PLUGIN_VERSION') && version_compare(FLUENT_SUPPORT_PRO_MIN_VERSION, FLUENTSUPPORTPRO_PLUGIN_VERSION, '>')) {
        $updateUrl = admin_url('plugins.php?s=fluent-support-pro&plugin_status=all&fluentsupport_pro_check_update=' . time());
        $html = '<div class="fs_alert_notification fs_alert_warning" style="border-radius: 8px; margin-bottom: 24px; max-width: 1360px; margin-left: auto; margin-right: auto;">
            <div style="display: flex; gap: 8px; align-items: center; padding: 8px 8px 8px 16px;">
                <span style="font-size: 15px; line-height: 18px; flex-shrink: 0;">⚠️</span>
                <p class="fs_alert_text">Fluent Support Pro Plugin needs to be updated for compatibility.</p>
            </div>
        </div>';
        $messages .= $html;
    }
    return $messages;
}, 100);

// The same, on every admin page: tickets and email piping keep working on an older Pro,
// its reports and connectors may not.
add_action('admin_notices', function () {
    if (!defined('FLUENTSUPPORTPRO_PLUGIN_VERSION') || !current_user_can('update_plugins')
        || !version_compare(FLUENT_SUPPORT_PRO_MIN_VERSION, FLUENTSUPPORTPRO_PLUGIN_VERSION, '>')) {
        return;
    }

    $updateUrl = admin_url('plugins.php?s=fluent-support-pro&plugin_status=all&fluentsupport_pro_check_update=' . time());
    printf(
        '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
        /* translators: %s is the Fluent Support Pro version this version of Fluent Support needs */
        esc_html(sprintf(__('Please update Fluent Support Pro to %s. Tickets and email piping keep working; reports and connectors may not until it is updated.', 'fluent-support'), FLUENT_SUPPORT_PRO_MIN_VERSION)),
        esc_url($updateUrl),
        esc_html__('Update now', 'fluent-support')
    );
});

// Remote storage uploads are failing: files are being kept on this server instead.
add_filter('fluent_support/dashboard_notice', function ($messages) {
    if (!\FluentSupport\App\Modules\PermissionManager::currentUserCan('fst_manage_settings')) {
        return $messages;
    }

    $driver = \FluentSupport\App\Services\Helper::getUploadDriverKey();
    if ($driver === 'local') {
        return $messages;
    }

    $health = \FluentSupport\App\Services\Storage\StorageManager::getHealth($driver);
    if (!$health['failed_uploads']) {
        return $messages;
    }

    $settingsUrl = admin_url('admin.php?page=fluent-support#/settings/upload_integration');
    $text = sprintf(
        /* translators: 1: number of files, 2: error message */
        _n(
            '%1$d file could not be uploaded to your remote storage and was kept on this server. Last error: %2$s',
            '%1$d files could not be uploaded to your remote storage and were kept on this server. Last error: %2$s',
            $health['failed_uploads'],
            'fluent-support'
        ),
        $health['failed_uploads'],
        $health['last_error']
    );

    $messages .= '<div class="fs_alert_notification fs_alert_warning" style="border-radius: 8px; margin-bottom: 24px; max-width: 1360px; margin-left: auto; margin-right: auto;">
        <div style="display: flex; gap: 8px; align-items: center; padding: 8px 8px 8px 16px;">
            <p class="fs_alert_text">' . esc_html($text) . ' <a href="' . esc_url($settingsUrl) . '">' . esc_html__('Check storage settings', 'fluent-support') . '</a></p>
        </div>
    </div>';

    return $messages;
}, 110);

add_filter('fluent_support/mail_to_customer_header', function ($headers, $data){
    return (new \FluentSupport\App\Hooks\Handlers\EmailNotificationHandler())->getMailerHeaderWithCc($headers, $data);
}, 10, 2);
