<?php

namespace FluentSupport\App\Models\Traits;

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
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
}
