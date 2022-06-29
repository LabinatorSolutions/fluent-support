<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\ProfileInfoService;
use FluentSupport\App\Services\TicketHelper;
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
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * getTicket method will return ticket information by ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function getTicket(Request $request, Ticket $ticket, $ticketId)
    {
        try {
            return $ticket->getTicket( $request, $ticketId );
        } catch (\Exception $e) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * createResponse method will create response by agent for the ticket
     * @param Request $request
     * @param Ticket $ticket
     * @param int $ticketId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function createResponse ( Request $request, Ticket $ticket, $ticketId )
    {
        $data = $request->all();

        $this->validate($data, [
            'content' => 'required'
        ]);

        try {
            return $ticket->createResponse( $data, $ticketId );
        } catch (\Exception $e) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * getTicketWidgets method generate additional information for a ticket by  customer
     * @param Ticket $ticket
     * @param $ticketId
     * @return array
     */
    public function getTicketWidgets( Ticket $ticket, $ticketId )
    {
        try {
            return $ticket->getTicketWidgets( $ticketId );
        } catch (\Exception $e) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * updateTicketProperty method will update ticket property
     * @param Request $request
     * @param Ticket $ticket
     * @param $ticketId
     * @return array
     */
    public function updateTicketProperty(Request $request, Ticket $ticket, $ticketId)
    {
        try {
            return $ticket->updateTicketProperty( $request, $ticketId );
        } catch (\Exception $e) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * closeTicket method close the ticket by id
     * @param Ticket $ticket
     * @param int $ticketId
     * @return array
     */
    public function closeTicket ( Ticket $ticket, $ticketId )
    {
        try {
            return $ticket->closeTicket( $ticketId );
        } catch (\Exception $e) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * reOpenTicket method will reopen a closed ticket
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function reOpenTicket ( Ticket $ticket, $ticketId )
    {
        try {
            return $ticket->reOpenTicket( $ticketId );
        } catch (\Exception $e) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * doBulkActions method is responsible for bulk action
     * This function will get ticket ids and action as parameter and perform action based on the selection
     * @param Request $request
     * @param Ticket $ticket
     * @return array|string[]|void
     * @throws \Exception
     */
    public function doBulkActions ( Request $request, Ticket $ticket )
    {
        $ticketIds = $request->get('ticket_ids', []);

        try {
            return $ticket->handleBulkActions( $request, $ticketIds );
        } catch ( \Exception $e ) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * doBulkReplies method will create response for bulk tickets
     * This function will get ticket ids, content, attachment etc and create response for tickets
     * @param Request $request
     * @param Conversation $conversation
     * @return array
     * @throws \Exception
     */
    public function doBulkReplies ( Request $request, Conversation $conversation )
    {
        $data = $request->all();
        $this->validate($data, [
            'content'    => 'required',
            'ticket_ids' => 'required|array'
        ]);

        try {
            return $conversation->doBulkReplies( $data );
        } catch ( \Exception $e ) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * deleteResponse method will remove a response from ticket by ticket id and response id
     * @param Request $request
     * @param Conversation $conversation
     * @param $ticketId
     * @param $responseId
     * @return array
     */
    public function deleteResponse ( Conversation $conversation, $ticketId, $responseId )
    {
        try {
            return $conversation->deleteResponse( $ticketId, $responseId );
        } catch (\Exception $e) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
    }

    /**
     * updateResponse method will update ticket response using ticket and response id
     * @param Request $request
     * @param Conversation $conversation
     * @param int $ticketId
     * @param int $responseId
     * @return array
     * @throws \Exception
     */
    public function updateResponse ( Request $request, Conversation $conversation, $ticketId, $responseId )
    {
        $data = $request->all();

        $this->validate($data, [
            'content' => 'required'
        ]);

        try {
            return $conversation->updateResponse( $data, $ticketId, $responseId );
        } catch (\Exception $e) {
            return $this->sendError( __($e->getMessage(), 'fluent-support') );
        }
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
