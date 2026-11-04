<?php

namespace FluentSupport\App\Modules\CustomerPortal;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Hooks\Handlers\ReCaptchaHandler;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Support\Arr;

/**
 * The customer portal. The caller is the logged-in customer, a remote portal
 * acting on_behalf of one (on_behalf), or a signed ticket link
 * (intended_ticket_hash). Ticket IDs in the routes are public ticket numbers.
 */
class PortalController extends Controller
{
    public function index(Request $request)
    {
        $customer = PortalService::findCustomer($this->onBehalf($request), $request->getIp());
        $perPage = min(max($request->getInt('per_page', 10), 1), 100);

        if (!$customer) {
            // No customer record yet: an empty page in the paginator's shape.
            return ['tickets' => ['data' => [], 'current_page' => $request->getInt('page', 1), 'last_page' => 1, 'per_page' => $perPage, 'total' => 0, 'from' => null, 'to' => null]];
        }

        if (!$customer->canAccessPortal()) {
            throw new ApiException(__('Your account is not active. Please contact support.', 'fluent-support'), 403, ['error_type' => '403']);
        }

        $canAccess = apply_filters('fluent_support/can_customer_access_portal', true, $customer);

        if (is_wp_error($canAccess)) {
            throw new ApiException($canAccess->get_error_message(), 403, ['error_type' => $canAccess->get_error_code()]);
        }

        $input = $request->getSafe([
            'filter_type'        => 'sanitize_text_field',
            'search'             => 'sanitize_text_field',
            'filters.product_id' => 'intval',
            'sorting.sort_by'    => 'sanitize_sql_orderby',
            'sorting.sort_type'  => 'sanitize_sql_orderby'
        ]);

        $statuses = ['open' => ['new', 'active', 'on-hold'], 'closed' => ['closed']];

        $query = Ticket::where('customer_id', $customer->id)
            ->filterByStatues(Arr::get($statuses, (string) Arr::get($input, 'filter_type'), []))
            ->searchBy(Arr::get($input, 'search'))
            ->filterByProductId(Arr::get($input, 'filters.product_id'))
            ->orderBy(Arr::get($input, 'sorting.sort_by') ?: 'created_at', Arr::get($input, 'sorting.sort_type') ?: 'desc');

        // An agent acting for a customer only sees the tickets they could open themselves.
        // Site admins have full access and may not have an agent record at all.
        if ($this->onBehalf($request) && !current_user_can('manage_options')) {
            (new AgentTicketAccess())->applyAccessScope($query);
        }

        do_action_ref_array('fluent_support/customer_portal/tickets_query', [&$query, $customer, $request]);

        $tickets = $query->paginate($perPage);

        foreach ($tickets as $ticket) {
            $ticket->setVisible(PortalService::TICKET_FIELDS);
            $ticket->human_date = $this->humanDate($ticket->created_at);

            // A raw fs_conversations row, so it is picked by hand.
            $lastResponse = $ticket->getLastResponse();
            $ticket->preview_response = $lastResponse ? Arr::only((array) $lastResponse, PortalService::RESPONSE_FIELDS) : null;
        }

        return ['tickets' => $tickets];
    }

