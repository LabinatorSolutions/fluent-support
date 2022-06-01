<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

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
            'customers' => $customer->getCustomers($request->get('search'), $request->get('status')),
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
        return $customer->getCustomer($customerId, $request->get('with', []));
    }

    /**
     * Create method will create new customer
     * @param Request $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function create(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'email' => 'required|email|unique:fs_persons'
        ]);

        $email = $data['email'];

        $data = Arr::only($data, (new Customer)->getFillable());

        $user = get_user_by('email', $email);

        if ($user) {
            $data['user_id'] = $user->ID;
            if (empty($data['first_name'])) {
                $data['first_name'] = $user->first_name;
            }
            if (empty($data['last_name'])) {
                $data['last_name'] = $user->last_name;
            }
        }

        $customer = Customer::create($data);

        return [
            'message'  => __('Customer has been added', 'fluent-support'),
            'customer' => $customer
        ];
    }

    /**
     * update method will update existing customer by customer id
     * @param Request $request
     * @param $customerId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function update(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $data = $request->all();
        $this->validate($data, [
            'email'      => 'required|email',
            'first_name' => 'required'
        ]);

        if ($otherCustomer = Customer::where('id', '!=', $customerId)->where('email', $data['email'])->first()) {
            return $this->sendError([
                'message' => __('Another Customer has same email address', 'fluent-support'),
                'errors'  => [
                    'email' => [
                        'unique' => __('Email address has been assigned to other customer', 'fluent-support')
                    ]
                ]
            ], 423);
        }

        $validKeys = (new Customer)->getFillable();
        unset($validKeys['hash']);
        unset($validKeys['user_id']);

        $updateData = Arr::only($data, $validKeys);

        $user = get_user_by('email', $data['email']);

        if ($user) {
            $updateData['user_id'] = $user->ID;
        }

        Customer::where('id', $customer->id)
            ->update($updateData);

        return [
            'message'  => __('Customer has been updated', 'fluent-support'),
            'customer' => Customer::findOrFail($customerId)
        ];
    }

    /**
     * delete method will delete a customer and all ticket by that customer
     * @param Request $request
     * @param $customerId
     * @return array
     */
    public function delete(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        $tickets = Ticket::where('customer_id', $customer->id)->get();

        foreach ($tickets as $ticket) {
            $ticket->deleteTicket();
        }

        $customer->delete();

        return [
            'message' => __('Customer Deleted Successfully', 'fluent-support')
        ];
    }

    /**
     * addOrUpdateProfileImage method will update a customer avatar
     * @param Request $request
     * @return array
     */
    public function addOrUpdateProfileImage(Request $request)
    {
        $allowExtension = [
            'jpeg', 'jpe', 'jpg', 'png'
        ];

        $customer_id = $request->get('customer_id');
        $file = $request->files();

        $ext = $file['file']->getClientOriginalExtension();

        if(!in_array($ext, $allowExtension)){
            return $this->sendError([
                'message' => __('Unsupported file submitted, please select an image file', 'fluent-support')
            ]);
        }

        $customer = Customer::findOrFail($customer_id);

        $uploadedImage = FileSystem::setSubDir('customer_avatars')->put($file);

        if($avatar = $uploadedImage[0]['url']){
            $customer->avatar = $avatar;
            $customer->save();

            return[
                'message' => __('Profile picture has been updated successfully', 'fluent-support'),
                'image'   => $customer->avatar,
                'customer' => $customer
            ];
        }

        else{
            return $this->sendError([
                'message' => __('Something went wrong while updating the profile picture', 'fluent-support')
            ]);
        }
    }
}
