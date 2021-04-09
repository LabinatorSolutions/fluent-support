<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\Framework\Request\Request;

class MailBoxController extends Controller
{
    public function index(Request $request)
    {
        $mailboxes = MailBox::all();

        foreach ($mailboxes as $mailbox) {
            $mailbox->tickets_count = Ticket::where('mailbox_id', $mailbox->id)->count();
        }

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

        if ($data['box_type'] == 'email') {
            if (empty($data['mapped_email'])) {
                return $this->sendError([
                    'message' => 'Mapped Email Address is required'
                ]);
            }
        }

        $data['settings'] = [
            'admin_email_address' => $data['email']
        ];

        if (!MailBox::first()) {
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

        if ($data['box_type'] == 'email' && empty($data['mapped_email'])) {
            return $this->sendError([
                'message' => 'Mapped Email Address is required'
            ]);
        }

        $mailbox->fill($data);
        $mailbox->save();

        return [
            'message' => 'Mailbox has been saved',
            'mailbox' => $mailbox
        ];
    }

    public function delete(Request $request, $mailBoxId)
    {
        $fallbackId = $request->get('fallback_id');

        if($fallbackId == $mailBoxId) {
            return $this->sendError([
                'message' => 'Fallback Box can not be the same as MailBox ID'
            ]);
        }

        $box = MailBox::findOrFail($mailBoxId);
        $fallbackBox = MailBox::findOrFail($fallbackId);


        do_action('fluent_support/before_delete_email_box', $box, $fallbackBox);
        $box->deleteAllMeta();
        MailBox::where('id', $mailBoxId)->delete();

        // lets transfer the tickets now
        Ticket::where('mailbox_id', $mailBoxId)
            ->update([
                'mailbox_id' => $fallbackBox->id
            ]);

        do_action('fluent_support/mailbox_deleted', $mailBoxId, $fallbackBox);

        return [
            'message' => __('Selected Business has been deleted', 'fluent-support')
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