    public function show(Request $request, $ticket_id)
    {
        // The customer first, then their ticket (findTicket: a signed link's customer is
        // the ticket's own). Person emails are loaded only for the photo fallback
        // (Gravatar); the field lists below leave them out.
        list($ticket) = $this->findTicket($request, $ticket_id, false, [
            'customer'    => fn($query) => $query->select(['id', 'first_name', 'last_name', 'email', 'avatar', 'person_type']),
            'agent'       => fn($query) => $query->select(['id', 'first_name', 'last_name', 'email', 'avatar', 'person_type', 'title']),
            'product'     => fn($query) => $query->select(['id', 'title']),
            'attachments' => fn($query) => $query->where('status', 'active'),
        ]);

        // Who closed it. Not loaded for an open ticket.
        if ($ticket->status == 'closed') {
            $ticket->load(['closed_by_person' => fn($query) => $query->select(['id', 'first_name', 'last_name', 'email', 'avatar'])]);
        }

        // Its thread: replies and merge/split notes, newest first.
        $responses = Conversation::where('ticket_id', $ticket->id)
            ->filterByType(['response', 'ticket_merge_activity', 'ticket_split_activity'])
            ->with([
                'person'      => fn($query) => $query->select(['id', 'first_name', 'last_name', 'email', 'avatar', 'person_type', 'title']),
                'attachments' => fn($query) => $query->where('status', 'active'),
            ])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $ratings = [];
        if ($responses->count() && defined('FLUENTSUPPORTPRO_PLUGIN_VERSION') && Helper::isAgentFeedbackEnabled()) {
            $ratings = Meta::where('object_type', 'conversation_meta')
                ->where('key', 'agent_feedback_ratings')
                ->whereIn('object_id', $responses->pluck('id')->toArray())
                ->pluck('value', 'object_id')
                ->toArray();
        }

        // Format the ticket.
        $ticket->human_date = $this->humanDate($ticket->created_at);
        $ticket->content = Helper::refreshSignedAttachmentUrls($ticket->content, $ticket->id);
        $content = apply_filters('fluent_support/ticket_content_before_render', $ticket->content, $ticket);
        $ticket->content = apply_filters('fluent_support/ticket_content_after_render', links_add_target(make_clickable($content)), $ticket);

        if (defined('FLUENTSUPPORTPRO')) {
            $ticket->custom_fields = $ticket->customData('public', true);
        }

        // Set when an agent opened the ticket for the customer: their name and photo only.
        if ($ticket->created_by && ($creator = Agent::find($ticket->created_by))) {
            $ticket->created_by_agent = [
                'full_name'       => $creator->full_name,
                'photo'           => $creator->photo,
                // The agent's first response is the opening message; skip the placeholder body.
                'agent_initiated' => $ticket->isAgentInitiated(),
            ];
        }

        $ticket->setVisible(PortalService::TICKET_FIELDS);
        foreach (['customer', 'agent', 'closed_by_person'] as $person) {
            if ($ticket->relationLoaded($person) && $ticket->{$person}) {
                $ticket->{$person}->setVisible(PortalService::PERSON_FIELDS);
            }
        }

        // Format the thread.
        $contents = Helper::refreshSignedAttachmentUrlsInContents($responses->pluck('content', 'id')->toArray(), $ticket->id);

        foreach ($responses as $response) {
            if (isset($ratings[$response->id])) {
                $response->agent_feedback = $ratings[$response->id];
            }

            $response->human_date = $this->humanDate($response->created_at);
            $content = apply_filters('fluent_support/response_content_before_render', $contents[$response->id] ?? $response->content, $response, null);
            $response->content = apply_filters('fluent_support/response_content_after_render', links_add_target(make_clickable($content)), $response, null);

            $response->setVisible(PortalService::RESPONSE_FIELDS);
            if ($response->person) {
                $response->person->setVisible(PortalService::PERSON_FIELDS);
            }
        }

        return [
            'ticket'     => $ticket,
            'responses'  => $responses,
            'sign_on_id' => $ticket->customer_id
        ];
    }

