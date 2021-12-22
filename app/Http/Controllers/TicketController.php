<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\ProfileInfoService;
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class TicketController extends Controller
{
    public function me(Request $request)
    {
        $user = wp_get_current_user();

        $settings = [
            'user_id'     => $user->id,
            'email'       => $user->user_email,
            'person'      => Helper::getAgentByUserId($user->ID),
            'permissions' => PermissionManager::currentUserPermissions(),
            'request'     => $request->all()
        ];

        if ($request->get('with_portal_settings')) {

            $mimeHeadings = Helper::getAcceptedMimeHeadings();
            $businessSettings = (new Settings())->globalBusinessSettings();
            $maxFileSize = absint($businessSettings['max_file_size']);

            $portalSettings = [
                'support_products'           => Product::select(['id', 'title'])->get(),
                'customer_ticket_priorities' => Helper::customerTicketPriorities(),
                'has_file_upload'            => !!Helper::ticketAcceptedFileMiles(),
                'has_rich_text_editor'       => true,
                'max_file_size' => $maxFileSize,
                'mime_headings' => $mimeHeadings
            ];

            $portalSettings = apply_filters('fluent_support/customer_portal_vars', $portalSettings);

            $settings['portal_settings'] = $portalSettings;
        }


        return $settings;
    }

    public function index(Request $request)
    {
        if($request->get('filter_type')=='advanced'){
            $queryArgs = [
                'filter_type'        => 'advanced',
                'filters_groups_raw' => $this->request->get('advanced_filters'),
                'search'             => $this->request->get('search', ''),
                'sort_by'            => $this->request->get('sort_by', 'id'),
                'sort_type'          => $this->request->get('sort_type', 'DESC'),
                'has_commerce'       => $this->request->get('has_commerce'),
                'custom_fields'      => $this->request->get('custom_fields') == 'true'
            ];


            if(defined('FLUENTSUPPORTPRO')){
                $filteredTickets = (new \FluentSupportPro\App\Services\TicketQueryService($queryArgs))->paginate();
                return [
                    'tickets' => (new \FluentSupportPro\App\Services\TicketQueryService($queryArgs))->paginate()
                ];
            }
        }
        $ticketsQuery = Ticket::with([
            'customer'         => function ($query) {
                $query->select(['first_name', 'last_name', 'email', 'id', 'avatar']);
            }, 'agent'         => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            },
            'product',
            'tags',
            'preview_response' => function ($query) {
                $query->orderBy('id', 'desc');
            }
        ]);

        // apply filters by access level
        do_action_ref_array('fluent_support/tickets_query_by_permission_ref', [&$ticketsQuery, false]);

        if ($customerId = $request->get('customer_id')) {
            $ticketsQuery = $ticketsQuery->where('customer_id', $customerId);
        }

        if ($filters = $request->get('filters', [])) {
            $ticketsQuery->applyFilters($filters);
        }

        if ($search = $request->get('search')) {
            $ticketsQuery->searchBy($search);
        }

        $ticketsQuery->orderBy($request->get('order_by', 'id'), $request->get('order_type', 'ASC'));

        $tickets = $ticketsQuery->paginate();

        $perPage = $request->get('per_page');

        foreach ($tickets as $ticket) {
            if ($perPage < 15) {
                if ($ticket->status != 'closed') {
                    $ticket->live_activity = TicketHelper::getActivity($ticket->id);
                } else {
                    $ticket->live_activity = [];
                }
            }
        }

        return [
            'tickets' => $tickets
        ];
    }

    public function createTicket(Request $request)
    {
        $ticketData = $request->get('ticket', []);
        $maybeNewCustomer = $request->get('newCustomer');

        if ($ticketData['create_wp_user'] == 'yes'){
            if(!username_exists($maybeNewCustomer['username'])){
                $authController = new AuthController();
                $createdUser = $authController->createUser($maybeNewCustomer);
                $authController->maybeUpdateUser($createdUser, $maybeNewCustomer);
            }else{
                return $this->sendError(__('This username is already exist in WordPress', 'fluent-support'));
            }
        }

        if($ticketData['create_customer'] == 'yes'){
            if (!empty($maybeNewCustomer) && is_null(Customer::where('email', $maybeNewCustomer['email'])->first())){
                $createCustomer = Customer::create($maybeNewCustomer);
                if ($createCustomer){
                    $ticketData['customer_id'] = $createCustomer->id;
                }
            }
            else{
                return $this->sendError(__('Customer with this email already exist', 'fluent-support'));
            }
        }

        $this->validate($ticketData, [
            'customer_id' => 'required',
            'title'       => 'required',
            'content'     => 'required'
        ]);

        $customer = Customer::findOrFail($ticketData['customer_id']);

        if (empty($ticketData['mailbox_id'])) {
            $mailbox = Helper::getDefaultMailBox();
            $ticketData['mailbox_id'] = $mailbox->id;
        } else {
            $mailbox = MailBox::findOrFail($ticketData['mailbox_id']); // just for validation
        }

        if (!empty($ticketData['product_id'])) {
            $data['product_source'] = 'local';
        }

        $ticketData['title'] = sanitize_text_field(wp_unslash($ticketData['title']));

        $ticketData['content'] = wp_unslash(wp_kses_post($ticketData['content']));

        if (!empty($ticketData['priority'])) {
            $ticketData['priority'] = sanitize_text_field($ticketData['priority']);
        }

        $ticketData['client_priority'] = sanitize_text_field($ticketData['client_priority']);

        $ticketData = apply_filters('fluent_support/create_ticket_data', $ticketData, $customer);
        do_action('fluent_support/before_ticket_create', $ticketData, $customer);

        $createdTicket = Ticket::create($ticketData);

        if (defined('FLUENTSUPPORTPRO') && !empty($ticketData['custom_fields'])) {
            $createdTicket->syncCustomFields($ticketData['custom_fields']);
            $createdTicket->custom_fields = $createdTicket->customData();
        }

        do_action('fluent_support/ticket_created', $createdTicket, $customer);

        return [
            'message' => __('Ticket has been created successfully', 'fluent-support'),
            'ticket'  => $createdTicket
        ];

    }

    public function getTicket(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId();
        $ticketWith = $request->get('with', ['customer', 'agent', 'product', 'mailbox', 'tags', 'attachments' => function ($q) {
            $q->where('status', 'active');
        }]);
        $responseWith = $request->get('response_with', ['person', 'attachments']);

        $ticket = Ticket::with($ticketWith)
            ->findOrFail($ticketId);

        if ($ticket->customer) {
            $ticket->customer->profile_edit_url = $ticket->customer->getUserProfileEditUrl();
        }

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }


        if ($ticket->status == 'closed') {
            $ticket->load('closed_by_person');
        }

        $responses = Conversation::where('ticket_id', $ticketId)
            ->with($responseWith)
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($responses as $response) {
            $response->content = make_clickable(wpautop($response->content, false));
        }

        $ticket->content = make_clickable(wpautop($ticket->content, false));

        $ticket->live_activity = TicketHelper::getActivity($ticketId, $agent->id);

        if (defined('FLUENTSUPPORTPRO')) {
            $ticket->custom_fields = $ticket->customData('admin', true);
        }

        $data = [
            'ticket'    => $ticket,
            'responses' => $responses,
            'agent_id'  => $agent->id
        ];

        if (in_array('fluentcrm_profile', $request->get('with_data', [])) && defined('FLUENTCRM')) {
            $data['fluentcrm_profile'] = Helper::getFluentCrmContactData($ticket->customer);
        }

        return $data;

    }

    public function createResponse(Request $request, $ticketId)
    {
        $data = $request->all();

        $this->validate($data, [
            'content' => 'required'
        ]);

        $agent = Helper::getAgentByUserId(get_current_user_id());

        if (!$agent) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission. Please add yourself as support agent first', 'fluent-support')
            ]);
        }

        $ticket = Ticket::findOrFail($ticketId);

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        $responseData = (new ResponseService())->createResponse($data, $agent, $ticket);

        $responseData['response']->content = make_clickable(wpautop($responseData['response']->content, false));

        return [
            'message'     => __('Response has been added'),
            'response'    => $responseData['response'],
            'ticket'      => $responseData['ticket'],
            'update_data' => $responseData['update_data']
        ];
    }

    public function getTicketWidgets(Request $request, $ticketId)
    {
        $ticket = Ticket::with('customer')->findOrFail($ticketId);

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        $otherTickets = Ticket::where('id', '!=', $ticketId)
            ->select(['id', 'title', 'status', 'created_at'])
            ->where('customer_id', $ticket->customer_id)
            ->orderBy('id', 'DESC')
            ->limit(10)
            ->get();

        return [
            'other_tickets' => $otherTickets,
            'extra_widgets' => ProfileInfoService::getProfileExtraWidgets($ticket->customer)
        ];
    }

    public function updateTicketProperty(Request $request, $ticketId)
    {
        $assigner = Helper::getAgentByUserId(get_current_user_id());
        $ticket = Ticket::findOrFail($ticketId);
        $propName = $request->get('prop_name');
        $propValue = $request->get('prop_value');

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        $prevValue = $ticket->{$propName};
        if ($propName && $propValue && $prevValue != $propValue) {
            $ticket->{$propName} = $propValue;
            $ticket->save();
        }

        $updateData = [];

        if ($propName == 'product_id') {
            $ticket->load('product');
            $updateData['product'] = $ticket->product;
        } else if ($propName == 'agent_id') {
            $ticket->load('agent');
            $updateData['agent'] = $ticket->agent;
            $updateData['assigner'] = (new TicketService())->onAgentChange($ticket, $assigner);
            if ($prevValue != $ticket->{$propName}) {
                do_action('fluent_support/agent_assigned_to_ticket', $ticket->agent, $ticket);
            }
        }

        return [
            'message'     => __(str_replace('_', ' ', ucwords($propName)) . ' has been updated', 'fluent-support'),
            'update_data' => $updateData
        ];
    }

    public function closeTicket(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId(get_current_user_id());

        $ticket = Ticket::findOrFail($ticketId);

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        return [
            'message' => __('Ticket has been closed', 'fluent_support'),
            'ticket'  => (new TicketService())->close($ticket, $agent)
        ];
    }

    public function reOpenTicket(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId(get_current_user_id());

        $ticket = Ticket::findOrFail($ticketId);

        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        return [
            'message' => __('Ticket has been opened again', 'fluent_support'),
            'ticket'  => (new TicketService())->reopen($ticket, $agent)
        ];
    }

    public function doBulkActions(Request $request)
    {
        $ticketIds = $request->get('ticket_ids', []);
        $action = $request->get('bulk_action');
        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');
        $agent = Helper::getAgentByUserId();
        $query = Ticket::whereIn('id', $ticketIds);

        if (!$hasAllPermission) {
            $query->where('agent_id', $agent->id);
        }

        if ($action == 'close_tickets') {
            $query->where('status', '!=', 'closed');
            $tickets = $query->get();
            foreach ($tickets as $ticket) {
                (new TicketService())->close($ticket, $agent);
            }

            return [
                'message' => sprintf(__('%d tickets have been closed', 'fluent-support'), count($tickets))
            ];
        } else if ($action == 'delete_tickets') {
            $tickets = $query->get();

            foreach ($tickets as $ticket) {
                $ticket->deleteTicket();
            }

            return [
                'message' => __(count($tickets) . ' tickets have been deleted', 'fluent-support')
            ];
        } else if ($action == 'assign_agent') {
            $agentId = absint($request->get('agent_id'));
            if (!$agentId) {
                $this->sendError([
                    'message' => __('agent_id param is required', 'fluent-support')
                ]);
            }

            $agent = Agent::findOrFail($agentId);

            $query->where(function ($q) use ($agent) {
                $q->where('agent_id', '!=', $agent->id)
                    ->orWhereNull('agent_id');
            });

            $tickets = $query->get();

            foreach ($tickets as $ticket) {
                $ticket->agent_id = $agent->id;
                $ticket->save();
                do_action('fluent_support/agent_assigned_to_ticket', $agent, $ticket);
            }

            return [
                'message' => __(count($tickets) . ' tickets has been assigned to', 'fluent-support') . ' ' . $agent->full_name
            ];
        } else if ($action == 'assign_tags') {

            $tags = array_filter(array_map('absint', $request->get('tag_ids', [])));
            if (!$tags) {
                $this->sendError([
                    'message' => __('tag_ids param is required', 'fluent-support')
                ]);
            }

            $tickets = $query->get();

            foreach ($tickets as $ticket) {
                $ticket->applyTags($tags);
            }

            return [
                'message' => __('Selected tags has been added to tickets', 'fluent-support')
            ];

        }

        $this->sendError([
            'message' => __('Sorry no action found as available', 'fluent-support')
        ]);
    }

    public function doBulkReplies(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'content'    => 'required',
            'ticket_ids' => 'required|array'
        ]);

        $ticketIds = $request->get('ticket_ids');
        $ticketIds = array_filter($ticketIds, 'absint');

        $agent = Helper::getAgentByUserId();

        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');

        $query = Ticket::whereIn('id', $ticketIds)->where('status', '!=', 'closed');

        if (!$hasAllPermission) {
            $query->where('agent_id', $agent->id);
        }

        $tickets = $query->get();

        if ($tickets->isEmpty()) {
            $this->sendError([
                'message' => __('Sorry no tickets found based on your filter and bulk actions', 'fluent-support')
            ]);
        }

        $responseData = [
            'content'           => $request->get('content'),
            'conversation_type' => $request->get('conversation_type', 'response'),
            'close_ticket'      => $request->get('close_ticket', 'no')
        ];

        $attachments = $request->get('attachments', []);

        if ($attachments) {
            $attachments = Attachment::whereNull('ticket_id')
                ->orderBy('id', 'asc')
                ->whereIn('file_hash', $attachments)
                ->get();
        }


        $responseService = new ResponseService();

        foreach ($tickets as $ticket) {
            if ($attachments) {
                $responseData['attachments'] = [];
                foreach ($attachments as $attachment) {
                    $attachedFile = $attachment->replicate();
                    $attachedFile->ticket_id = $ticket->id;
                    $attachedFile->save();
                    $responseData['attachments'][] = $attachedFile->file_hash;
                }
            }

            $responseService->createResponse($responseData, $agent, $ticket);
        }


        return [
            'message' => __('Response has been added to the selected tickets', 'fluent-support')
        ];

    }

    public function deleteResponse(Request $request, $ticketId, $responseId)
    {
        $ticket = Ticket::findOrFail($ticketId);
        $response = Conversation::findOrFail($responseId);
        $agent = Helper::getAgentByUserId();

        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');

        if (!$hasAllPermission) {
            if ($ticket->agent_id != $agent->id) {
                return $this->sendError([
                    'message' => __('Sorry, You do not have permission to delete this response', 'fluent-support')
                ]);
            }
        }

        Conversation::where('id', $response->id)->delete();

        return [
            'message' => __('Selected response has been deleted', 'fluent-support')
        ];

    }

    public function updateResponse(Request $request, $ticketId, $responseId)
    {
        $data = $request->all();

        $this->validate($data, [
            'content' => 'required'
        ]);

        $ticket = Ticket::findOrFail($ticketId);
        $response = Conversation::findOrFail($responseId);
        $agent = Helper::getAgentByUserId();

        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');

        if (!$hasAllPermission) {
            if ($ticket->agent_id != $agent->id) {
                return $this->sendError([
                    'message' => __('Sorry, You do not have permission to delete this response', 'fluent-support')
                ]);
            }
        }

        $response->content = wp_unslash(wp_kses_post($data['content']));
        $response->save();

        return [
            'message'  => __('Selected response has been updated', 'fluent-support'),
            'response' => $response
        ];
    }

    public function getLiveActivity(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId();

        return [
            'live_activity' => TicketHelper::getActivity($ticketId, $agent->id)
        ];
    }

    public function removeLiveActivity(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId();

        return [
            'result'   => TicketHelper::removeFromActivities($ticketId, $agent->id),
            'agent_id' => $agent->id
        ];
    }

    public function addTag(Request $request, $ticketId)
    {
        $ticket = Ticket::findOrFail($ticketId);

        $tagId = intval($request->get('tag_id'));

        if (!$ticket->hasTag($tagId)) {
            $ticket->tags()->attach($tagId, ['source_type' => 'ticket_tag']);
        }

        return [
            'message' => __('Tag has been added to this ticket', 'fluent-support'),
            'tags'    => $ticket->tags
        ];
    }

    public function detachTag($ticketId, $tagId)
    {
        $ticket = Ticket::findOrFail($ticketId);
        $ticket->tags()->detach($tagId);

        return [
            'message' => __('Tag has been removed from this ticket', 'fluent-support'),
            'tags'    => $ticket->tags
        ];
    }

    public function changeTicketCustomer(Request $request)
    {
        $updateCustomer = Ticket::where('id', $request->get('ticket_id'))
            ->update(['customer_id' => $request->get('customer')]);
        return [
            'message'         => __('Customer has been updated', 'fluent-support'),
            'updatedCustomer' => $updateCustomer
        ];
    }

    public function getTicketCustomData(Request $request, $ticketId)
    {
        if (!defined('FLUENTSUPPORTPRO')) {
            return [
                'custom_data'     => [],
                'rendered_fields' => []
            ];
        }

        $ticket = Ticket::findOrFail($ticketId);

        return [
            'custom_data'     => (object)$ticket->customData(),
            'rendered_fields' => \FluentSupportPro\App\Services\CustomFieldsService::getRenderedPublicFields($ticket->customer)
        ];
    }

    public function syncFluentCrmTags(Request $request)
    {

        if (!defined('FLUENTCRM')) {
            return $this->sendError([
                'message' => __('FluentCRM is not installed', 'fluent-support')
            ]);
        }

        $contactId = absint($request->get('contact_id'));

        if (!$contactId) {
            return $this->sendError([
                'message' => __('Contact could not be found', 'fluent-support')
            ]);
        }

        $tagIds = array_filter($request->get('tags', []), 'absint');
        $canAddTags = \FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_manage_contacts');
        $canAddTags = apply_filters('fluent_support/can_user_add_tags_to_customer', $canAddTags);

        if (!$canAddTags) {
            return $this->sendError([
                'message' => __('Sorry you do not have permission to add contact tags', 'fluent-support')
            ]);
        }

        $contact = \FluentCrm\App\Models\Subscriber::findOrFail($contactId);

        $existingTags = $contact->tags;
        $existingTagIds = [];
        foreach ($existingTags as $tag) {
            $existingTagIds[] = $tag->id;
        }
        $newTagIds = array_diff($tagIds, $existingTagIds);
        $removedTagIds = array_diff($existingTagIds, $tagIds);

        if ($newTagIds) {
            $contact->attachTags($newTagIds);
        }

        if ($removedTagIds) {
            $contact->detachTags($removedTagIds);
        }


        return [
            'tags'    => $contact->tags,
            'message' => __('FluentCRM contact tags has been updated', 'fluent-support')
        ];

    }
}
