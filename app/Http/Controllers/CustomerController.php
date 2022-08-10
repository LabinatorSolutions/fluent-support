<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Models\Customer;
use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Services\AvatarUploder;

/**
 * CustomerController class for REST API
 * This class is responsible for getting data for all request related to customer
 * @package FluentSupport\App\Http\Controllers
 *
 * @version 1.0.0
 */
class CustomerController extends Controller
{
    /**
     * index method will return the list of customers
     * @param Request $request
     * @param Customer $customer
     * @return array
     */
    public function index(Request $request, Customer $customer)
    {
        return [
            'customers' => $customer->getCustomers($request->getSafe('search'), $request->getSafe('status')),
        ];
    }


    /**
     * getCustomer method will return individual customer information by customer id
     * This function will also get information about extra widgets, tickets and Fluent CRM
     * @param Request $request
     * @param Customer $customer
     * @param $customerId
     * @return array
     */
    public function getCustomer(Request $request, Customer $customer, $customerId)
    {
        return $customer->getCustomer($customerId, $request->getSafe('with'));
    }

    /**
     * Create method will create new customer
     * @param Request $request
     * @param Customer $customer
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function create(Request $request, Customer $customer)
    {
        $this->validate($request->getSafe(), [
            'email' => 'required|email|unique:fs_persons'
        ]);

        return [
            'message'  => __('Customer has been added', 'fluent-support'),
            'customer' => $customer->createCustomer($request->getSafe())
        ];
    }

    /**
     * update method will update existing customer by customer id
     * @param Request $request
     * @param Customer $customer
     * @param $customerId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function update(Request $request, Customer $customer, $customerId)
    {
        $data = $this->validate($request->getSafe(), [
            'email'      => 'required|email',
            'first_name' => 'required'
        ]);

        try {
            return [
                'message'  => __('Customer has been updated', 'fluent-support'),
                'customer' => $customer->updateCustomer($customerId, $data)
            ];
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => __($e->getMessage(), 'fluent-support'),
                'errors'  => [
                    'email' => [
                        'unique' => __('Email address has been assigned to other customer', 'fluent-support'),
                    ]
                ]
            ], 423);
        }
    }

    /**
     * delete method will delete a customer and all tickets by that customer
     * @param Request $request
     * @param Customer $customer
     * @param int $customerId
     * @return array
     */
    public function delete(Request $request, Customer $customer, $customerId)
    {
        return $customer->deleteCustomer($customerId);
    }

    /**
     * addOrUpdateProfileImage method will update a customer avatar
     * For a successful upload it's required to send file object, customer id and the user type(customer)
     * @param Request $request
     * @return array
     */
    public function addOrUpdateProfileImage(Request $request, AvatarUploder $avatarUploder)
    {
        try {
           return $avatarUploder->addOrUpdateProfileImage( $request->files(), $request->getSafe('customer_id', 'int'), 'customer' );
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => __($e->getMessage(), 'fluent-support')
            ]);
        }
    }

    /**
     * resetAvatar method will restore a customer avatar
     * For a successful upload it's required to send file object, customer id and the user type(customer)
     * @param Request $request
     * @param $id
     * @return array
     */
    public function resetAvatar(Customer $customer, $id){
        try {
            $customer->restoreAvatar($customer, $id);

            return [
                'message'  => __('Customer avatar reset to gravatar default', 'fluent-support'),
            ];
        } catch (\Exception $e) {
            return [
                'message'  => __($e->getMessage(), 'fluent-support')
            ];
        }
    }
}