    public function store(Request $request)
    {
        $onBehalf = $this->onBehalf($request);

        // A remote portal filing for a customer can't answer a captcha. That takes a user
        // allowed to act on_behalf, never a flag the client sends.
        if (!($onBehalf && PortalPolicy::canActOnBehalf()) && ReCaptchaHandler::isRecaptchaApplicable('ticket_form')) {
            $token = $request->getSafe('g-recaptcha-response', 'sanitize_text_field');
            if (!$token || !ReCaptchaHandler::validateRecaptcha($token, null, null, 'create_ticket')) {
                throw new ApiException(__('Your recaptcha is not verified', 'fluent-support'), 422, ['error_type' => '422']);
            }
        }

        $customData = $request->get('custom_data', []);
        $customData = is_array($customData) ? map_deep($customData, 'sanitize_text_field') : [];

        $this->validateTicketForm($request, $customData);

        $mailbox = MailBox::find($request->getInt('mailbox_id')) ?: Helper::getDefaultMailBox();
        $mailboxId = $mailbox ? (int) $mailbox->id : null;

        // An agent acting for a customer may only file into mailboxes they can access.
        // Checked before the customer is found, which may create one.
        if ($onBehalf && !current_user_can('manage_options') && in_array($mailboxId, (new AgentTicketAccess())->getRestrictedMailboxIds(), true)) {
            throw new ApiException(__('You do not have access to this mailbox.', 'fluent-support'), 403, ['error_type' => '403']);
        }

        $customer = PortalService::findCustomer($onBehalf, $request->getIp(), true);

        if (!$customer) {
            throw new ApiException(__('Unable to identify. Please make sure you have provided correct information.', 'fluent-support'), 403, ['error_type' => '403']);
        }

        $data = [
            'title'       => $request->getSafe('title', 'sanitize_text_field'),
            'content'     => Helper::sanitizeHtmlContent($request->get('content')),
            'custom_data' => $customData,
        ];

        if ($request->has('product_id')) {
            $data['product_id'] = $request->getInt('product_id');
        }

        if ($request->has('client_priority')) {
            $data['client_priority'] = $request->getSafe('client_priority', 'sanitize_text_field');
        }

        if (is_array($request->get('attachments'))) {
            $data['attachments'] = map_deep($request->get('attachments'), 'sanitize_text_field');
        }

        $canCreate = apply_filters('fluent_support/can_customer_create_ticket', true, $customer, $data);

        if (!$canCreate || is_wp_error($canCreate)) {
            throw new ApiException(
                is_wp_error($canCreate) ? $canCreate->get_error_message() : __('Sorry you cannot create ticket', 'fluent-support'),
                422,
                ['error_type' => is_wp_error($canCreate) ? $canCreate->get_error_code() : 'general_error']
            );
        }

        if (!$customer->canAccessPortal()) {
            throw new ApiException(__('Sorry, You do not have access to customer portal', 'fluent-support'), 403, ['error_type' => '403']);
        }

        $data += [
            'customer_id'    => $customer->id,
            'mailbox_id'     => $mailboxId,
            'product_source' => 'local',
            'source'         => 'web',
            // TicketService reads custom_fields; custom_data stays for create_ticket_data listeners.
            'custom_fields'  => $customData,
        ];

        if ($messageId = Helper::generateMessageID($customer->email)) {
            $data['message_id'] = $messageId;
        }

        // Fields the ticket form hides are ignored if a client sends them anyway.
        $disabledFields = (array) apply_filters('fluent_support/disabled_ticket_fields', []);
        if (in_array('product_services', $disabledFields, true)) {
            unset($data['product_id']);
        }
        if (in_array('priority', $disabledFields, true)) {
            unset($data['client_priority']);
        }

        $ticket = (new TicketService())->storeCustomerTicket($data, $customer, (bool) $onBehalf);

        return [
            'message' => __('Ticket has been created successfully', 'fluent-support'),
            'ticket'  => $ticket->setVisible(PortalService::TICKET_FIELDS)
        ];
    }

