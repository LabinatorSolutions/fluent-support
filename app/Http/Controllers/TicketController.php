<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\ProfileInfoService;
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Request\Request;

/**
 *  TicketController class for REST API related to ticket
 * This class is responsible for getting / inserting/ modifying data for all request related to ticket
 * @package FluentSupport\App\Http\Controllers
 *
 * @version 1.0.0
 */

class TicketController extends Controller
{
    /**
     * This `me` method will return the current user profile info
     * @param Request $request
     * @param ProfileInfoService $profileInfoService
     * @return array
     */
    public function me(Request $request, ProfileInfoService $profileInfoService)
    {
        return $profileInfoService->me( $request, wp_get_current_user() );
    }

    /**
     * index method will return the list of ticket based on the selected filter
     * @param Request $request
     * @return array
     */
    public function index(Request $request, Ticket $ticket)
    {
        //Selected filter type, either simple or Advanced
        $filterType = $request->get('filter_type', 'simple');

        return $ticket->getTickets( $request, $filterType );
    }

    /**
     * createTicket method will create new ticket as well as customer or WP user
     * @param Request $request
     * @param Ticket $ticket
     * @return array
     * @throws \Exception
     */
    public function createTicket( Request $request, Ticket $ticket )
    {
        $ticketData = $request->get('ticket', []);
        $maybeNewCustomer = $request->get('newCustomer');

        if( empty($maybeNewCustomer) ){
            $this->validate($ticketData, [
                'customer_id' => 'required',
                'title'       => 'required',
                'content'     => 'required'
            ]);
        }

        try {
            return $ticket->createTicket( $ticketData, $maybeNewCustomer );
        } catch (\Exception $e) {
            return $this->sendError(__($e->getMessage(), 'fluent-support'));
        }
    }

    /**
     * getTicket method will return ticket information by ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function getTicket(Request $request, $ticketId)
    {
        //Get logged in agent information
        $agent = Helper::getAgentByUserId();

        $ticketWith = $request->get('with', ['customer', 'agent', 'product', 'mailbox', 'tags', 'attachments' => function ($q) {
            $q->whereIn('status', ['active', 'inline']);
        }]);
        $responseWith = $request->get('response_with', ['person', 'attachments']);

        //Get ticket by id
        $ticket = Ticket::with($ticketWith)
            ->findOrFail($ticketId);

        //If ticket has customer
        if ($ticket->customer) {
            //Get and set customer profile url
            $ticket->customer->profile_edit_url = $ticket->customer->getUserProfileEditUrl();
        }

        //If user do not have permission in this ticket
        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }


        if ($ticket->status == 'closed') {
            $ticket->load('closed_by_person');
        }

        //Get ticket responses
        $responses = Conversation::where('ticket_id', $ticketId)
            ->with($responseWith)
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($responses as $response) {
            $response->content = make_clickable(wpautop($response->content, false));
        }

        $ticket->content = make_clickable(wpautop($ticket->content, false));

        //Get last activity by agent
        $ticket->live_activity = TicketHelper::getActivity($ticketId, $agent->id);

        if (defined('FLUENTSUPPORTPRO')) {
            $ticket->custom_fields = $ticket->customData('admin', true);
        }

        $data = [
            'ticket'    => $ticket,
            'responses' => $responses,
            'agent_id'  => $agent->id
        ];

        if(defined('FLUENTSUPPORTPRO') && $ticket->watchers){
            $data['watchers'] = TicketHelper::getWatchers($ticket->watchers);
        }

        //Is request come with fluentcrm_profile, get fluent crm contack information
        if (in_array('fluentcrm_profile', $request->get('with_data', [])) && defined('FLUENTCRM')) {
            $data['fluentcrm_profile'] = Helper::getFluentCrmContactData($ticket->customer);
        }

        return $data;

    }

    /**
     * createResponse method will create response by agent for the ticket
     * @param Request $request
     * @param $ticketId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function createResponse(Request $request, $ticketId)
    {
        $data = $request->all();

        $this->validate($data, [
            'content' => 'required'
        ]);

        //Get logged-in agent information
        $agent = Helper::getAgentByUserId(get_current_user_id());

        if (!$agent) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission. Please add yourself as support agent first', 'fluent-support')
            ]);
        }

        $ticket = Ticket::findOrFail($ticketId);

        //If the agent has permission to view this ticket
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

    /**
     * getTicketWidgets method generate additional information for a ticket by  customer
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function getTicketWidgets(Request $request, $ticketId)
    {
        //Get ticket with customer by ticket
        $ticket = Ticket::with('customer')->findOrFail($ticketId);

        //If the logged-in user has permission
        if (!PermissionManager::hasTicketPermission($ticket)) {
            return $this->sendError([
                'message' => __('Sorry, You do not have permission to this ticket', 'fluent-support')
            ]);
        }

        //Get last 10 tickets of this customer except this
        /*
         * Filter ticket limit to show ticket in view ticket page sidebar
         * @since 1.5.6
         * @param int $limit
         */
        $limit = apply_filters('fluent_support/previous_ticket_widgets_limit', 10);

