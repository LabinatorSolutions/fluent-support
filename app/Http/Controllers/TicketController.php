<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Modules\Auth\AuthService;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Support\Arr;
use FluentSupport\App\Http\Requests\TicketRequest;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\FluentCRMServices;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\ProfileInfoService;
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\App\Modules\Customers\CustomerService;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\App\Services\Tickets\TicketFormatter;
use FluentSupport\App\Services\Tickets\TicketQuery;
use FluentSupport\App\Models\AgentGroup;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\App\Services\Tickets\TicketStats;
use FluentSupport\App\Services\Integrations\FluentBooking\FluentBookingService;

/**
 * Agent-side REST endpoints for tickets.
 */
class TicketController extends Controller
{
    /**
     * Most tickets one bulk request may change. Above the largest list page
     * size (150), so a full page always fits.
     */
    const MAX_BULK_TICKETS = 200;

    /**
     * Current user's profile, permissions and optionally the portal settings.
     *
     * @param Request $request
     * @return array
     */
    public function me(Request $request)
    {
        $user = wp_get_current_user();

        $settings = [
            'user_id'     => $user->ID,
            'email'       => $user->user_email,
            'person'      => Helper::getAgentByUserId($user->ID),
            'permissions' => PermissionManager::currentUserPermissions(),
        ];

        if ($request->getSafe('with_portal_settings', 'sanitize_text_field')) {
            $mimeHeadings = Helper::getAcceptedMimeHeadings();
            $businessSettings = (new \FluentSupport\App\Services\EmailNotification\Settings())->globalBusinessSettings();
            $maxFileSize = absint($businessSettings['max_file_size']);

            $portalSettings = [
                'support_products'           => \FluentSupport\App\Models\Product::select(['id', 'title'])->orderedByTitle()->get(),
                'customer_ticket_priorities' => Helper::customerTicketPriorities(),
                'has_file_upload'            => !!Helper::ticketAcceptedFileMiles(),
                'has_rich_text_editor'       => true,
                'max_file_size'              => $maxFileSize,
                'mime_headings'              => $mimeHeadings
            ];

            $portalSettings = apply_filters('fluent_support/customer_portal_vars', $portalSettings);
            $settings['portal_settings'] = $portalSettings;
        }

        return $settings;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $tickets = TicketQuery::fromRequest($request->all())->query()->with([
            'customer'         => function ($query) {
                $query->select(['first_name', 'last_name', 'email', 'id', 'avatar']);
            }, 'agent'         => function ($query) {
                $query->select(['first_name', 'last_name', 'email', 'avatar', 'id']);
            },
            'mailbox',
            'product',
            'tags',
            'preview_response' => function ($query) {
                $query->latest('id');
            }
        ])->paginate();

        TicketFormatter::listRows($tickets);

        /**
         * Filter a page of the ticket list before it is returned.
         *
         * @param \FluentSupport\Framework\Pagination\LengthAwarePaginator $tickets
         */
        $tickets = apply_filters('fluent_support/tickets_list', $tickets);

        $perPage = $request->getSafe('per_page', 'intval', 15);

        // Small pages come from the board view, which shows live activity.
        if ($perPage < 15) {
            TicketHelper::loadBatchLiveActivities($tickets);
        }

        return [
            'tickets' => $tickets
        ];
    }

    /**
     * Counts for the ticket list sidebar views, limited to tickets the current agent can see.
     *
     * @return array
     */
    public function getSidebarCounts()
    {
        $agent = Helper::getAgentByUserId();

        if (!$agent) {
            return $this->sendError([
                'message' => __('Sorry, you do not have permission to view tickets.', 'fluent-support')
            ], 403);
        }

        return [
            'counts' => (new TicketStats())->getSidebarCounts($agent)
        ];
    }

