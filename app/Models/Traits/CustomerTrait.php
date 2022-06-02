<?php

namespace FluentSupport\App\Models\Traits;

use Exception;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;
use FluentSupport\App\Services\ProfileInfoService;

trait CustomerTrait
{

    /**
     * This getCustomers method will return all customers
     * @param string $search
     * @param string $status
     * @return object
     */
    public function getCustomers($search, $status=false)
    {
        $customersQuery = static::latest()->searchBy($search);

        if ($status && $status != 'all') {
            $customersQuery->filterByStatues([$status]);
        }

        $customers = $customersQuery->paginate();

        return $this->attachCustomersMetaData($customers);
    }

    /**
     * This getCustomer method will return a single customer
     * @param int $id
     * @param array $with
     * @return object
     */

    public function getCustomer($id, $with)
    {
        $customer = static::find($id);

        $data = [
            'customer' => $customer
        ];

        return $this->getCustomerAdditionalData($with, $customer, $data);
    }

    /**
     * This createCustomer method will create a new customer
     * @param array $data
     * @return object
     */

    public function createCustomer($data)
    {
        $data = Arr::only($data, $this->getFillable());

        $user = get_user_by('email', $data['email']);

        if ($user) {
            $data['user_id'] = $user->ID;
            if (empty($data['first_name'])) {
                $data['first_name'] = $user->first_name;
            }
            if (empty($data['last_name'])) {
                $data['last_name'] = $user->last_name;
            }
        }

        return static::create($data);
    }

    /**
     * This updateCustomer method will update a customer
     * @param int $customerId
     * @param array $data
     * @return object
     */

    public function updateCustomer($customerId, $data)
    {
        $data = $this->takeValidKeysForUpdate($data);

        $customer = static::findOrFail($customerId);

        if($this->customerExists($customerId, $data['email']))
        {
            throw new Exception('Another Customer has same email address');
        }

        $user = get_user_by('email', $data['email']);

        if ($user) {
            $data['user_id'] = $user->ID;
        }

        static::where('id', $customer->id)->update($data);

        return static::findOrFail($customerId);
    }

    /**
     * This attachCustomersMetaData method will attach meta data to customers
     * @param object $customers
     * @return object
     */
    private function attachCustomersMetaData($customers)
    {
        foreach ($customers as $customer) {
            $customer->total_tickets = $customer->getTicketCounts();
            $customer->total_responses = $customer->getResponseCounts();
            if ($customer->user_id) {
                //Get profile link, if they are WP user
                $customer->user_profile = admin_url('user-edit.php?user_id=' . $customer->user_id);
            }
        }

        return $customers;
    }

    /**
     * This getCustomerAdditionalData method will return additional data for a customer
     * @param array $with
     * @param object $customer
     * @param array $data
     * @return array
     */

    private function getCustomerAdditionalData($with, $customer, $data)
    {
        if (in_array('widgets', $with)) {
            $data['widgets'] = ProfileInfoService::getProfileExtraWidgets($customer);
        }

        if (in_array('tickets', $with)) {
            /*
             * Filter ticket limit to show ticket in customer page sidebar
             * @since 1.5.6
             * @param int $limit
             */
            $limit = apply_filters('fluent_support/customer_page_ticket_widgets_limit', 20);

            $data['tickets'] = Ticket::select(['id', 'title', 'status', 'customer_id', 'created_at'])
                ->where('customer_id', $customer->id)
                ->orderBy('id', 'DESC')
                ->limit($limit)
                ->get();
        }

        if(in_array('fluentcrm_profile', $with)) {
            $data['fluentcrm_profile'] = Helper::getFluentCrmContactData($customer);
        }

        return $data;
    }

    /**
     * This customerExists method will check if customer exists
     * @param int $customerId
     * @param string $email
     * @return mixed
     */

    private function customerExists($customerId, $email)
    {
        $customer = static::where('id','!=', $customerId)->where('email', $email)->first();
        if ($customer) {
            return $customer;
        }
        return false;
    }


    /**
     * This takeValidKeysForUpdate method will take valid keys data for update
     * @param array $data
     * @return array
     */
    private function takeValidKeysForUpdate($data)
    {
        $validKeys = $this->getFillable();
        unset($validKeys['hash']);
        unset($validKeys['user_id']);

        return Arr::only($data, $validKeys);
    }
}
