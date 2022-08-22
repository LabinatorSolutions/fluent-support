<?php

namespace FluentSupport\App\Http\Controllers;

use Exception;
use FluentSupport\App\Http\Requests\TicketCreateCustomerPortalRequest;
use FluentSupport\App\Http\Requests\TicketResponseRequest;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Services\CustomerPortalService;
use FluentSupport\App\Services\Helper;
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
            $customer = $customerPortalService->resolveCustomer( $request );
            return [
                'tickets' => $customerPortalService->getTickets( $customer,  $request->getSafe('filter_type', '') )
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
    public function createTicket(TicketCreateCustomerPortalRequest $request, CustomerPortalService $customerPortalService)
    {
        $data = $this->validate($request->get(), [
            'title'   => 'required',
            'content' => 'required'
        ]);

        $data['title'] = sanitize_text_field($data['title']);
        $data['content'] = wp_kses_post($data['content']);

        try {
            $customer = $customerPortalService->resolveCustomer($request, true);
            return [
                'message' => __('Ticket has been created successfully', 'fluent-support'),
                'ticket'  => $customerPortalService->createTicket( $customer, $data, $request )
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
    public function createResponse(TicketResponseRequest $request, CustomerPortalService $customerPortalService, $ticketId)
    {
        try {
            return $customerPortalService->createResponse( $request, $ticketId, $request->getSafe(null, [], 'wp_kses_post') );
        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage(),
                'error_type' => $e->getCode()
            ]);
        }
    }

    /**
     * This `closeTicket` is responsible for closing ticket by ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function closeTicket(Request $request, CustomerPortalService $customerPortalService, $ticketId)
    {
        try {
            return $customerPortalService->closeTicket( $request, $ticketId );
        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage(),
                'error_type' => $e->getCode()
            ]);
        }
    }

    /**
     * closeTicket method will re-open a ticket by customer using ticket id
     * @param Request $request
     * @param $ticketId
     * @return array
     */
    public function reOpenTicket(Request $request, CustomerPortalService $customerPortalService, $ticketId)
    {
        try {
            return $customerPortalService->reOpenTicket( $request, $ticketId );
        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage(),
                'error_type' => $e->getCode()
            ]);
        }
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
     * logout method will logout the customer
     * @return mixed
     */
    public function logout()
    {
        wp_logout();

        return $this->sendSuccess([
            'message' => __('You have been logged out', 'fluent-support')
        ]);
    }
}
