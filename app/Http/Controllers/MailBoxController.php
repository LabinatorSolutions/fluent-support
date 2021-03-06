<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\MailBox;
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
        $mailbox =  MailBox::findOrFail($id);

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
            'hosted_page_id'        => '',
            'non_logged_in_message' => '',
            'client_notifications'  => [
                'ticket_created',
                'response_added_by_agent'
            ],
            'admin_notifications'   => [
                'ticket_created',
                'response_added_by_customer'
            ]
        ];

        if ($data['box_type'] == 'web') {
            $pageId = $data['customer_portal_page_id'];
            if (!$pageId) {
                return $this->sendError([
                    'message' => 'Customer Portal Page is required'
                ]);
            }
            $settings['hosted_page_id'] = intval($pageId);
        } else {
            if(empty($data['mapped_email'])) {
                return $this->sendError([
                    'message' => 'Mapped Email Address is required'
                ]);
            }
        }

        $data['settings'] = $settings;

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
            $pageId = $data['settings']['hosted_page_id'];
            if (!$pageId) {
                return $this->sendError([
                    'message' => 'Customer Portal Page is required'
                ]);
            }
        } else {
            if(empty($data['mapped_email'])) {
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
}
