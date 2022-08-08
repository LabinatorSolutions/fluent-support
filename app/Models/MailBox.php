<?php

namespace FluentSupport\App\Models;

use Exception;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\Framework\Support\Arr;

class MailBox extends Model
{
    protected $table = 'fs_mail_boxes';

    protected $appends = ['settings'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'box_type',
        'mapped_email',
        'email_footer',
        'settings',
        'avatar',
        'created_by',
        'is_default'
    ];

    public static function boot()
    {
        static::creating(function ($model) {
            $model->slug = static::slugify($model->name);
            $model->settings = $model->settings ?: [
                'admin_email_address' => ''
            ];
        });
    }

    public function setSettingsAttribute($settings)
    {
        $this->attributes['settings'] = \maybe_serialize($settings);
    }

    public function getSettingsAttribute($value)
    {
        return \maybe_unserialize($this->attributes['settings']);
    }

    public static function slugify($title)
    {
        $slug = sanitize_title($title, 'business', 'display');
        if (MailBox::where('slug', $slug)->first()) {
            $slug .= '-' . time();
        }
        return $slug;
    }

    public function getMailerHeader()
    {
        $headers = [];
        $fromString = $this->email; //$this->name;
        if($this->name) {
            $fromString = $this->name.' <'.$fromString.'>';
        }

        if ($fromString) {
            $headers[] = 'From: '. $fromString;
        }

        // Set Reply-To Header
        $headers[] = 'Reply-To: '. $fromString;

        return $headers;
    }

    public function getMeta($key, $default = '')
    {
        $class = __NAMESPACE__ . '\MailBox';

        $meta = Meta::where('object_type', $class)
            ->where('object_id', $this->id)
            ->where('key', $key)
            ->first();

        if($meta) {
            return maybe_unserialize($meta->value);
        }

        return $default;
    }

    public function saveMeta($key, $value)
    {
        $class = __NAMESPACE__ . '\MailBox';

        $meta = Meta::where('object_type', $class)
            ->where('object_id', $this->id)
            ->where('key', $key)
            ->first();

        if($meta) {
            $meta->value = maybe_serialize($value);
            $meta->save();
            return true;
        }

        Meta::insert([
            'object_type' => $class,
            'object_id' => $this->id,
            'key' => $key,
            'value' => maybe_serialize($value)
        ]);
        return true;
    }

    public function deleteMeta($key)
    {
        $class = __NAMESPACE__ . '\MailBox';

        $meta = Meta::where('object_type', $class)
            ->where('object_id', $this->id)
            ->where('key', $key)
            ->delete();
    }

    public function deleteAllMeta()
    {
        $class = __NAMESPACE__ . '\MailBox';

        $meta = Meta::where('object_type', $class)
            ->where('object_id', $this->id)
            ->delete();
    }

    /**
     * This `getMailBoxes` method is used to get all mailboxes.
     * @return array
     */
    public function getMailBoxes ()
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
     * This `getMailBox` method is used to get a mailbox by id.
     * @param int $id
     * @return MailBox
     */
    public function getMailBox ($id )
    {
        return MailBox::findOrFail($id);
    }

    /**
     * This `createMailBox` method is used to create a new MailBox.
     * @param array $data
     * @return MailBox
     */
    public function createMailBox ( $data )
    {
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

        return MailBox::create($data);
    }


    /**
     * This `updateMailBox` method is used to update a mailbox.
     * @param array $data
     * @param int $mailBoxId
     * @return object $mailbox
     */
    public function updateMailBox ($data, $mailBoxId )
    {

        $mailbox = MailBox::findOrFail($mailBoxId);

        if ($data['box_type'] == 'email' && empty($data['mapped_email'])) {
            throw new Exception('Mapped Email Address is required');
        }

        $mailbox->fill($data);
        $mailbox->save();

        return $mailbox;
    }

    /**
     * This `deleteMailBox` method is used to delete a mailbox.
     * @param int $mailBoxId The id of the mailbox to be deleted.
     * @param int $fallbackId The id of the fallback mailbox to be used.
     * @return array
     */
    public function deleteMailBox ($mailBoxId, $fallbackId )
    {
        if ( $mailBoxId == $fallbackId ) {
            throw new Exception('Fallback Box can not be the same as MailBox ID');
        }

        $box = MailBox::findOrFail($mailBoxId);
        $fallbackBox = MailBox::findOrFail($fallbackId);

        $this->runDelete( $box, $mailBoxId, $fallbackBox );

        return [
            'message' => __('Selected Business has been deleted', 'fluent-support')
        ];
    }

