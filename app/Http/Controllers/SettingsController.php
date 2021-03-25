<?php

namespace FluentSupport\App\Http\Controllers;


use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
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


    public function getPages()
    {
        return [
            'pages' => Helper::getWPPages()
        ];
    }

    public function setupPortal(Request $request)
    {
        $mailbox = $request->get('mailbox');
        $this->validate($mailbox, [
            'name'     => 'required',
            'email'    => 'required|email',
            'box_type' => 'required'
        ]);

        $settings = $request->get('global_settings');

        $createPage = $settings['create_portal_page'] == 'yes';

        if (!$createPage && empty($settings['portal_page_id'])) {
            return $this->sendError([
                'message' => 'Please select a page or enable create page'
            ]);
        }

        if ($createPage) {
            // we have to created the page
            $page_id = wp_insert_post(
                array(
                    'comment_status' => 'close',
                    'ping_status'    => 'close',
                    'post_author'    => get_current_user_id(),
                    'post_title'     => __('Support Portal'),
                    'post_status'    => 'publish',
                    'post_content'   => '[fluent_support_portal]',
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

        return [
            'mailbox' => $newMailBox,
            'global_settings' => $globalSettings,
            'mailboxes' => MailBox::select(['id', 'name', 'settings'])->get()
        ];

    }
}
