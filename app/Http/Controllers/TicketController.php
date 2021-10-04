<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Tag;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Models\TicketTag;
use FluentSupport\App\Modules\PermissionManager;
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

        return [
            'user_id' => $user->id,
            'email' => $user->user_email,
            'person' => Helper::getAgentByUserId($user->ID),
            'permissions' => PermissionManager::currentUserPermissions(),
            'request' => $request->all()
        ];
    }

    public function index(Request $request)
    {
        $ticketsQuery = Ticket::with([
            'customer' => function ($query) {
                $query->select(['first_name', 'last_name', 'email', 'id']);
            }, 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            },
            'product',
            'tags'
        ]);

        // apply filters by access level
        $permissionLevel = PermissionManager::currentUserTicketsPermissionLevel();
        if ($permissionLevel != 'all') {
            $agent = Helper::getAgentByUserId();
            if ($permissionLevel == 'own') {
                $ticketsQuery->where('agent_id', $agent->id);
            } else {
                $ticketsQuery->where(function ($q) use ($agent) {
                    $q->where('agent_id', $agent->id);
                    $q->orWhereNull('agent_id');
                });
            }
        }

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
        $this->validate($ticketData, [
            'customer_id' => 'required',
            'title' => 'required',
            'content' => 'required'
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

        $ticketData['priority'] = sanitize_text_field($ticketData['client_priority']);

        $ticketData['client_priority'] = sanitize_text_field($ticketData['client_priority']);

        $ticketData = apply_filters('fluent_support/create_ticket_data', $ticketData, $customer);
        do_action('fluent_support/before_ticket_create', $ticketData, $customer);

        $createdTicket = Ticket::create($ticketData);

        do_action('fluent_support/ticket_created', $createdTicket, $customer);

        return [
            'message' => __('Ticket has been created successfully', 'fluent-support'),
            'ticket' => $createdTicket
        ];

    }

    public function getTicket(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId();
        $ticketWith = $request->get('with', ['customer', 'agent', 'product', 'mailbox', 'tags', 'attachments']);
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

        return [
            'ticket' => $ticket,
            'responses' => $responses,
            'agent_id' => $agent->id
        ];
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
                'message' => 'Sorry, You do not have permission. Please add yourself as support agent first'
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
            'message' => __('Response has been added'),
            'response' => $responseData['response'],
            'ticket' => $responseData['ticket'],
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
            'message' => $propName . ' has been updated',
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
            'ticket' => (new TicketService())->close($ticket, $agent)
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
            'ticket' => (new TicketService())->reopen($ticket, $agent)
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
        }

        $tickets = $query->get();

        if ($action == 'close_tickets') {

            foreach ($tickets as $ticket) {
                (new TicketService())->close($ticket, $agent);
            }

            return [
                'message' => count($tickets) . ' tickets have been closed'
            ];
        }

        $this->sendError([
            'message' => 'Sorry no action found as available'
        ]);
    }

    public function doBulkReplies(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'content' => 'required',
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

        if (!count($tickets)) {
            $this->sendError([
                'message' => __('Sorry no tickets found based on your filter and bulk actions', 'fluent-support')
            ]);
        }

        $responseData = [
            'content' => $request->get('content'),
            'conversation_type' => $request->get('conversation_type', 'response'),
            'close_ticket' => $request->get('close_ticket', 'no')
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

    public function deleteBulk(Request $request)
    {
        $ticketIds = $request->get('ticket_ids', []);

        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');
        $agent = Helper::getAgentByUserId();
        $query = Ticket::whereIn('id', $ticketIds);

        if (!$hasAllPermission) {
            $query->where('agent_id', $agent->id);
        }

        $tickets = $query->get();

        foreach ($tickets as $ticket) {
            $ticket->deleteTicket();
        }

        return [
            'message' => count($tickets) . ' has been deleted successfully'
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
                    'message' => 'Sorry, You do not have permission to delete this response'
                ]);
            }
        }

        Conversation::where('id', $response->id)->delete();

        return [
            'message' => 'Selected response has been deleted'
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
                    'message' => 'Sorry, You do not have permission to delete this response'
                ]);
            }
        }

        $response->content = wp_unslash(wp_kses_post($data['content']));
        $response->save();

        return [
            'message' => 'Selected response has been updated',
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
            'result' => TicketHelper::removeFromActivities($ticketId, $agent->id),
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
            'tags' => $ticket->tags
        ];
    }

    public function detachTag($ticketId, $tagId)
    {
        $ticket = Ticket::findOrFail($ticketId);
        $ticket->tags()->detach($tagId);

        return [
            'message' => __('Tag has been removed from this ticket', 'fluent-support'),
            'tags' => $ticket->tags
        ];
    }
}