    public function reply(Request $request, $ticket_id)
    {
        // The raw content, so markup that cleans down to nothing gets "write a reply" below.
        $this->validate($request->all(), ['content' => 'required'], [
            'content.required' => __('Reply content is required', 'fluent-support')
        ]);

        list($ticket, $customer) = $this->findTicket($request, $ticket_id, 'response');

        $data = [
            'content'      => Helper::sanitizeHtmlContent($request->get('content')),
            'close_ticket' => $request->getSafe('close_ticket', 'sanitize_text_field'),
        ];

        if (is_array($request->get('attachments'))) {
            $data['attachments'] = map_deep($request->get('attachments'), 'sanitize_text_field');
        }

        $canReply = apply_filters('fluent_support/can_customer_create_response', true, $ticket->customer, $ticket, $data);

        if (!$canReply || is_wp_error($canReply)) {
            throw ApiException::forbidden(is_wp_error($canReply) ? $canReply->get_error_message() : __('Sorry you cannot create response', 'fluent-support'));
        }

        // Checked before anything changes, so a blank reply neither reopens nor saves.
        if (!$this->hasText($data['content'])) {
            throw ApiException::invalid(__('Please write a reply before sending.', 'fluent-support'));
        }

        // A reply reopens a closed ticket, as an emailed reply does, before the reply is
        // saved so its hooks see an active ticket. Not when the customer closes it with this reply.
        if ($ticket->status == 'closed' && $data['close_ticket'] != 'yes') {
            (new TicketService())->reopen($ticket, $customer);
        }

        $created = (new ResponseService())->createResponse($data + ['conversation_type' => 'response'], $customer, $ticket);

        if (!$created) {
            throw ApiException::invalid(__('Please write a reply before sending.', 'fluent-support'));
        }

        $response = $created['response'];
        $response->content = Helper::refreshSignedAttachmentUrls($response->content, $ticket->id);
        $response->load(['attachments' => function ($query) {
            $query->where('status', 'active');
        }]);

        // The portal adds the reply to the thread, which shows the author's name and photo.
        if ($response->person) {
            $response->person->setVisible(PortalService::PERSON_FIELDS);
        }

        return [
            'message'  => __('Reply has been added', 'fluent-support'),
            'response' => $response->setVisible(PortalService::RESPONSE_FIELDS),
            'ticket'   => self::status(Ticket::find($ticket->id))
        ];
    }

    public function close(Request $request, $ticket_id)
    {
        list($ticket, $customer) = $this->findTicket($request, $ticket_id, 'close');

        return [
            'message' => __('Ticket has been closed', 'fluent-support'),
            'ticket'  => self::status((new TicketService())->close($ticket, $customer))
        ];
    }

    public function reopen(Request $request, $ticket_id)
    {
        list($ticket, $customer) = $this->findTicket($request, $ticket_id, 'reopen');

        return [
            'message' => __('Ticket has been opened again', 'fluent-support'),
            'ticket'  => self::status((new TicketService())->reopen($ticket, $customer))
        ];
    }

    public function rate(Request $request, $ticket_id)
    {
        list($ticket) = $this->findTicket($request, $ticket_id, 'feedback');

        return PortalService::recordRating(
            $request->getSafe('approval_status', 'sanitize_text_field'),
            $request->getInt('conversation_id'),
            $ticket->id
        );
    }

    public function options()
    {
        return [
            'support_products'           => Product::select(['id', 'title'])->orderedByTitle()->get(),
            'customer_ticket_priorities' => Helper::customerTicketPriorities()
        ];
    }

    public function customFields()
    {
        return ['custom_fields_rendered' => apply_filters('fluent_support/custom_fields_rendered', [], null, 'public')];
    }

    public function logout()
    {
        wp_logout();

        return $this->sendSuccess(['message' => __('You have been logged out', 'fluent-support')]);
    }

    /**
     * After an action the portal only reads the ticket status.
     *
     * @param Ticket $ticket
     * @return array
     */
    public static function status($ticket)
    {
        return [
            'id'            => (int) $ticket->id,
            'serial_number' => (int) $ticket->serial_number,
            'status'        => $ticket->status,
            'resolved_at'   => $ticket->resolved_at,
        ];
    }

    private function onBehalf(Request $request)
    {
        return PortalService::sanitizeOnBehalf($request->get('on_behalf', []));
    }

