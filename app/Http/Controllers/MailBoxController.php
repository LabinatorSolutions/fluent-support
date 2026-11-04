<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\MailerInbox\MailBoxService;
use FluentSupport\App\Services\Tickets\TicketQuery;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Support\Arr;

/**
 * Mailboxes (business inboxes) on the settings screens: the list, one
 * mailbox, create, update, the default, delete, the email templates of a
 * mailbox, and moving tickets between mailboxes.
 */
class MailBoxController extends Controller
{
    /**
     * Every mailbox with its ticket count.
     */
    public function index()
    {
        $counts = Ticket::groupBy('mailbox_id')->selectRaw('mailbox_id, COUNT(*) AS total')->pluck('total', 'mailbox_id')->all();

        $mailboxes = MailBox::all();
        foreach ($mailboxes as $mailbox) {
            $mailbox->tickets_count = (int) ($counts[$mailbox->id] ?? 0);
        }

        return ['mailboxes' => $mailboxes];
    }

    public function get($id)
    {
        return ['mailbox' => $this->find($id)];
    }

    /**
     * A web mailbox notifies its own address; an email (piped) mailbox gets its
     * admin address once piping is set up. The first mailbox is the default.
     */
    public function save(Request $request)
    {
        $data = $this->mailboxData($request);
        $data['settings'] = ['admin_email_address' => $data['box_type'] === 'email' ? '' : $data['email']];
        if (!MailBox::first()) {
            $data['is_default'] = 'yes';
        }

        return [
            'message' => __('Mailbox has been created successfully', 'fluent-support'),
            'mailbox' => MailBox::create($data)
        ];
    }

    public function update(Request $request, $id)
    {
        $mailbox = $this->find($id);
        $data = $this->mailboxData($request);

        if ($data['box_type'] === 'email' && empty($data['mapped_email'])) {
            throw ApiException::invalid(__('Mapped Email Address is required', 'fluent-support'));
        }

        $mailbox->fill($data);
        $mailbox->save();

        return [
            'message' => __('Mailbox has been saved', 'fluent-support'),
            'mailbox' => $mailbox
        ];
    }

    /**
     * Deletes the mailbox. Its tickets and the default flag move to fallback_id;
     * its email templates go with it.
     */
    public function delete(Request $request, $id)
    {
        $box = $this->find($id);
        $fallback = MailBox::find($request->getSafe('fallback_id', 'intval'));
        if (!$fallback || (int) $fallback->id === (int) $box->id) {
            throw ApiException::invalid(__('Fallback Box cannot be the same as MailBox ID', 'fluent-support'));
        }

        if ($box->is_default === 'yes') {
            $fallback->is_default = 'yes';
            $fallback->save();
        }

        /*
         * Fires before a mailbox is deleted.
         *
         * @since v1.0.0
         * @param object $box         Mailbox
         * @param object $fallbackBox Fallback mailbox
         */
        do_action('fluent_support/before_delete_email_box', $box, $fallback);

        $box->deleteAllMeta();
        $box->delete();
        // Not a move: fluent_support/mailbox_deleted is the event for this.
        Ticket::where('mailbox_id', $box->id)->update(['mailbox_id' => $fallback->id]);

        /*
         * Fires after a mailbox is deleted.
         *
         * @since v1.0.0
         * @param integer $mailBoxId
         * @param object  $fallbackBox Fallback mailbox
         */
        do_action('fluent_support/mailbox_deleted', (int) $box->id, $fallback);

        return ['message' => __('Selected Business has been deleted', 'fluent-support')];
    }

    public function setAsDefault($id)
    {
        $box = $this->find($id);
        MailBox::where('id', '!=', $box->id)->update(['is_default' => 'no']);
        $box->is_default = 'yes';
        $box->save();

        return ['message' => __('Selected Business has been set as default', 'fluent-support')];
    }

    /**
     * Moves tickets out of this mailbox; the current user may not move them into
     * a mailbox they are restricted from.
     */
    public function moveTickets(Request $request, $id)
    {
        $data = $request->only(['ticket_ids', 'new_box_id', 'move_type']);

        if (in_array((int) Arr::get($data, 'new_box_id'), array_map('intval', PermissionManager::getRestrictedMailboxIds()), true)) {
            throw ApiException::invalid(__('You do not have access to the specified mailbox', 'fluent-support'));
        }

        return (new MailBoxService())->moveTickets($data, $id);
    }

