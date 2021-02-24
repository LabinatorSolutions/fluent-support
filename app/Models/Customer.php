<?php

namespace FluentSupport\App\Models;

use FluentSupport\Framework\Support\Arr;

class Customer extends Person
{
    protected static $type = 'customer';

    public static function maybeCreateCustomer($customerData)
    {
        $customer = self::getCustomerFromData($customerData);

        $user = get_user_by('email', $customerData['email']);
        if($user) {
            $customerData['first_name'] = $user->first_name;
            $customerData['last_name'] = $user->last_name;
            $customerData['user_id'] = $user->ID;
        }

        if(!$customer) {
            // we have to create customer
            $customer = self::create($customerData);
        } else {
            $customer->fill($customerData);
            $customer->save();
        }

        return $customer;
    }

    public static function getCustomerFromData($customerData)
    {
        $remoteUid = Arr::get($customerData, 'remote_uid');
        $email = $customerData['email'];

        $customer = false;
        if($remoteUid) {
            $customer = self::where('remote_uid', $remoteUid)->first();
        }

        if(!$customer) {
            if(!empty($customerData['user_id'])) {
                $customer = self::where('user_id', $customerData['user_id'])->first();
            } else {
                $customer = self::where('email', $email)->first();
            }
        }

        return $customer;
    }

    public function getTicketCounts()
    {
        return Ticket::where('customer_id', $this->id)->count();
    }

    public function getResponseCounts()
    {
        return Response::where('person_id', $this->id)->count();
    }
}