    /**
     * The ticket the caller may act on, and the customer acting. A signed link opens
     * its own ticket for its own customer; everyone else only finds their own
     * tickets. A ticket they may not open answers 404, the same as an unknown number.
     *
     * @return array [Ticket, Customer]
     * @throws ApiException
     */
    private function findTicket(Request $request, $ticketNumber, $action, array $with = ['customer'])
    {
        $hash = $request->getSafe('intended_ticket_hash', 'sanitize_text_field', '');
        $onBehalf = $this->onBehalf($request);

        if ($hash && Helper::isPublicSignedTicketEnabled()) {
            $ticket = Ticket::wherePublicIdentifier($ticketNumber)->with($with)->first();
            $ticket = ($ticket && hash_equals((string) $ticket->hash, $hash)) ? $ticket : null;
            // The full row: the loaded relation may lack status, and the access check reads it.
            $customer = $ticket ? Customer::find($ticket->customer_id) : null;
        } else {
            // With signed links off, a hash from the API user (not on_behalf) is a remote
            // portal relaying an anonymous visitor: never let it become the API user's customer.
            if ($hash && !$onBehalf && PortalPolicy::canActOnBehalf()) {
                throw PortalService::notFound();
            }

            $customer = PortalService::findCustomer($onBehalf, $request->getIp());
            $ticket = $customer
                ? Ticket::where('customer_id', $customer->id)->wherePublicIdentifier($ticketNumber)->with($with)->first()
                : null;
        }

        if (!$ticket) {
            throw PortalService::notFound();
        }

        PortalService::assertCanAccess($customer, $ticket, $action, (bool) $onBehalf);

        return [$ticket, $customer];
    }



    /**
     * The required fields come from settings and from Pro's custom fields.
     *
     * @param Request $request
     * @param array   $customData Sanitized custom_data.
     * @return void
     * @throws ApiException|\FluentSupport\Framework\Validator\ValidationException
     */
    private function validateTicketForm(Request $request, array $customData)
    {
        $rules = $this->app->applyCustomFilters('custom_field_required_before_ticket_create', [
            'required_fields' => ['title' => 'required', 'content' => 'required'],
            'error_messages'  => [
                'title.required'   => __('Title is required', 'fluent-support'),
                'content.required' => __('Content is required', 'fluent-support')
            ]
        ]);

        if (Arr::get(Helper::getOption('_ticket_form_settings', []), 'product_required_field') === 'yes' && Product::count() > 0) {
            $rules['required_fields']['product_id'] = 'required';
            $rules['error_messages']['product_id.required'] = __('Product is required', 'fluent-support');
        }

        $defaultData = [
            'ticket_title'   => $request->getSafe('title', 'sanitize_text_field'),
            'ticket_content' => Helper::sanitizeHtmlContent($request->get('content'))
        ];

        if ($request->has('product_id')) {
            $defaultData['ticket_product_id'] = $request->getInt('product_id');
        }

        if ($request->has('client_priority')) {
            $defaultData['ticket_client_priority'] = $request->getSafe('client_priority', 'sanitize_text_field');
        }

        $rules = $this->app->applyCustomFilters('custom_field_required_by_conditions_before_ticket_create', [
            'required_fields' => $rules['required_fields'],
            'error_messages'  => $rules['error_messages'],
            'custom_data'     => $customData,
            'default_data'    => $defaultData
        ]);

        if (!isset($rules['required_fields']) && !isset($rules['error_messages'])) {
            throw new ApiException(__('Invalid form data submitted', 'fluent-support'), 400, ['error_type' => '400']);
        }

        $this->validate($request->all(), (array) ($rules['required_fields'] ?? []), (array) ($rules['error_messages'] ?? []));
    }

    /**
     * Whether a reply has something to read: text, or an image. Editors leave markup
     * such as <p>&nbsp;</p> behind when the text is deleted.
     *
     * @param mixed $content
     * @return bool
     */
    private function hasText($content)
    {
        // ResponseService refuses whatever empty() treats as empty, "0" included.
        if (!is_string($content) || empty($content)) {
            return false;
        }

        if (stripos($content, '<img') !== false) {
            return true;
        }

        $text = wp_strip_all_tags(str_ireplace(['&nbsp;', '&#160;'], ' ', $content));

        return trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0") !== '';
    }

    private function humanDate($dateTime)
    {
        // translators: %s is the time duration (e.g., "2 hours", "3 days")
        return sprintf(__('%s ago', 'fluent-support'), human_time_diff(strtotime($dateTime), current_time('timestamp')));
    }
}
