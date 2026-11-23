<?php

namespace FluentSupport\App\Modules\CustomerPortal;

use FluentSupport\App\Modules\Customers\CustomerService;
use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

/**
 * The portal rules other code also needs: who the customer is, whether they may
 * act on a ticket, and storing a rating.
 */
class PortalService
{
    /**
     * The only ticket fields a customer receives. Anything not named here (agent
     * priority, threading fields, the mailbox row) never leaves the server.
     */
    const TICKET_FIELDS = [
        'id', 'ticket_number', 'display_ticket_number', 'serial_number', 'slug', 'title', 'content',
        'status', 'privacy', 'client_priority', 'product_id', 'product_source', 'mailbox_id',
        'customer_id', 'agent_id', 'created_by', 'closed_by', 'source', 'response_count',
        'first_response_time', 'total_close_time', 'last_agent_response', 'last_customer_response',
        'waiting_since', 'resolved_at', 'created_at', 'updated_at',
        // Set by the portal, and the relations it loads.
        'human_date', 'preview_response', 'custom_fields', 'created_by_agent',
        'customer', 'agent', 'closed_by_person', 'product', 'attachments',
    ];

    /**
     * The only reply fields a customer receives.
     */
    const RESPONSE_FIELDS = [
        'id', 'serial', 'ticket_id', 'person_id', 'conversation_type', 'content', 'source',
        'is_important', 'created_at', 'updated_at',
        'human_date', 'agent_feedback', 'person', 'attachments',
    ];

    /**
     * The only fields a customer receives about a person (customer or agent).
     */
    const PERSON_FIELDS = ['id', 'first_name', 'last_name', 'full_name', 'photo', 'avatar', 'person_type', 'title'];

    /**
     * The customer for $onBehalf (a remote portal), or for the logged-in user when
     * it is empty. Creates one only when $create is set.
     *
     * @param array  $onBehalf Sanitized with sanitizeOnBehalf().
     * @param string $ip
     * @param bool   $create
     * @return Customer|false
     */
    public static function findCustomer($onBehalf, $ip, $create = false)
    {
        if (!$onBehalf) {
            $user = get_user_by('ID', get_current_user_id());
            if (!$user) {
                return false;
            }

            $onBehalf = [
                'user_id'         => $user->ID,
                'email'           => $user->user_email,
                'last_ip_address' => $ip
            ];
        }

        return $create ? CustomerService::findOrCreate($onBehalf) : CustomerService::find($onBehalf);
    }

    /**
     * May this customer act on this ticket? Another customer's private ticket answers
     * 404, like an unknown one; a blocked customer or an add-on's refusal answers 403.
     * An agent acting on_behalf of the customer also keeps their own mailbox limits.
     *
     * @param Customer|false $customer
     * @param Ticket         $ticket
     * @param string|false   $action   Passed to the can_customer_access_ticket filter.
     * @param bool           $onBehalf The caller is an agent acting for the customer.
     * @return void
     * @throws ApiException
     */
    public static function assertCanAccess($customer, $ticket, $action = false, $onBehalf = false)
    {
        if (!$customer || ($ticket->privacy == 'private' && $customer->id != $ticket->customer_id)) {
            throw self::notFound();
        }

        // Logged as an internal note and not answered yet: the customer doesn't know it exists.
        if ($ticket->isHiddenFromCustomer()) {
            throw self::notFound();
        }

        // Site admins have full access and may not have an agent record at all.
        if ($onBehalf && !current_user_can('manage_options') && !(new AgentTicketAccess())->currentAgentCanAccess($ticket)) {
            throw self::notFound();
        }

        if (!$customer->canAccessPortal()) {
            throw ApiException::forbidden(esc_html__('Sorry, You do not have access to customer portal', 'fluent-support'));
        }

        $result = apply_filters('fluent_support/can_customer_access_ticket', true, $customer, $ticket, $action);

        if (!$result) {
            throw ApiException::forbidden(esc_html__('Sorry, You cannot access this ticket', 'fluent-support'));
        }

        if (is_wp_error($result)) {
            throw ApiException::forbidden(esc_html($result->get_error_message()));
        }
    }

    /**
     * Rate an agent's reply on a ticket the caller may open. Rating it the same way
     * again removes the rating. Each rating fires agent_feedback_received at most
     * once per reply, so clicking back and forth can't rerun workflows.
     *
     * @param string $rating 'like' or 'dislike'
     * @param int    $conversationId
     * @param int    $ticketId
     * @return Meta The rating row, created, updated or just deleted.
     * @throws ApiException
     */
    public static function recordRating($rating, $conversationId, $ticketId)
    {
        if (!in_array($rating, ['like', 'dislike'], true)) {
            throw new ApiException(esc_html__('Invalid feedback value provided', 'fluent-support'));
        }

        $conversation = Conversation::with('person')
            ->where('id', $conversationId)
            ->where('ticket_id', $ticketId)
            ->where('conversation_type', 'response')
            ->first();

        if (!$conversation || !$conversation->person || $conversation->person->person_type !== 'agent') {
            throw new ApiException(esc_html__('Invalid conversation for feedback', 'fluent-support'));
        }

        $where = ['object_id' => $conversationId, 'object_type' => 'conversation_meta', 'key' => 'agent_feedback_ratings'];
        $feedback = Meta::where($where)->first();

        if ($feedback && $feedback->value === $rating) {
            $feedback->delete();
            return $feedback;
        }

        if ($feedback) {
            $feedback->update(['value' => $rating]);
        } else {
            $feedback = Meta::create($where + ['value' => $rating]);
        }

        $firedKey = '_agent_feedback_fired_' . $rating;
        $alreadyFired = Meta::where(['object_type' => 'conversation_meta', 'object_id' => $conversationId, 'key' => $firedKey])->exists();
        $ticket = Ticket::with('customer')->find($ticketId);

        if (!$alreadyFired && $ticket) {
            Meta::create(['object_type' => 'conversation_meta', 'object_id' => $conversationId, 'key' => $firedKey, 'value' => current_time('mysql')]);

            /*
             * Fires when a customer likes or dislikes an agent's reply.
             *
             * @since 2.4.1
             * @param string       $rating       'like' or 'dislike'
             * @param Conversation $conversation The rated reply.
             * @param Ticket       $ticket
             * @param Customer     $customer
             */
            do_action('fluent_support/agent_feedback_received', $rating, $conversation, $ticket, $ticket->customer);
        }

        return $feedback;
    }

    /**
     * Keeps only scalar values, so sanitize_text_field() never gets an array.
     *
     * @param mixed $onBehalf
     * @return array
     */
    public static function sanitizeOnBehalf($onBehalf)
    {
        $sanitized = [];

        foreach (is_array($onBehalf) ? $onBehalf : [] as $key => $value) {
            if (is_scalar($value)) {
                $sanitized[sanitize_text_field($key)] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * @return ApiException 404.
     */
    public static function notFound()
    {
        return ApiException::notFound(esc_html__('Sorry, You do not have permission to this support ticket', 'fluent-support'));
    }
}
