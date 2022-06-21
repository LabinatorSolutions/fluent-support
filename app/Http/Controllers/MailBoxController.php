<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\Framework\Request\Request;

class MailBoxController extends Controller
{
    /**
     * index method will return the list of business inbox
     * @param Request $request
     * @return array
     */
    public function index( MailBox $mailBox )
    {
        return $mailBox->getMailBoxes();
    }

    /**
     * get method will fetch and return information related to business box
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function get( MailBox $mailBox, $id )
    {
        return [
            'mailbox' => $mailBox->getMailBox( $id )
        ];

    }


    /**
     * Save method will create new business box
     * @param Request $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function save(Request $request, MailBox $mailBox)
    {
        $data = wp_unslash( $request->get('business') );

        $this->validate($data, [
            'name' => 'required',
            'email' => 'required'
        ]);

        return [
            'message' => __('Mailbox has been created successfully', 'fluent-support'),
            'mailbox' => $mailBox->createMailBox( $data )
        ];
    }

    /**
     * Update method will update existing information for a business by mailbox id
     * @param Request $request
     * @param $mailBoxId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function update(Request $request, MailBox $mailBox, $mailBoxId)
    {
        try{
            $data = wp_unslash( $request->get('business') );

            $this->validate($data, [
                'name' => 'required',
                'email' => 'required'
            ]);

            return [
                'message' => __( 'Mailbox has been saved', 'fluent-support' ),
                'mailbox' => $mailBox->updateMailBox( $data, $mailBoxId )
            ];
        }catch (\Exception $e){
            return [
                'message' => __( $e->getMessage(), 'fluent-support' ),
            ];
        }
    }

    /**
     * delete method will delete a business from mailbox and replaced with alternative
     * @param Request $request
     * @param $mailBoxId
     * @return array
     */
    public function delete(Request $request, MailBox $mailBox, $mailBoxId)
    {
        try {
            return $mailBox->deleteMailBox( $mailBoxId, $request->get('fallback_id') );
        } catch (\Exception $e) {
            return [
                'message' => __( $e->getMessage(), 'fluent-support' ),
            ];
        }
    }

    public function moveTickets(Request $request, MailBox $mailBox, $mailBoxId)
    {
        try {
            $data = $request->only(['ticket_ids', 'new_box_id', 'move_type']);
            return $mailBox->moveTickets( $data, $mailBoxId );
        } catch (\Exception $e) {
            return [
                'message' => __( $e->getMessage(), 'fluent-support' ),
            ];
        }
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

        if ($customer_id) {
            $ticketsQuery->where('customer_id', $customer_id);
        }

        if ($product_id) {
            $ticketsQuery->where('product_id', $product_id);
        }

        if ($ticket_title) {
            $ticketsQuery->where('title', 'LIKE', "%$ticket_title%");
        }

        $tickets = $ticketsQuery->paginate();

        return [
            'tickets' => $tickets
        ];
    }
}