        $otherTickets = Ticket::where('id', '!=', $ticketId)
            ->select(['id', 'title', 'status', 'created_at'])
            ->where('customer_id', $ticket->customer_id)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();

        return [
            'other_tickets' => $otherTickets,
            'extra_widgets' => ProfileInfoService::getProfileExtraWidgets($ticket->customer)
        ];
    }

    /**
     * updateTicketProperty method will update ticket property
     * @param Request $request
     * @param $ticketId
     * @return array
     */
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
                do_action('fluent_support/agent_assigned_to_ticket', $ticket->agent, $ticket, $assigner);
            }
        }

        return [
            'message'     => __(str_replace('_', ' ', ucwords($propName)) . ' has been updated', 'fluent-support'),
            'update_data' => $updateData
        ];
    }

    /**
     * closeTicket method close the ticket by id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
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

    /**
     * reOpenTicket method will reopen a closed ticket
     * @param Request $request
     * @param $ticketId
     * @return array
     */
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

    /**
     * doBulkActions method is responsible for bulk action
     * This function will get ticket ids and action as parameter and perform action based on the selection
     * @param Request $request
     * @return array|string[]|void
     */
    public function doBulkActions(Request $request)
    {
        //Get all ticket ids
        $ticketIds = $request->get('ticket_ids', []);
        $action = $request->get('bulk_action');//get action
        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');
        $agent = Helper::getAgentByUserId();
        $query = Ticket::whereIn('id', $ticketIds);

        //If agent do not have permission to manage other tickets
        if (!$hasAllPermission) {
            //Filter ticket by agent_id
            $query->where('agent_id', $agent->id);
        }

        //If bulk action is close ticket
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
            //If bulk action is delete ticket
            $tickets = $query->get();

            foreach ($tickets as $ticket) {
                $ticket->deleteTicket();
            }

            return [
                'message' => __(count($tickets) . ' tickets have been deleted', 'fluent-support')
            ];
        } else if ($action == 'assign_agent') {
            //If action is assign agent
            $agentId = absint($request->get('agent_id'));
            if (!$agentId) {
                $this->sendError([
                    'message' => __('agent_id param is required', 'fluent-support')
                ]);
            }

            $agent = Agent::findOrFail($agentId);

            //Filter ticket where not assign same agent or none
            $query->where(function ($q) use ($agent) {
                $q->where('agent_id', '!=', $agent->id)
                    ->orWhereNull('agent_id');
            });

            $tickets = $query->get();

            foreach ($tickets as $ticket) {
                $assigner = Helper::getCurrentAgent();
                $ticket->agent_id = $agent->id;
                $ticket->save();
                do_action('fluent_support/agent_assigned_to_ticket', $agent, $ticket, $assigner);
            }

            return [
                'message' => __(count($tickets) . ' tickets has been assigned to', 'fluent-support') . ' ' . $agent->full_name
            ];
        } else if ($action == 'assign_tags') {
            //if action is assign tags
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

    /**
     * doBulkReplies method will create response for bulk tickets
     * This function will get ticket ids, content, attachment etc and create response for tickets
     * @param Request $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function doBulkReplies(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'content'    => 'required',
            'ticket_ids' => 'required|array'
        ]);

        //get all ticket ids
        $ticketIds = $request->get('ticket_ids');
        $ticketIds = array_filter($ticketIds, 'absint');

        //Get logged in agent information
        $agent = Helper::getAgentByUserId();

        $hasAllPermission = PermissionManager::currentUserCan('fst_manage_other_tickets');

        $query = Ticket::whereIn('id', $ticketIds)->where('status', '!=', 'closed');

        //If the agent does not have permission
        if (!$hasAllPermission) {
            //Filter ticket by agent_id
            $query->where('agent_id', $agent->id);
        }

        $tickets = $query->get();

        //if not ticket found
        if ($tickets->isEmpty()) {
            $this->sendError([
                'message' => __('Sorry no tickets found based on your filter and bulk actions', 'fluent-support')
            ]);
        }

        //get response data
        $responseData = [
            'content'           => $request->get('content'),
            'conversation_type' => $request->get('conversation_type', 'response'),
            'close_ticket'      => $request->get('close_ticket', 'no')
        ];

        $attachments = $request->get('attachments', []);

        //If request with file
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

    /**
     * deleteResponse method will remove a response from ticket by ticket id and response id
     * @param Request $request
     * @param $ticketId
     * @param $responseId
     * @return array
     */
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

    /**
     * updateResponse method will update ticket response using ticket and response id
     * @param Request $request
     * @param $ticketId
     * @param $responseId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
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

    /**
     * getLiveActivity method will return the activity in a ticket by agents
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function getLiveActivity(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId();

        return [
            'live_activity' => TicketHelper::getActivity($ticketId, $agent->id)
        ];
    }

    /**
     * removeLiveActivity method will remove activities that
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function removeLiveActivity(Request $request, $ticketId)
    {
        $agent = Helper::getAgentByUserId();

        return [
            'result'   => TicketHelper::removeFromActivities($ticketId, $agent->id),
            'agent_id' => $agent->id
        ];
    }

    /**
     * addTag method will add tag in ticket by ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
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

    /**
     * detachTag method will remove all tags from tickets
     * @param $ticketId
     * @param $tagId
     * @return array
     */
    public function detachTag($ticketId, $tagId)
    {
        $ticket = Ticket::findOrFail($ticketId);
        $ticket->tags()->detach($tagId);

        return [
            'message' => __('Tag has been removed from this ticket', 'fluent-support'),
            'tags'    => $ticket->tags
        ];
    }

    /**
     * changeTicketCustomer method will update customer in a ticket
     * This method will get ticket id and customer id as parameter, it will replace existing customer id with new
     * @param Request $request
     * @return array
     */
    public function changeTicketCustomer(Request $request)
    {
        $updateCustomer = Ticket::where('id', $request->get('ticket_id'))
            ->update(['customer_id' => $request->get('customer')]);
        return [
            'message'         => __('Customer has been updated', 'fluent-support'),
            'updatedCustomer' => $updateCustomer
        ];
    }

    /**
     * getTicketCustomData method will return the custom data by ticket id
     * @param Request $request
     * @param $ticketId
     * @return array|array[]
     */
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

    /**
     * syncFluentCrmTags method will synchronize the tags with Fluent CRM by contact id
     *This function will get contact id and tags as parameter, get existing tags from crm and updated added/removed tags
     * @param Request $request
     * @return array
     */
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
