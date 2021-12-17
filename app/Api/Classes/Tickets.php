<?php

namespace FluentSupport\App\Api\Classes;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;

class Tickets
{
    private $instance = null;

    private $allowedInstanceMethods = [
        'all',
        'get',
        'find',
        'first',
        'paginate'
    ];

    public function __construct(Ticket $instance)
    {
        $this->instance = $instance;
    }

    /**
     * getTickets method will return all tickets
     *
     * @param mixed $status
     * @param mixed $customer
     * @return object
     */
    public function getTickets($query=false)
    {

        if(!$query) {
            $ticketsQuery = Ticket::with([
                'customer' => function ($query) {
                    $query->select(['first_name', 'last_name', 'id', 'email']);
                }, 'agent' => function ($query) {
                    $query->select(['first_name', 'last_name', 'id', 'email']);
                }
            ])
                ->orderBy('id', 'DESC');
            return $ticketsQuery->paginate();
        }
    }

    /**
     * getTicket method will return a specific ticket by id
     * @param int $id
     */

    public function getTicket(int $id)
    {
        if (is_numeric($id)) {
            return Ticket::findOrFail($id);
        }
        return false;
    }

    /**
     *  createTicket method will create a new ticket
     * @param array $data
     * @return object
     */

    public function createTicket(array $data)
    {
        if(!$data['customer_id'] || !Customer::findOrFail($data['customer_id'])) {
            return false;
        }

        if (!$data['title'] || !$data['content']) {
            return false;
        }

        return Ticket::create($data);

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

        throw new \Exception("Method {$method} does not exist.");
    }
}
