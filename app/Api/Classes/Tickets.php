<?php

namespace FluentSupport\App\Api\Classes;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;

/**
 * Tickets PHP API.
 *
 * Example Usage: $ticketsApi = FluentSupportApi('tickets');
 */
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
     * Paginated tickets, newest first.
     *
     * @return object
     */
    public function getTickets()
    {
        $ticketsQuery = Ticket::with([
            'customer' => function ($query) {
                $query->select(['first_name', 'last_name', 'id', 'email']);
            }, 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id', 'email']);
            }
        ])
            ->orderBy('id', 'DESC');
        $tickets = $ticketsQuery->paginate();

        if (defined('FLUENTSUPPORTPRO')) {
            foreach ($tickets as $ticket) {
                $ticket->custom_fields = $ticket->customData();
            }
        }

        return $tickets;
    }

    /**
     * @param int $id
     * @return object|false
     */

    public function getTicket(int $id)
    {
        if (is_numeric($id)) {
            $ticket = Ticket::findOrFail($id);
            if (defined('FLUENTSUPPORTPRO')) {
                $ticket->custom_fields = $ticket->customData();
            }
            return $ticket;
        }
        return false;
    }

    /**
     * @param array $data
     * @param int $agentId
     * @param int $ticketId
     * @return array|boolean
     */

    public function addResponse(array $data, int $agentId, int $ticketId)
    {
        if (!$agentId || !$ticketId || !$data['content']) {
            return false;
        }

        $agent = Agent::findOrFail($agentId);
        $ticket = Ticket::findOrFail($ticketId);

        if ($agent && $ticket) {
            return (new ResponseService())->createResponse($data, $agent, $ticket);
        }
        return false;
    }

    /**
     * @param array $data
     * @return object|false
     */

    public function createTicket(array $data)
    {
        $customerId = $data['customer_id'] ?? null;

        if (!$customerId || !($customer = Customer::find($customerId))) {
            return false;
        }

        if (empty($data['title']) || empty($data['content'])) {
            return false;
        }

        $createdTicket = (new TicketService())->storeTicket($data, $customer);

        if (defined('FLUENTSUPPORTPRO')) {
            $createdTicket->custom_fields = $createdTicket->customData();
        }

        return $createdTicket;
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

        throw new \Exception(sprintf("Method %s does not exist.", esc_html($method)));
    }
}
