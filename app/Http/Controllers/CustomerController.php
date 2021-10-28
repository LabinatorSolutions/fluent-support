<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Customer;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::orderBy('id', 'DESC')
            ->orderBy($request->get('order_by', 'id'), $request->get('order_type', 'ASC'))
            ->searchBy($request->get('search'))
            ->paginate();

        foreach ($customers as $customer) {
            $customer->total_tickets = $customer->getTicketCounts();
            $customer->total_responses = $customer->getResponseCounts();
            if($customer->user_id) {
                $customer->user_profile = admin_url('user-edit.php?user_id='.$customer->user_id);
            }
        }

        return [
            'customers' => $customers,
        ];
    }

    public function create(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'email' => 'required|email|unique:fs_persons'
        ]);

        $email = $data['email'];

        $data = Arr::only($data, ['first_name', 'last_name', 'email']);

        $user = get_user_by('email', $email);

        if($user) {
            $data['user_id'] = $user->ID;
            if(empty($data['first_name'])) {
                $data['first_name'] = $user->first_name;
            }
            if(empty($data['last_name'])) {
                $data['last_name'] = $user->last_name;
            }
        }

        $customer = Customer::create($data);

        return [
            'message' => __('Customer has been added', 'fluent-support'),
            'customer' => $customer
        ];
    }

    public function update(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $data = $request->all();
        $this->validate($data, [
            'email' => 'required|email',
            'first_name' => 'required'
        ]);

        if($otherCustomer = Customer::where('id', '!=', $customerId)->where('email', $data['email'])->first()) {
            return $this->sendError([
                'message' => __('Another Customer has same email address', 'fluent-support'),
                'errors' => [
                    'email' => [
                        'unique' => __('Email address has been assigned to other customer', 'fluent-support')
                    ]
                ]
            ], 423);
        }

        $updateData = Arr::only($data, ['first_name', 'last_name', 'email']);

        $user = get_user_by('email', $data['email']);

        if($user) {
            $updateData['user_id'] = $user->ID;
        }

        Customer::where('id', $customer->id)
            ->update($updateData);

        return [
            'message' => __('Customer has been updated', 'fluent-support'),
            'customer' => Customer::findOrFail($customerId)
        ];
    }

    public function delete(Request $request, $customerId)
    {

    }
}