    /**
     * The mailbox's tickets for the move screen: customer, product and title filters.
     */
    public function getTickets(Request $request, $id)
    {
        $box = $this->find($id);
        $filters = (array) $request->get('filters', []);

        // Empty filters are left out: a 0 would match "no product".
        $query = (new TicketQuery(array_filter([
            'mailbox_id'  => (int) $box->id,
            'customer_id' => (int) Arr::get($filters, 'customer_id'),
            'product_id'  => (int) Arr::get($filters, 'product_id'),
        ])))->query()->with([
            'customer'         => fn($query) => $query->select(['first_name', 'last_name', 'email', 'id', 'avatar']),
            'agent'            => fn($query) => $query->select(['first_name', 'last_name', 'id']),
            'product',
            'tags',
            'preview_response' => fn($query) => $query->latest('id'),
        ]);

        $title = sanitize_text_field(Arr::get($filters, 'ticket_title', ''));
        if ($title !== '') {
            $query->where('title', 'LIKE', '%' . $GLOBALS['wpdb']->esc_like($title) . '%');
        }

        return ['tickets' => $query->paginate()];
    }

    /**
     * Every email template of the mailbox, and their keys.
     */
    public function getEmailsSetups($id)
    {
        $box = $this->find($id);
        $settings = new Settings();
        $keys = $settings->getEmailSettingsKeys();

        return [
            'email_configs' => array_map(fn($key) => $settings->getBoxEmailSettings($box, $key), $keys),
            'email_keys'    => $keys
        ];
    }

    public function getEmailSettings(Request $request, $id)
    {
        return [
            'email_settings' => (new Settings())->getBoxEmailSettings($this->find($id), $this->emailType($request))
        ];
    }

    /**
     * Saves one email template. Subject and body are required.
     */
    public function saveEmailSettings(Request $request, $id)
    {
        $box = $this->find($id);
        $emailType = $this->emailType($request);
        $input = (array) $request->get('email_settings', []);

        $data = [
            'key'              => sanitize_key(Arr::get($input, 'key', '')),
            'title'            => sanitize_text_field(Arr::get($input, 'title', '')),
            'email_subject'    => sanitize_text_field(Arr::get($input, 'email_subject', '')),
            'email_body'       => wp_kses_post(Arr::get($input, 'email_body', '')),
            'status'           => sanitize_text_field(Arr::get($input, 'status', '')),
            'can_edit_subject' => sanitize_text_field(Arr::get($input, 'can_edit_subject', '')),
            'send_attachments' => sanitize_text_field(Arr::get($input, 'send_attachments', '')),
        ];
        if ($data['email_subject'] === '' || $data['email_body'] === '') {
            throw ApiException::invalid(__('The email subject and body are required.', 'fluent-support'));
        }

        (new Settings())->saveBoxEmailSettings($box, $emailType, $data);

        return ['message' => __('Settings has been updated', 'fluent-support')];
    }

    private function find($id)
    {
        $box = MailBox::find((int) $id);
        if (!$box) {
            throw ApiException::notFound();
        }

        return $box;
    }

    /**
     * A known email template key (fluent_support/email_setting_keys).
     */
    private function emailType(Request $request)
    {
        $emailType = $request->getSafe('email_type', 'sanitize_text_field');
        if (!in_array($emailType, (new Settings())->getEmailSettingsKeys(), true)) {
            throw ApiException::invalid(__('Unknown email type.', 'fluent-support'));
        }

        return $emailType;
    }

    /**
     * The mailbox fields from the request's business; name and email are required.
     */
    private function mailboxData(Request $request)
    {
        $input = (array) $request->get('business', []);

        $data = [
            'name'         => sanitize_text_field(Arr::get($input, 'name', '')),
            'email'        => sanitize_email(Arr::get($input, 'email', '')),
            'box_type'     => sanitize_key(Arr::get($input, 'box_type', '')),
            'mapped_email' => sanitize_email(Arr::get($input, 'mapped_email', '')),
            'email_footer' => wp_kses_post(Arr::get($input, 'email_footer', '')),
            'is_default'   => Arr::get($input, 'is_default') === 'yes' ? 'yes' : 'no',
        ];
        if (isset($input['settings']) && is_array($input['settings'])) {
            $data['settings'] = map_deep($input['settings'], 'sanitize_text_field');
            if (isset($input['settings']['admin_email_address'])) {
                $data['settings']['admin_email_address'] = sanitize_email($input['settings']['admin_email_address']);
            }
        }

        if ($data['name'] === '' || $data['email'] === '') {
            throw ApiException::invalid(__('The name and email are required.', 'fluent-support'));
        }

        return $data;
    }
}
