<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\Framework\Request\Request;

class MailBoxController extends Controller
{
    public function index(Request $request)
    {
        $mailboxes = MailBox::all();

        return [
            'mailboxes' => $mailboxes
        ];
    }

    public function get(Request $request, $id)
    {
        $mailbox = MailBox::findOrFail($id);

        return $this->sendSuccess([
            'mailbox' => $mailbox
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->get('business');
        $data = wp_unslash($data);

        $this->validate($data, [
            'name'  => 'required',
            'email' => 'required'
        ]);

        $settings = [
            'non_logged_in_message' => ''
        ];

        if ($data['box_type'] == 'web') {

        } else {
            if (empty($data['mapped_email'])) {
                return $this->sendError([
                    'message' => 'Mapped Email Address is required'
                ]);
            }
        }

        $data['settings'] = $settings;

        if(!MailBox::first()) {
            $data['is_default'] = 'yes';
        }

        $mailbox = MailBox::create($data);

        return [
            'message' => 'Mailbox has been created successfully',
            'mailbox' => $mailbox
        ];
    }

    public function update(Request $request, $mailBoxId)
    {
        $data = $request->get('business');
        $data = wp_unslash($data);

        $this->validate($data, [
            'name'  => 'required',
            'email' => 'required'
        ]);

        $mailbox = MailBox::findOrFail($mailBoxId);

        if ($data['box_type'] == 'web') {

        } else {
            if (empty($data['mapped_email'])) {
                return $this->sendError([
                    'message' => 'Mapped Email Address is required'
                ]);
            }
        }

        $mailbox->fill($data);
        $mailbox->save();

        return [
            'message' => 'Mailbox has been saved',
            'mailbox' => $mailbox
        ];
    }

    public function getEmailSettings(Request $request, Settings $settings, $boxId)
    {
        $box = MailBox::findOrFail($boxId);
        $emailType = $request->get('email_type');

        return [
            'email_settings' => $settings->getBoxEmailSettings($box, $emailType)
        ];
    }

    public function saveEmailSettings(Request $request, Settings $settings, $boxId)
    {
        $data = wp_unslash($request->get('email_settings'));
        $this->validate($data, [
            'email_subject' => 'required',
            'email_body'    => 'required'
        ]);

        $data['email_body'] = wp_kses_post($data['email_body']);

        $box = MailBox::findOrFail($boxId);
        $emailType = $request->get('email_type');

        $settings->saveBoxEmailSettings($box, $emailType, $data);

        return [
            'message' => __('Settings has been updated', 'fluent-support')
        ];
    }
}
