<?php

namespace FluentSupport\App\Http\Controllers;

use Exception;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\CustomerPortalService;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Request\Request;

/**
 * CustomerPortalController class for REST API
 * This class is responsible for getting data for all request related to customer and customer portal
 * @package FluentSupport\App\Http\Controllers
 *
 * @version 1.0.0
 */

class CustomerPortalController extends Controller
{
    /**
     * getTickets will generate ticket information with customer and agents by customer
     * @param Request $request
     * @param CustomerPortalService $customerPortalService
     * @return array
     * @throws Exception
     */
    public function getTickets(Request $request, CustomerPortalService $customerPortalService)
    {
        try {
            $customer = $customerPortalService->resolveCustomer($request);
            return [
                'tickets' => $customerPortalService->getTickets($customer,  $request->get('filter_type', ''))
            ];
        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage(),
                'error_type' => $e->getCode()
            ]);
        }
    }

    /**
     * createTicket method will create ticket submitted by customers
     * @param Request $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function createTicket(Request $request, CustomerPortalService $customerPortalService)
    {
        $this->validate($request->all(), [
            'title'   => 'required',
            'content' => 'required'
        ]);

        try {
            $customer = $customerPortalService->resolveCustomer($request, true);
            return [
                'message' => __('Ticket has been created successfully', 'fluent-support'),
                'ticket'  => $customerPortalService->createTicket($customer, $request->all(), $this->resolveMailboxId($request))
            ];
        } catch (Exception $e) {
            return $this->sendError([
                'message' => __($e->getMessage(), 'fluent-support'),
                'error_type' => $e->getCode()
            ]);
        }
    }

    /**
     * getTicket method will get the ticket information with customer and agent as well as response in a ticket by ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function getTicket(Request $request, CustomerPortalService $customerPortalService, $ticketId)
    {
        try {
            return $customerPortalService->getTicket( $request, $ticketId );
        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage(),
                'error_type' => $e->getCode()
            ]);
        }
    }

    /**
     * createResponse method will create response by customer in a ticket by ticket id
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

        $data['content'] = wp_unslash(wp_kses_post($data['content']));


        $ticket = Ticket::with(['customer'])->findOrFail($ticketId);

        if ($request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $customer = $ticket->customer;
        } else {
            $customer = $this->resolveCustomer($request);
        }

        if (!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        if($customer->status == 'inactive') {
            return $this->sendError([
                'message'    => __('Sorry, You do not have access to customer portal', 'fluent-support'),
                'error_type' => 'inactive_customer'
            ]);
        }


        if ($ticket->privacy == 'private' && $customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not reply to this ticket', 'fluent-support')
            ]);
        }

        $data['conversation_type'] = 'response';
        $responseData = (new ResponseService())->createResponse($data, $customer, $ticket);

        return [
            'message'  => __('Reply has been added', 'fluent-support'),
            'response' => $responseData['response'],
            'ticket'   => $responseData['ticket']
        ];
    }

    /**
     * closeTicket method will close a ticket by customer using ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function closeTicket(Request $request, $ticketId)
    {
        $ticket = Ticket::with(['customer'])->findOrFail($ticketId);

        if ($request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $customer = $ticket->customer;
        } else {
            $customer = $this->resolveCustomer($request);
        }

        if (!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        if($customer->status == 'inactive') {
            return $this->sendError([
                'message'    => __('Sorry, You do not have access to customer portal', 'fluent-support'),
                'error_type' => 'inactive_customer'
            ]);
        }


        if ($customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not close this ticket', 'fluent-support')
            ]);
        }

        return [
            'message' => __('Ticket has been closed', 'fluent_support'),
            'ticket'  => (new TicketService())->close($ticket, $customer)
        ];
    }

    /**
     * closeTicket method will re-open a ticket by customer using ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function reOpenTicket(Request $request, $ticketId)
    {
        $ticket = Ticket::with(['customer'])->findOrFail($ticketId);

        if ($request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $customer = $ticket->customer;
        } else {
            $customer = $this->resolveCustomer($request);
        }

        if($customer->status == 'inactive') {
            return $this->sendError([
                'message'    => __('Sorry, You do not have access to customer portal', 'fluent-support'),
                'error_type' => 'inactive_customer'
            ]);
        }


        if (!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        if ($customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not close this ticket', 'fluent-support')
            ]);
        }


        return [
            'message' => __('Ticket has been opened again', 'fluent_support'),
            'ticket'  => (new TicketService())->reopen($ticket, $customer)
        ];


    }

    /**
     * resolveCustomer method will create and return or only return existing customer
     * This method will get customer id or customer info or option to force create as parameter.
     * @param $request
     * @param false $forceCreate
     * @return false|Customer
     */
    private function resolveCustomer($request, $forceCreate = false)
    {
        $onBehalf = $request->get('on_behalf');

        if (!$onBehalf) {
            $user = get_user_by('ID', get_current_user_id());
            if (!$user) {
                return false;
            }
            $onBehalf = [
                'user_id'         => $user->ID,
                'email'           => $user->user_email,
                'last_ip_address' => $request->getIp()
            ];
        }

        if ($forceCreate) {
            return Customer::maybeCreateCustomer($onBehalf);
        }

        return Customer::getCustomerFromData($onBehalf);
    }

    /**
     * getPublicOptions method will return the list of product and customer priorities
     * @return array
     */
    public function getPublicOptions()
    {
        $products = Product::select(['id', 'title'])->get();

        return [
            'support_products'           => $products,
            'customer_ticket_priorities' => Helper::customerTicketPriorities()
        ];
    }

    /**
     * getCustomFieldsRender method will return the list of custom fields
     * @return array|array[]
     */
    public function getCustomFieldsRender()
    {
        if(!defined('FLUENTSUPPORTPRO')) {
            return [
                'custom_fields_rendered' => []
            ];
        }

        return [
            'custom_fields_rendered' =>  \FluentSupportPro\App\Services\CustomFieldsService::getRenderedPublicFields()
        ];

    }

    /**
     * resolveMailboxId method will either get information of the mailbox added by user or default and return the id
     * @param $request
     * @return null
     */
    private function resolveMailboxId($request)
    {
        if ($mailboxId = $request->get('mailbox_id')) {
            $mailbox = MailBox::find($mailboxId);
            if ($mailbox) {
                return $mailbox->id;
            }
        }

        $mailbox = Helper::getDefaultMailBox();

        if ($mailbox) {
            return $mailbox->id;
        }
        return null;
    }


    /**
     * logout method will logout the customer
     * @return mixed
     */
    public function logout()
    {
        wp_logout();

        if(is_wp_error(wp_logout())) {
            return $this->sendError([
                'message' => __('Sorry! Something went wrong', 'fluent-support')
            ]);
        } else {
            return $this->sendSuccess([
                'message' => __('You have been logged out', 'fluent-support')
            ]);
        }
    }
}
