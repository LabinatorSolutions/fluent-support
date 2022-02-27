<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\App\Services\TicketQueryService;
use FluentSupport\Framework\Request\Request;

class MailBoxController extends Controller
{
    /**
     * index method will return the list of business inbox
     * @param Request $request
     * @return array
     */
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

    /**
     * get method will fetch and return information related to business box
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function get(Request $request, $id)
    {
        $mailbox = MailBox::findOrFail($id);

        return $this->sendSuccess([
            'mailbox' => $mailbox
        ]);
    }


    /**
     * Save method will create new business box
     * @param Request $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function save(Request $request)
    {
        $data = $request->get('business');
        $data = wp_unslash($data);

        $this->validate($data, [
            'name' => 'required',
            'email' => 'required'
        ]);

        if ($data['box_type'] == 'email') {
            $data['settings'] = [
                'admin_email_address' => ''
            ];
        } else {
            $data['settings'] = [
                'admin_email_address' => $data['email']
            ];
        }

        if (!MailBox::first()) {
            $data['is_default'] = 'yes';
        }

        $mailbox = MailBox::create($data);

        return [
            'message' => __('Mailbox has been created successfully', 'fluent-support'),
            'mailbox' => $mailbox
        ];
    }

    /**
     * Update method will update existing information for a business by mailbox id
     * @param Request $request
     * @param $mailBoxId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function update(Request $request, $mailBoxId)
    {
        $data = $request->get('business');
        $data = wp_unslash($data);

        $this->validate($data, [
            'name' => 'required',
            'email' => 'required'
        ]);

        $mailbox = MailBox::findOrFail($mailBoxId);

        if ($data['box_type'] == 'email' && empty($data['mapped_email'])) {
            return $this->sendError([
                'message' => __('Mapped Email Address is required', 'fluent-support')
            ]);
        }

        $mailbox->fill($data);
        $mailbox->save();

        return [
            'message' => __('Mailbox has been saved', 'fluent-support'),
            'mailbox' => $mailbox
        ];
    }

    /**
     * delete method will delete a business from mailbox and replaced with alternative
     * @param Request $request
     * @param $mailBoxId
     * @return array
     */
    public function delete(Request $request, $mailBoxId)
    {
        $fallbackId = $request->get('fallback_id');

        if ($fallbackId == $mailBoxId) {
            return $this->sendError([
                'message' => __('Fallback Box can not be the same as MailBox ID', 'fluent-support')
            ]);
        }

        $box = MailBox::findOrFail($mailBoxId);
        $fallbackBox = MailBox::findOrFail($fallbackId);

        /*
         * Action before delete a mailbox
         *
         * @since v1.0.0
         * @param object $box           Mailbox
         * @param object $fallbackBox   Fallback mailbox
         */
        do_action('fluent_support/before_delete_email_box', $box, $fallbackBox);
        $box->deleteAllMeta();
        MailBox::where('id', $mailBoxId)->delete();

        // lets transfer the tickets now
        Ticket::where('mailbox_id', $mailBoxId)
            ->update([
                'mailbox_id' => $fallbackBox->id
            ]);

        /*
         * Action on mailbox delete
         *
         * @since v1.0.0
         * @param integer $mailBoxId
         * @param object $fallbackBox   Fallback mailbox
         */
        do_action('fluent_support/mailbox_deleted', $mailBoxId, $fallbackBox);

        return [
            'message' => __('Selected Business has been deleted', 'fluent-support')
        ];

    }

    public function moveTickets(Request $request, $mailBoxId)
    {
        $newBoxId = $request->get('new_box_id');
        $ticketIds = $request->get('ticket_ids', []);
        $move_type = $request->get('move_type');

        if ($newBoxId == $mailBoxId) {
            return $this->sendError([
                'message' => __('New Box can not be the same as MailBox ID', 'fluent-support')
            ]);
        }

        if ($move_type == 'Custom' && empty($ticketIds)) {
            return $this->sendError([
                'message' => __('Invalid request submitted, Select ticket first', 'fluent-support')
            ]);
        }

        $newBox = MailBox::findOrFail($newBoxId);

        if (!empty($ticketIds)) {
            Ticket::whereIn('id', $ticketIds)
                ->update([
                    'mailbox_id' => $newBox->id
                ]);
        } else {
            // Move all ticket for the MailBox
            Ticket::where('mailbox_id', $mailBoxId)
                ->update([
                    'mailbox_id' => $newBox->id
                ]);
        }

        return [
            'message' => __('All ticket moves to the selected Business', 'fluent-support')
        ];
    }

    /**
     * getEmailSettings method will get and return the mailbox email settings
     * @param Request $request
     * @param Settings $settings
     * @param $boxId
     * @return array
     */
    public function getEmailSettings(Request $request, Settings $settings, $boxId)
    {
        $box = MailBox::findOrFail($boxId);
        $emailType = $request->get('email_type');

        return [
            'email_settings' => $settings->getBoxEmailSettings($box, $emailType)
        ];
    }

    /**
     * getEmailsSetups method will return email settings for a business box by box id
     * @param Request $request
     * @param Settings $settings
     * @param $boxId
     * @return array
     */
    public function getEmailsSetups(Request $request, Settings $settings, $boxId)
    {
        $box = MailBox::findOrFail($boxId);

        $types = $settings->getEmailSettingsKeys();

        $req = [];
        foreach ($types as $type) {
            $req[] = $settings->getBoxEmailSettings($box, $type);
        }

        return [
            'email_configs' => $req,
            'email_keys' => $types
        ];
    }

    /**
     * saveEmailSettings method will save the email settings for a business box using box id
     * @param Request $request
     * @param Settings $settings
     * @param $boxId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function saveEmailSettings(Request $request, Settings $settings, $boxId)
    {
        $data = wp_unslash($request->get('email_settings'));
        $this->validate($data, [
            'email_subject' => 'required',
            'email_body' => 'required'
        ]);

        $data['email_body'] = wp_kses_post($data['email_body']);

        $box = MailBox::findOrFail($boxId);
        $emailType = $request->get('email_type');

        $settings->saveBoxEmailSettings($box, $emailType, $data);

        return [
            'message' => __('Settings has been updated', 'fluent-support')
        ];
    }

    public function getTickets(Request $request, $mailbox_id)
    {
        $customer_id = $request->get('filters.customer_id');
        $product_id = $request->get('filters.product_id');
        $ticket_title = $request->get('filters.ticket_title');

        $ticketsQuery = (new Ticket())->with([
            'customer' => function ($query) use ($customer_id) {
                $query->select(['first_name', 'last_name', 'email', 'id', 'avatar']);
            }, 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            },
            'product',
            'tags',
            'preview_response' => function ($query) {
                $query->orderBy('id', 'desc');
            }
        ])->where('mailbox_id', $mailbox_id);

        if ($customer_id)
            $ticketsQuery->where('customer_id', $customer_id);

        if ($product_id)
            $ticketsQuery->where('product_id', $product_id);

        if ($ticket_title)
            $ticketsQuery->where('title', 'LIKE', "%$ticket_title%");

        $tickets = $ticketsQuery->paginate();

        return [
            'tickets' => $tickets
        ];
    }
}