    private function runDelete ( $box, $mailBoxId, $fallbackBox )
    {
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
    }


    /**
     * This `moveTickets` method is used to move tickets from one mailbox to another.
     * @param array $data
     * @param int $mailBoxId
     * @return array
     */
    public function moveTickets ($data, $mailBoxId )
    {
        $this->validateTicketMoving( $data, $mailBoxId );

        $newBox = MailBox::findOrFail($data['new_box_id']);
        $oldBox = MailBox::findOrFail($mailBoxId);

        /**
         * Action before tickets moved
         * @since v1.5.7
         * @param array $data
         * @param object $oldBox Current mailbox
         * @param object $newBox Mailbox where selected tickets will be moved
         */
        do_action( 'fluent_support/before_move_tickets', $data, $oldBox, $newBox );

        if (!empty($data['ticket_ids'])) {
            Ticket::whereIn('id', $data['ticket_ids'])
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
        /**
         * Action after tickets moved
         * @since v1.5.7
         * @param array $data
         * @param object $oldBox Mailbox where selected tickets were moved from
         * @param object $newBox Mailbox where selected tickets were moved to
         */
        do_action( 'fluent_support/tickets_moved', $data, $oldBox, $newBox );

        return [
            'message' => __('All ticket moves to the selected Business', 'fluent-support')
        ];

    }

    private function validateTicketMoving ( $data, $mailBoxId )
    {

        if ($data['new_box_id'] == $mailBoxId) {
            throw new Exception('New Box can not be the same as MailBox ID');
        }

        if ( $data['move_type'] == 'Custom' && empty($data['ticket_ids']) ) {
            throw new Exception('Invalid request submitted, Select ticket first');
        }

        return true;
    }

    /**
     * This `getEmailSetups` method is used to get the email setups
     * @param int $boxId
     * @return array
     */
    public function getEmailsSetups ( $boxId )
    {
        $settings = new Settings();
        $box = MailBox::findOrFail( $boxId );

        $types = $settings->getEmailSettingsKeys();

        $req = [];
        foreach ( $types as $type ) {
            $req[] = $settings->getBoxEmailSettings( $box, $type );
        }

        return [
            'email_configs' => $req,
            'email_keys' => $types
        ];
    }


    /**
     * This `saveEmailSettings` method is used to save the email settings for the mailbox
     * @param object $request
     * @param int $boxId
     * @return array
     */
    public function saveEmailSettings ($request, $boxId, $data )
    {
        $settings = new Settings();

        $data['email_body'] = wp_kses_post($data['email_body']);

        $box = MailBox::findOrFail($boxId);
        $emailType = $request->get('email_type');

        $settings->saveBoxEmailSettings($box, $emailType, $data);

        return [
            'message' => __('Settings has been updated', 'fluent-support')
        ];
    }

    /**
     * This `getTickets` method is used to get the tickets for the given mailbox
     * @param array $filters
     * @param int $boxId
     * @return array
     */
    public function getTickets ($filters, $boxId )
    {

        $ticketsQuery = $this->getTicketsQuery( $filters['customer_id'], $boxId );

        if ( $filters['customer_id'] ) {
            $ticketsQuery->where('customer_id', $filters['customer_id']);
        }

        if ( $filters['product_id'] ) {
            $ticketsQuery->where('product_id', $filters['product_id']);
        }

        if ( $filters['ticket_title']  ) {
            $ticketsQuery->where('title', 'LIKE', "%". $filters['ticket_title'] ."%");
        }

        return [
            'tickets' => $ticketsQuery->paginate()
        ];
    }

    private function getTicketsQuery ( $customerID, $boxId )
    {
        $ticketsQuery = (new Ticket())->with([
            'customer' => function ($query) use ( $customerID ) {
                $query->select(['first_name', 'last_name', 'email', 'id', 'avatar']);
            }, 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            },
            'product',
            'tags',
            'preview_response' => function ($query) {
                $query->latest('id');
            }
        ])->where('mailbox_id', $boxId);

        return $ticketsQuery;
    }
}
