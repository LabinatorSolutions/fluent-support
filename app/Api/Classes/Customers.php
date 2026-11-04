<?php

namespace FluentSupport\App\Api\Classes;

use FluentSupport\App\Modules\Auth\AuthService;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Tickets\TicketService;

/**
 * Customers PHP API.
 *
 * Example Usage: $customersApi = FluentSupportApi('customers');
 */
class Customers
{
    private $instance = null;

    private $allowedInstanceMethods = [
        'all',
        'get',
        'find',
        'first',
        'paginate'
    ];

    public function __construct(Customer $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Paginated list of customers.
     *
     * @return object
     */

    public function getCustomers()
    {
        return Customer::paginate();
    }

    /**
     * @param int $customerId
     * @return object|boolean
     */

    public function getCustomer(int $customerId)
    {
        if (is_numeric($customerId)) {
            return Customer::findOrFail($customerId);
        }
        return false;
    }

    /**
     * @param array $data
     * @param int $customer_id
     * @return object|boolean
     */

    public function updateCustomer(array $data, int $customer_id)
    {
        if (!$customer_id) {
            return false;
        }
        if ($customer = Customer::where('id', $customer_id)->first()) {
            return $customer->update($data);
        }

        return false;
    }

    /**
     * Create a customer, optionally creating the WP user first.
     *
     * @param array $data
     * @param bool $createWpUser
     * @return object|boolean
     */

    public function createCustomerWithOrWithoutWpUser(array $data, $createWpUser = false)
    {
        if (!$createWpUser) {
            $isExist = Customer::where('email', $data['email'])->first();
            if (!$data['email'] || !is_email($data['email']) || $isExist) {
                return false;
            }
            return Customer::create($data);
        }

        if (!username_exists($data['username'])) {
            $createdUser = AuthService::createUser($data);
            if ($createdUser) {
                $data['user_id'] = $createdUser;
                return Customer::create($data);
            }
        }

        return false;
    }

    /**
     * @param int $id
     * @param bool $withAssociatedData Also delete the customer's tickets and attachments.
     * @return void
     */
    public function deleteCustomer($id, $withAssociatedData = false)
    {
        if (!$id) {
            return;
        }

        $customer = Customer::findOrFail($id);
        if ($withAssociatedData) {
            $ticketService = new TicketService();
            $tickets = Ticket::where('customer_id', $id)->get();
            foreach ($tickets as $ticket) {
                $ticketService->deleteTicket($ticket);
            }
        }
        $customer->delete();
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function __call($method, $params)
    {
        if (in_array($method, $this->allowedInstanceMethods)) {
            return call_user_func_array([$this->instance, $method], $params);
        }

        throw new \Exception(sprintf('Method %s does not exist.', esc_html($method)));
    }
}