    /**
     * Create a ticket, creating the customer and optionally a WP user first.
     *
     * @param TicketRequest $request
     * @return array
     */
    public function createTicket(TicketRequest $request)
    {
        try {
            $data = $request->sanitize();
            $ticketData = $data['ticket'];
            $maybeNewCustomer = Arr::get($data, 'newCustomer', []);

            if (!empty($data['attachments'])) {
                $ticketData['attachments'] = $data['attachments'];
            }

            if (empty($ticketData['customer_id'])) {
                // findOrCreate() below links the new customer to this user by email.
                if (Arr::get($ticketData, 'create_wp_user') == 'yes' && !empty($maybeNewCustomer['username']) && !username_exists($maybeNewCustomer['username'])) {
                    AuthService::createUser($maybeNewCustomer);
                }

                $email = Arr::get($maybeNewCustomer, 'email');
                if (!$email || !is_email($email)) {
                    return $this->sendError([
                        'message' => __('A valid email is required to create a ticket', 'fluent-support')
                    ]);
                }

                // An existing customer with this email is reused as it is; a new one gets the
                // form's name and email and is linked to the WordPress user with the email
                // (made just above when asked). Nothing else the request sends is written.
                $customer = CustomerService::find(['email' => $email])
                    ?: CustomerService::findOrCreate(Arr::only($maybeNewCustomer, ['first_name', 'last_name', 'email']));
                if (!$customer || !$customer->exists) {
                    return $this->sendError([
                        'message' => __('Customer could not be created', 'fluent-support')
                    ]);
                }
                $ticketData['customer_id'] = $customer->id;
            }

            // Creating a ticket for another agent is an assignment.
            if (!PermissionManager::currentUserCan('fst_assign_agents')) {
                unset($ticketData['agent_id']);
            }

            $customer = Customer::findOrFail($ticketData['customer_id']);

            $createdTicket = (new TicketService())->storeTicket($ticketData, $customer);
            // Loaded when the ticket was created with an agent.
            if ($createdTicket->relationLoaded('agent')) {
                TicketFormatter::person($createdTicket->agent);
            }

            return [
                'message' => __('Ticket has been created successfully', 'fluent-support'),
                'ticket'  => $createdTicket
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param Request $request
     * @param int $ticket_id
     * @return array
     */
    public function getTicket(Request $request, $ticket_id)
    {
        try {
            $agent = Helper::getAgentByUserId();

            $defaultWith = ['customer', 'agent', 'product', 'mailbox', 'tags', 'attachments' => function ($q) {
                $q->where('status', 'active');
            }];

            // `with` may only narrow the default relations. The access check covers this
            // ticket alone, so a nested name like customer.tickets would expose tickets
            // outside the agent's scope.
            $requestedWith = $request->get('with');
            $ticketWith = [];
            if (is_array($requestedWith)) {
                foreach ($requestedWith as $relation) {
                    if (!is_string($relation)) {
                        continue;
                    }
                    if (isset($defaultWith[$relation])) {
                        $ticketWith[$relation] = $defaultWith[$relation];
                    } elseif (in_array($relation, $defaultWith, true)) {
                        $ticketWith[] = $relation;
                    }
                }
            }

            if (!$ticketWith) {
                $ticketWith = $defaultWith;
            }

            $ticket = Ticket::with($ticketWith)->findOrFail($ticket_id);

            $responseWith = [
                'person',
                'ccinfo',
                'attachments' => function ($q) {
                    $q->where('status', 'active');
                }
            ];

            $ticket->load(['responses' => function ($q) use ($responseWith) {
                $q->with($responseWith);
            }]);

            $scheduledRows = Conversation::where('ticket_id', $ticket->id)
                ->where('conversation_type', 'schedule')
                ->with($responseWith)
                ->get();
            $renderedRows = $ticket->responses->concat($scheduledRows);

            $restrictedBusinessBoxes = PermissionManager::getRestrictedMailboxIds();

            if (in_array($ticket->mailbox_id, $restrictedBusinessBoxes)) {
                throw new \Exception(esc_html__('Ticket cannot be fetched due to restricted mailbox', 'fluent-support'), 403);
            }

            $this->ensureCanAccessTicket($ticket);

            if ($ticket->customer) {
                $customFieldsKey = Helper::getCustomRegistrationFieldKeys();
                $ticket->customer->custom_field_keys = $customFieldsKey;

                if ($ticket->customer->user_id) {
                    $customFieldKeysUsingHook = apply_filters('fluent_support/custom_registration_form_fields_key', []);
                    if (!empty($customFieldKeysUsingHook)) {
                        $allUserMeta = get_user_meta($ticket->customer->user_id);
                        foreach ($customFieldKeysUsingHook as $key) {
                            if (isset($allUserMeta[$key][0]) && $allUserMeta[$key][0]) {
                                $ticket->customer->$key = $allUserMeta[$key][0];
                            }
                        }
                    }
                }

                $ticket->customer->profile_edit_url = $ticket->customer->getUserProfileEditUrl();
            }

            if ($ticket->status == 'closed') {
                $ticket->load('closed_by_person');
            }

            if (defined('FLUENTSUPPORTPRO_PLUGIN_VERSION') && Helper::isAgentFeedbackEnabled()) {
                $responseIds = $ticket->responses->pluck('id')->toArray();
                $feedbacks = Meta::where('object_type', 'conversation_meta')
                    ->where('key', 'agent_feedback_ratings')
                    ->whereIn('object_id', $responseIds)
                    ->get()
                    ->keyBy('object_id');

                foreach ($ticket->responses as $response) {
                    if ($feedbacks->has($response->id)) {
                        $response->agent_feedback = $feedbacks->get($response->id)->value;
                    }
                }
            }

            $contents = ['ticket' => $ticket->content];
            foreach ($renderedRows as $response) {
                $contents['response_' . $response->id] = $response->content;
            }

            $contents = Helper::refreshSignedAttachmentUrlsInContents($contents, $ticket->id);
            $ticket->content = $contents['ticket'];

            foreach ($renderedRows as $response) {
                $responseKey = 'response_' . $response->id;
                if (isset($contents[$responseKey])) {
                    $response->content = $contents[$responseKey];
                }

                $responseContent = apply_filters(
                    'fluent_support/response_content_before_render',
                    $response->content,
                    $response,
                    $ticket
                );

                if ($response->conversation_type === 'note') {
                    $responseContent = wpautop($responseContent, false);
                } else {
                    $responseContent = links_add_target(make_clickable(wpautop($responseContent, false)));
                }

                $response->content = apply_filters(
                    'fluent_support/response_content_after_render',
                    $responseContent,
                    $response,
                    $ticket
                );

                if (!empty($response->ccinfo)) {
                    $val = Helper::safeUnserialize($response->ccinfo->value);
                    if (isset($val['cc_email']) && !empty($val['cc_email'])) {
                        $response->cc_info = $val['cc_email'];
                    } else {
                        $response->cc_info = '';
                    }
                } else {
                    $response->cc_info = '';
                }
            }

            $ticketContent = apply_filters(
                'fluent_support/ticket_content_before_render',
                $ticket->content,
                $ticket
            );

            $ticketContent = links_add_target(make_clickable(wpautop($ticketContent, false)));

            $ticket->content = apply_filters(
                'fluent_support/ticket_content_after_render',
                $ticketContent,
                $ticket
            );

            $ticket->live_activity = TicketHelper::getActivity($ticket->id, $agent->id);

            $ccInfo = $ticket->getSettingsValue('cc_email', []);
            $ticket->carbon_copy = !empty($ccInfo) ? implode(', ', $ccInfo) : '';

            if (defined('FLUENTSUPPORTPRO')) {
                $ticket->custom_fields = $ticket->customData('admin', true);
            }

            // Set when an agent opened the ticket on the customer's behalf.
            if ($ticket->created_by) {
                $ticket->load('created_by_person');
                if ($ticket->created_by_person) {
                    $isAgentInitiated = $ticket instanceof Ticket && $ticket->isAgentInitiated();
                    $ticket->created_by_agent = [
                        'id'        => $ticket->created_by_person->id,
                        'full_name' => $ticket->created_by_person->full_name,
                        'agent_initiated' => $isAgentInitiated,
                    ];
                }
                $ticket->unsetRelation('created_by_person');
            }

            foreach ($renderedRows as $response) {
                TicketFormatter::person($response->person);
            }

            if ($ticket->relationLoaded('agent')) {
                TicketFormatter::person($ticket->agent);
            }

            if ($ticket->relationLoaded('closed_by_person')) {
                TicketFormatter::person($ticket->closed_by_person);
            }

            $data = [
                'ticket'    => $ticket,
                'responses' => $ticket->responses,
                'agent_id'  => $agent->id
            ];

            if (defined('FLUENTSUPPORTPRO') && $ticket->watchers) {
                $data['watchers'] = TicketHelper::getWatchers($ticket->watchers);
            }

            $withData = $request->get('with_data', null);
            $withDataArray = is_array($withData) ? map_deep($withData, 'sanitize_text_field') : [];

            if (defined('FLUENTCRM') && in_array('fluentcrm_profile', $withDataArray)) {
                $data['fluentcrm_profile'] = Helper::getFluentCrmContactData($ticket->customer);
            }

            /**
             * Filter the ticket view response.
             *
             * @param array $data
             * @param \FluentSupport\Framework\Database\Orm\Model $ticket The viewed ticket
             * @param \FluentSupport\Framework\Support\Collection $scheduledRows Rendered unsent scheduled replies, left out of `responses`
             */
            return apply_filters('fluent_support/ticket_view_data', $data, $ticket, $scheduledRows);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function getMentionableAgents(Request $request, $ticket_id)
    {
        try {
            $ticket = Ticket::findOrFail($ticket_id);

            if (in_array($ticket->mailbox_id, PermissionManager::getRestrictedMailboxIds())) {
                throw new \Exception(esc_html__('Ticket cannot be fetched due to restricted mailbox', 'fluent-support'), 403);
            }

            $this->ensureCanAccessTicket($ticket);

            $search = trim($request->getSafe('search', 'sanitize_text_field', ''));
            $limit = min(max(absint($request->getSafe('limit', 'intval', 20)), 1), 50);

            return [
                'agents' => $this->getMentionableAgentList($ticket, $search, $limit)
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    protected function getMentionableAgentList($ticket, $search, $limit)
    {
        $allAgents = Agent::select(['id', 'first_name', 'last_name', 'email', 'user_id'])
            ->mentionBy($search)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        if ($allAgents->isEmpty()) {
            return [];
        }

        $restrictions = $this->getAgentRestrictionsMap($allAgents->pluck('id')->all());
        $ticketAccess = new AgentTicketAccess();
        $results = [];

        foreach ($allAgents as $agent) {
            if (!$ticketAccess->canAccess($agent, $ticket, $restrictions[$agent->id] ?? [])) {
                continue;
            }

            $results[] = [
                'id'         => strval($agent->id),
                'first_name' => $agent->first_name,
                'last_name'  => $agent->last_name,
                'email'      => $agent->email,
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    protected function getAgentRestrictionsMap(array $agentIds)
    {
        if (!$agentIds) {
            return [];
        }

        $metas = Meta::where('object_type', 'person_meta')
            ->where('key', 'agent_restrictions')
            ->whereIn('object_id', $agentIds)
            ->get();

        $restrictions = [];
        foreach ($metas as $meta) {
            $restrictions[$meta->object_id] = Helper::safeUnserialize($meta->value) ?: [];
        }

        return $restrictions;
    }

    public function getFluentBookingEventTypes()
    {
        try {
            // All FluentBooking endpoints need manage permission. View-only agents can't book meetings.
            $this->ensureCanManageTickets();

            $service = new FluentBookingService();
            $eventTypes = $service->getEventTypes();

            return [
                'status'      => $service->getStatus($eventTypes),
                'event_types' => $eventTypes
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function createFluentBookingLink(Request $request, $ticket_id)
    {
        try {
            $this->ensureCanManageTickets();

            $ticket = Ticket::with('customer')->findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $eventId = $request->getSafe('event_type_id', 'intval');

            if (!$eventId) {
                throw new \Exception(esc_html__('Please select a FluentBooking event type.', 'fluent-support'));
            }

            return (new FluentBookingService())->createBookingLink(
                $ticket,
                $eventId,
                wp_kses_post(Helper::decodeNonMarkupEntities($request->get('message'))),
                $request->get('selected_slots', []),
                $request->getSafe('timezone', 'sanitize_text_field', '')
            );
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function getFluentBookingAvailability(Request $request, $ticket_id)
    {
        try {
            $this->ensureCanManageTickets();

            $ticket = Ticket::with('customer')->findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $eventId = $request->getSafe('event_type_id', 'intval');

            if (!$eventId) {
                throw new \Exception(esc_html__('Please select a FluentBooking event type.', 'fluent-support'));
            }

            return [
                'availability' => (new FluentBookingService())->getAvailabilitySlots(
                    $eventId,
                    $request->getSafe('range', 'sanitize_key', 'next_3_days'),
                    $request->getSafe('timezone', 'sanitize_text_field'),
                    $request->getSafe('duration', 'intval'),
                    $ticket,
                    $request->get('selected_dates', []),
                    $request->getSafe('calendar_month', 'sanitize_text_field', '')
                )
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function getFluentBookingMeetings($ticket_id)
    {
        try {
            $this->ensureCanManageTickets();

            $ticket = Ticket::with('customer')->findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            return [
                'meetings' => (new FluentBookingService())->getTicketMeetings($ticket)
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * The customer's other tickets, plus extra profile widgets on request.
     *
     * @param Request $request
     * @param int $ticket_id
     * @return array
     */
    public function getTicketWidgets(Request $request, $ticket_id)
    {
        try {
            $ticket = Ticket::with('customer')->findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $perPage = max(1, absint(apply_filters('fluent_support/previous_ticket_widgets_limit', 5)));
            $page    = max(1, absint($request->get('page', 1)));
            $offset  = ($page - 1) * $perPage;

            $baseQuery = (new TicketQuery(['customer_id' => $ticket->customer_id, 'exclude_id' => $ticket_id, 'sort_by' => false]))->query();

            $total = $baseQuery->count();

            $otherTickets = (clone $baseQuery)
                ->select(['id', 'title', 'status', 'ticket_number', 'serial_number', 'created_at', 'updated_at'])
                ->latest('id')
                ->limit($perPage)
                ->offset($offset)
                ->get();

            $response = [
                'other_tickets'       => $otherTickets,
                'other_tickets_total' => $total,
                'other_tickets_more'  => ($offset + $perPage) < $total,
            ];

            if (in_array('extra_widgets', $request->get('with', []))) {
                $response['extra_widgets'] = ProfileInfoService::getProfileExtraWidgets($ticket->customer);
            }

            return $response;
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param Request $request
     * @param int $ticket_id
     * @return array
     */
    public function updateTicketProperty(Request $request, $ticket_id)
    {
        try {
            $assigner = Helper::getAgentByUserId();
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $propName = $request->getSafe('prop_name', 'sanitize_text_field');
            $propValue = $request->getSafe('prop_value', 'sanitize_text_field');

            // prop_name is assigned straight onto the model, so it must be allowlisted.
            if (!in_array($propName, $this->updatableTicketProperties(), true)) {
                throw new \Exception(esc_html__('This ticket property cannot be updated.', 'fluent-support'), 403);
            }

            $propValue = $this->sanitizeTicketProperty($ticket, $propName, $propValue);

            $updateData = [];
            if ($propName == 'agent_id') {
                (new TicketService())->assignAgent($ticket, Agent::findOrFail($propValue), $assigner);
                $ticket->load('agent');
                $updateData['agent'] = $ticket->agent;
                $updateData['assigner'] = $assigner;
            } else {
                (new TicketService())->change($ticket, [$propName => $propValue], $assigner);
                if ($propName == 'product_id') {
                    $ticket->load('product');
                    $updateData['product'] = $ticket->product;
                }
            }

            $message = sprintf(
                /* translators: %s: The name of the property that was updated */
                __('%s has been updated', 'fluent-support'),
                esc_html(str_replace('_', ' ', ucwords((string) $propName)))
            );

            return [
                'message'     => $message,
                'update_data' => $updateData
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * Replaces the ticket's opening message. Site admins only (AgentTicketPolicy),
     * to remove sensitive data the customer sent.
     *
     * @param Request $request
     * @param int $ticket_id
     * @return array
     */
    public function updateTicketContent(Request $request, $ticket_id)
    {
        try {
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            // Input is already unslashed. Unslashing again strips real backslashes.
            $content = wp_kses_post(Helper::decodeNonMarkupEntities((string) $request->get('content')));

            if (!trim(wp_strip_all_tags($content)) && stripos($content, '<img') === false) {
                throw new \Exception(esc_html__('The message cannot be empty.', 'fluent-support'), 422);
            }

            (new TicketService())->updateContent($ticket, $content, Helper::getAgentByUserId());

            return [
                'message'  => __('The opening message has been updated', 'fluent-support'),
                'response' => ['content' => $ticket->content],
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * The only ticket columns that may be changed through updateTicketProperty.
     * This mirrors exactly what the admin UI edits here (agent, title, product,
     * status and the two priority fields). Ownership, audit, public-identifier
     * and other sensitive columns are intentionally absent and must go through
     * their dedicated workflows.
     *
     * mailbox_id is deliberately NOT listed: moving a ticket between mailboxes
     * has to fire fluent_support/before_move_tickets and
     * fluent_support/tickets_moved, which only MailBoxService::moveTickets()
     * does. Assigning the column here would move the ticket silently, so the
     * mailbox switcher posts to mailboxes/{id}/move_tickets instead.
     *
     * @return array
     */
    protected function updatableTicketProperties()
    {
        return [
            'agent_id',
            'title',
            'product_id',
            'status',
            'priority',
            'client_priority',
        ];
    }

    /**
     * Validate a property update against its allowed values and the
     * capability the UI requires for it.
     *
     * @param Ticket $ticket
     * @param string $propName  Already confirmed to be in the allowlist.
     * @param string $propValue Raw (text-sanitized) value from the request.
     * @return mixed Normalized value ready to assign to the model.
     * @throws \Exception When the value is invalid or the caller lacks permission.
     */
    protected function sanitizeTicketProperty(Ticket $ticket, $propName, $propValue)
    {
        switch ($propName) {
            case 'status':
                // The status dropdown in ViewTicket.vue submits the group key from
                // changeable_ticket_statuses and hides empty groups. Match that.
                $allowedStatuses = [];
                foreach (Helper::changeableTicketStatuses() as $statusKey => $statusGroup) {
                    if (!empty($statusGroup)) {
                        $allowedStatuses[] = $statusKey;
                    }
                }

                if (!in_array($propValue, $allowedStatuses, true)) {
                    throw new \Exception(esc_html__('Invalid ticket status.', 'fluent-support'), 422);
                }

                // The ticket view closes and reopens with its own buttons.
                if ($propValue === 'closed' || $ticket->status === 'closed') {
                    throw new \Exception(esc_html__('Closing or reopening a ticket must use the dedicated close and re-open actions.', 'fluent-support'), 422);
                }

                return $propValue;

            case 'product_id':
                // The sidebar has no "no product" choice.
                if (!(int) $propValue) {
                    throw new \Exception(esc_html__('Invalid product.', 'fluent-support'), 422);
                }
                return (int) $propValue;

            case 'agent_id':
                if (!PermissionManager::currentUserCan('fst_assign_agents')) {
                    throw new \Exception(esc_html__('Permission denied to assign agent', 'fluent-support'), 403);
                }

                $agent = Agent::findOrFail((int) $propValue);
                $restrictedBoxes = (new AgentTicketAccess())->getRestrictedMailboxIds($agent);

                if (in_array((int) $ticket->mailbox_id, $restrictedBoxes, true)) {
                    throw new \Exception(esc_html__('Agent is restricted for this mailbox ticket', 'fluent-support'), 403);
                }
                return (int) $propValue;

            case 'title':
            case 'priority':
            case 'client_priority':
                // Checked by TicketService::change().
                return $propValue;
        }

        // Unreachable after the allowlist check. Fail closed anyway.
        throw new \Exception(esc_html__('This ticket property cannot be updated.', 'fluent-support'), 403);
    }

    /**
     * @param Request $request
     * @param int $ticket_id
     * @return array
     */
    public function closeTicket(Request $request, $ticket_id)
    {
        try {
            $agent = Helper::getAgentByUserId();
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $closeSilently = $request->getSafe('close_ticket_silently', 'sanitize_text_field') === 'yes' ? 'yes' : 'no';

            $ticket = (new TicketService())->close($ticket, $agent, '', $closeSilently);
            TicketFormatter::closedBy($ticket);

            return [
                'message' => __('Ticket has been closed', 'fluent-support'),
                'ticket'  => $ticket
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param int $ticket_id
     * @return array
     */
    public function reOpenTicket($ticket_id)
    {
        try {
            $agent = Helper::getAgentByUserId();
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            return [
                'message' => __('Ticket has been opened again', 'fluent-support'),
                'ticket'  => (new TicketService())->reopen($ticket, $agent)
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param Request $request
     * @return array|string[]|void
     * @throws \Exception
     */
    public function doBulkActions(Request $request)
    {
        try {
            $action = $request->getSafe('bulk_action', 'sanitize_text_field');
            $ticketIds = array_map('intval', (array) $request->get('ticket_ids', null, []));

            if (count($ticketIds) > self::MAX_BULK_TICKETS) {
                throw new \Exception(sprintf(
                    /* translators: %d is the most tickets one bulk action can change */
                    esc_html__('You can change at most %d tickets at a time.', 'fluent-support'),
                    (int) self::MAX_BULK_TICKETS
                ), 422);
            }

            $agent = Helper::getAgentByUserId();
            $query = Ticket::whereIn('id', $ticketIds);

            // Same access rules as ensureCanAccessTicket() on single-ticket routes.
            (new AgentTicketAccess())->applyAccessScope($query, $agent);

            if ($action == 'close_tickets') {
                $tickets = $query->get();
                $tickets->each(function ($ticket) use ($agent) {
                    (new TicketService())->close($ticket, $agent);
                });

                return [
                    'message' => sprintf(
                        /* translators: %d represents the number of closed tickets. */
                        __('%d tickets have been closed.', 'fluent-support'),
                        count($tickets)
                    )
                ];
            } else if ($action == 'delete_tickets') {
                $tickets = $query->get();
                $ticketService = new TicketService();

                foreach ($tickets as $ticket) {
                    $ticketService->deleteTicket($ticket, $agent);
                }

                return [
                    'message' => sprintf(
                        /* translators: %d is the number of tickets that were deleted */
                        __('%d tickets have been deleted', 'fluent-support'),
                        count($tickets)
                    )
                ];
            } else if ($action == 'assign_agent') {
                if (!$request->has('agent_id')) {
                    throw new \Exception(esc_html__('agent_id param is required', 'fluent-support'));
                }

                $assignAgent = Agent::findOrFail($request->getSafe('agent_id', 'intval'));

                $query->where(function ($q) use ($assignAgent) {
                    $q->where('agent_id', '!=', $assignAgent->id)
                        ->orWhereNull('agent_id');
                });

                $tickets = $query->get();
                $assignedCount = 0;
                $skippedCount = 0;

                $restrictedBoxes = (new AgentTicketAccess())->getRestrictedMailboxIds($assignAgent);

                $tickets->each(function ($ticket) use ($assignAgent, $agent, $restrictedBoxes, &$assignedCount, &$skippedCount) {
                    if (!empty($ticket->mailbox_id) && in_array((int) $ticket->mailbox_id, $restrictedBoxes, true)) {
                        $skippedCount++;
                        return;
                    }

                    (new TicketService())->assignAgent($ticket, $assignAgent, $agent);
                    $assignedCount++;
                });

                $assignedMessage = sprintf(
                    /* translators: %1$d is the number of tickets assigned, %2$s is the agent's name. */
                    __('%1$d tickets have been assigned to %2$s.', 'fluent-support'),
                    $assignedCount,
                    $assignAgent->full_name
                );

                $skippedMessage = $skippedCount > 0
                    ? sprintf(
                        /* translators: %1$d is the number of skipped tickets due to mailbox restrictions. */
                        __('%1$d tickets were skipped due to mailbox restrictions or already being assigned.', 'fluent-support'),
                        $skippedCount
                    )
                    : '';

                return [
                    'message' => trim($assignedMessage . ' ' . $skippedMessage)
                ];
            } else if ($action == 'assign_agent_group') {
                if (!$request->has('agent_group_id')) {
                    throw new \Exception(esc_html__('agent_group_id param is required', 'fluent-support'));
                }

                $groupId = $request->getSafe('agent_group_id', 'intval');
                $group = AgentGroup::findOrFail($groupId);

                if ($group->agents()->count() === 0) {
                    throw new \Exception(esc_html__('No agents found in this group', 'fluent-support'));
                }

                $tickets = $query->get();
                $assignedCount = 0;
                $skippedCount = 0;
                $currentCounts = [];

                foreach ($tickets as $ticket) {
                    $selectedAgent = $group->getLeastLoadedAgent(
                        $ticket->mailbox_id, $currentCounts
                    );

                    if (!$selectedAgent) {
                        $skippedCount++;
                        continue;
                    }

                    (new TicketService())->assignAgent($ticket, $selectedAgent, $agent, true);
                    $assignedCount++;
                    $currentCounts[$selectedAgent->id] = ($currentCounts[$selectedAgent->id] ?? 0) + 1;
                }

                return [
                    'message' => sprintf(
                        /* translators: %1$d is tickets assigned, %2$d is tickets skipped. */
                        __('%1$d tickets assigned via agent group. %2$d skipped.', 'fluent-support'),
                        $assignedCount,
                        $skippedCount
                    )
                ];
            } else if ($action == 'assign_tags') {
                $tagIds = $request->get('tag_ids', null);
                if (!is_array($tagIds)) {
                    $tagIds = [];
                }
                $tags = array_filter(array_map('absint', $tagIds));

                $query->get()->each(function ($ticket) use ($tags) {
                    (new TicketService())->addTags($ticket, $tags);
                });

                return [
                    'message' => __('Selected tags has been added to tickets', 'fluent-support')
                ];
            } else if ($action == 'remove_tags') {
                $tagIds = $request->get('tag_ids', null);
                $tags = array_filter(array_map('absint', is_array($tagIds) ? $tagIds : []));

                if (!$tags) {
                    throw new \Exception(esc_html__('Please select tags to remove', 'fluent-support'), 422);
                }

                $query->get()->each(function ($ticket) use ($tags) {
                    (new TicketService())->removeTags($ticket, $tags);
                });

                return [
                    'message' => __('Selected tags have been removed from the tickets', 'fluent-support')
                ];
            } else if ($action == 'change_priority') {
                $priority = $request->getSafe('priority', 'sanitize_text_field');

                if (!array_key_exists($priority, Helper::adminTicketPriorities())) {
                    throw new \Exception(esc_html__('Invalid ticket priority.', 'fluent-support'), 422);
                }

                return [
                    'message' => sprintf(
                        /* translators: %d is the number of tickets whose priority changed */
                        __('Priority changed on %d tickets.', 'fluent-support'),
                        $this->bulkSetProperty($query, 'priority', $priority)
                    )
                ];
            } else if ($action == 'change_product') {
                $productId = $request->getSafe('product_id', 'intval');

                if (!$productId || !Product::where('id', $productId)->exists()) {
                    throw new \Exception(esc_html__('Invalid product.', 'fluent-support'), 422);
                }

                return [
                    'message' => sprintf(
                        /* translators: %d is the number of tickets whose product changed */
                        __('Product changed on %d tickets.', 'fluent-support'),
                        $this->bulkSetProperty($query, 'product_id', $productId)
                    )
                ];
            } else if ($action == 'reopen_tickets') {
                // Only closed tickets reopen; the rest are left as they are.
                $tickets = $query->where('status', 'closed')->get();
                $ticketService = new TicketService();

                foreach ($tickets as $ticket) {
                    $ticketService->reopen($ticket, $agent);
                }

                return [
                    'message' => sprintf(
                        /* translators: %d is the number of tickets reopened */
                        __('%d tickets have been reopened.', 'fluent-support'),
                        count($tickets)
                    )
                ];
            }

            throw new \Exception(esc_html__('Sorry no action found as available', 'fluent-support'));
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * Set one column on every ticket the bulk query matches, skipping tickets
     * that already have the value.
     *
     * @param \FluentSupport\Framework\Database\Orm\Builder $query
     * @param string $column
     * @param mixed $value
     * @return int Number of tickets changed.
     */
    protected function bulkSetProperty($query, $column, $value)
    {
        $changed = 0;
        $tickets = new TicketService();
        $agent = Helper::getAgentByUserId();

        /** @var Ticket $ticket */
        foreach ($query->get() as $ticket) {
            if ($tickets->change($ticket, [$column => $value], $agent)) {
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * @param int $ticket_id
     * @return array
     */
    public function deleteTicket($ticket_id)
    {
        try {
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            (new TicketService())->deleteTicket($ticket);

            return [
                'message' => __('Ticket has been deleted successfully', 'fluent-support')
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * Agents currently viewing the ticket.
     *
     * @param Request $request
     * @param int $ticket_id
     * @return array
     */
    public function getLiveActivity(Request $request, $ticket_id)
    {
        try {
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $agent = Helper::getAgentByUserId();

            return [
                'live_activity' => TicketHelper::getActivity($ticket_id, $agent->id)
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * Remove the current agent from the ticket's live activity.
     *
     * @param Request $request
     * @param int $ticket_id
     * @return array
     */
    public function removeLiveActivity(Request $request, $ticket_id)
    {
        try {
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            $agent = Helper::getAgentByUserId();

            return [
                'result'   => TicketHelper::removeFromActivities($ticket_id, $agent->id),
                'agent_id' => $agent->id
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param Request $request
     * @param int $ticket_id
     * @return array
     */
    public function addTag(Request $request, $ticket_id)
    {
        try {
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            (new TicketService())->addTags($ticket, $request->getSafe('tag_id', 'intval'));

            return [
                'message' => __('Tag has been added to this ticket', 'fluent-support'),
                'tags'    => $ticket->tags
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * Remove one tag from the ticket.
     *
     * @param int $ticket_id
     * @param int $tag_id
     * @return array
     */
    public function detachTag($ticket_id, $tag_id)
    {
        try {
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            (new TicketService())->removeTags($ticket, $tag_id);

            return [
                'message' => __('Tag has been removed from this ticket', 'fluent-support'),
                'tags'    => $ticket->tags
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param Request $request
     * @param int $ticket_id
     * @return array
     */
    public function changeTicketCustomer(Request $request, $ticket_id)
    {
        $ticketId = (int) $ticket_id;
        $newCustomerId = $request->getSafe('customer', 'intval');

        if (!$newCustomerId) {
            return $this->sendError(__('Invalid customer selected.', 'fluent-support'));
        }

        // The ticket would expose the new customer's profile data, so this
        // needs the same capability as the customer routes.
        if (!PermissionManager::currentUserCan('fst_sensitive_data')) {
            return $this->sendError(__('You do not have permission to change the ticket customer.', 'fluent-support'));
        }

        try {
            $ticket = Ticket::findOrFail($ticketId);

            $this->ensureCanAccessTicket($ticket);

            if ($ticket->customer_id == $newCustomerId) {
                return $this->sendError(__('Customer already assigned to this ticket.', 'fluent-support'));
            }

            (new TicketService())->change($ticket, ['customer_id' => $newCustomerId], Helper::getAgentByUserId());

            return ['message' => __('Customer has been updated', 'fluent-support')];

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param Request $request
     * @param int $ticket_id
     * @return array|array[]
     */
    public function getTicketCustomData(Request $request, $ticket_id)
    {
        if (!defined('FLUENTSUPPORTPRO')) {
            return [
                'custom_data'     => [],
                'rendered_fields' => []
            ];
        }

        try {
            $ticket = Ticket::findOrFail($ticket_id);

            $this->ensureCanAccessTicket($ticket);

            return [
                'custom_data'     => (object)$ticket->customData(),
                'rendered_fields' => apply_filters('fluent_support/custom_fields_rendered', [], $ticket->customer, 'admin')
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param Request $request
     * @param FluentCRMServices $fluentCRMServices
     * @return array
     */
    public function syncFluentCrmTags(Request $request, FluentCRMServices $fluentCRMServices)
    {
        $data = [
            'contact_id' => $request->getSafe('contact_id', 'intval'),
            'tags'       => $request->get('tags', null)
        ];

        if (is_array($data['tags'])) {
            $data['tags'] = array_map('intval', $data['tags']);
        }

        try {
            return $fluentCRMServices->syncCrmTags($data);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    /**
     * @param Request $request
     * @param FluentCRMServices $fluentCRMServices
     * @return array
     */

    public function syncFluentCrmLists(Request $request, FluentCRMServices $fluentCRMServices)
    {
        $data = [
            'contact_id' => $request->getSafe('contact_id', 'intval'),
            'lists'      => $request->get('lists', null, [])
        ];

        if (is_array($data['lists'])) {
            $data['lists'] = array_map('intval', $data['lists']);
        }

        try {
            return $fluentCRMServices->syncCrmLists($data);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function fetchLabelSearch()
    {
        try {
            $agent_id = get_current_user_id();
            return TicketHelper::getLabelSearch($agent_id);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function storeOrUpdateLabelSearch(Request $request)
    {
        try {
            $agent_id = get_current_user_id();
            $searchData = $request->get('query', null, []);
            if (is_array($searchData)) {
                $searchData = map_deep($searchData, 'sanitize_text_field');
            }
            $filterType = Arr::get($searchData, 'filter_type', '');
            if ($filterType == 'advanced') {
                return TicketHelper::saveSearchLabel($agent_id, $searchData, $filterType);
            }

            return [
                'message' => __('Invalid filter type.', 'fluent-support'),
            ];

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }

    public function deleteLabelSearch(Request $request, $search_id)
    {
        try {
            $agent_id = get_current_user_id();
            return TicketHelper::deleteSavedSearch($search_id);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e)
            ]);
        }
    }
}
