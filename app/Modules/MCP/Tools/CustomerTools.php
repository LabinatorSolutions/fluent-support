<?php

namespace FluentSupport\App\Modules\MCP\Tools;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Helpers\MCPHelper;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

class CustomerTools
{
    public static function searchCustomers($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        $search = sanitize_text_field($params['search'] ?? '');

        ['page' => $page, 'per_page' => $perPage] = MCPHelper::pagination($params);

        $restricted = PermissionManager::getRestrictedMailboxIds();

        $query = Customer::select([
            'id', 'first_name', 'last_name', 'email', 'status', 'city', 'state', 'country', 'created_at',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Exclude customers who only have tickets in restricted mailboxes.
        if ($restricted) {
            $query->where(function ($q) {
                $q->whereDoesntHave('tickets')
                  ->orWhereHas('tickets', function ($tq) {
                      (new AgentTicketAccess())->applyMailboxRestrictionScope($tq);
                  });
            });
        }

        $paginated = $query->orderBy('id', 'DESC')->paginate($perPage, ['*'], 'page', $page);

        $pageItems   = $paginated->items();
        $customerIds = array_map(fn($c) => $c->id, $pageItems);

        $ticketCounts = [];
        if ($customerIds) {
            // Count only tickets this agent could open directly.
            $countQuery = (new AgentTicketAccess())
                ->applyAccessScope(Ticket::whereIn('customer_id', $customerIds), $agent);
            $rows = $countQuery
                ->selectRaw('customer_id, COUNT(*) as total_tickets, SUM(CASE WHEN status != \'closed\' THEN 1 ELSE 0 END) as open_tickets')
                ->groupBy('customer_id')
                ->get()
                ->keyBy('customer_id');

            foreach ($rows as $cid => $row) {
                $ticketCounts[$cid] = [
                    'total_tickets' => (int) $row->total_tickets,
                    'open_tickets'  => (int) $row->open_tickets,
                ];
            }
        }

        $customers = [];
        foreach ($pageItems as $customer) {
            $item                  = MCPHelper::formatCustomerForMCP($customer);
            $item['total_tickets'] = $ticketCounts[$customer->id]['total_tickets'] ?? 0;
            $item['open_tickets']  = $ticketCounts[$customer->id]['open_tickets'] ?? 0;
            $customers[] = $item;
        }

        $total   = $paginated->total();
        $summary = $search
            /* translators: %1$d is the number of customers found, %2$s is the search term */
            ? sprintf(_n('Found %1$d customer matching \'%2$s\'', 'Found %1$d customers matching \'%2$s\'', $total, 'fluent-support'), $total, $search)
            /* translators: %d is the number of customers found */
            : sprintf(_n('Found %d customer', 'Found %d customers', $total, 'fluent-support'), $total);

        return MCPHelper::envelope($summary, ['customers' => $customers], MCPHelper::pagingMeta($paginated));
    }

    public static function getCustomerTickets($params)
    {
        $customerId = (int) ($params['customer_id'] ?? 0);
        $email = sanitize_email($params['email'] ?? '');
        $name = sanitize_text_field($params['name'] ?? '');

        $customer = null;

        if ($customerId) {
            $customer = Customer::find($customerId);
        } elseif ($email) {
            $customer = Customer::where('email', $email)->first();
        } elseif ($name) {
            $customer = Customer::where(function ($q) use ($name) {
                $q->where('first_name', 'LIKE', '%' . $name . '%')
                  ->orWhere('last_name', 'LIKE', '%' . $name . '%')
                  ->orWhere('email', 'LIKE', '%' . $name . '%');
            })->first();
        }

        if (!$customer) {
            return MCPHelper::error('not_found', __('Customer not found. Provide customer_id, email, or name.', 'fluent-support'), ['next_step' => 'Use search-customers to find the customer first']);
        }

        if (!self::canSeeCustomer($customer)) {
            return MCPHelper::error('forbidden', __('You do not have access to this customer', 'fluent-support'));
        }

        ['page' => $page, 'per_page' => $perPage] = MCPHelper::pagination($params);

        $query = Ticket::with([
            'agent' => function ($q) {
                $q->select(['id', 'first_name', 'last_name', 'email']);
            },
        ])->where('customer_id', $customer->id);

        if (!empty($params['status'])) {
            $statuses = \FluentSupport\App\Services\Helper::getTkStatusesByGroupName($params['status']);
            if ($statuses) {
                $query->whereIn('status', $statuses);
            }
        }

        do_action_ref_array('fluent_support/tickets_query_by_permission_ref', [&$query]);

        (new AgentTicketAccess())->applyMailboxRestrictionScope($query);

        $paginated = $query->orderBy('id', 'DESC')->paginate($perPage, ['*'], 'page', $page);

        $tickets = [];
        foreach ($paginated->items() as $ticket) {
            $tickets[] = [
                'id'             => $ticket->id,
                'title'          => $ticket->title,
                'status'         => $ticket->status,
                'priority'       => MCPHelper::normalizePriority($ticket->priority),
                'response_count' => (int) $ticket->response_count,
                'waiting_on'     => MCPHelper::waitingOn($ticket),
                'created_at'     => MCPHelper::toIso8601($ticket->created_at),
                'agent'          => $ticket->agent ? MCPHelper::formatPersonSummary($ticket->agent) : null,
            ];
        }

        $total      = $paginated->total();
        $identifier = $customer->email ?: MCPHelper::personName($customer);
        /* translators: %1$d is the number of tickets found, %2$s is the customer email or name */
        $summary    = sprintf(_n('Found %1$d ticket for %2$s', 'Found %1$d tickets for %2$s', $total, 'fluent-support'), $total, $identifier);

        return MCPHelper::envelope(
            $summary,
            ['customer' => MCPHelper::formatCustomerForMCP($customer), 'tickets' => $tickets],
            MCPHelper::pagingMeta($paginated)
        );
    }

    /** Fields update-customer may change, with their sanitizers. */
    const UPDATABLE_FIELDS = [
        'first_name'     => 'sanitize_text_field',
        'last_name'      => 'sanitize_text_field',
        'title'          => 'sanitize_text_field',
        'note'           => 'sanitize_textarea_field',
        'address_line_1' => 'sanitize_textarea_field',
        'address_line_2' => 'sanitize_textarea_field',
        'city'           => 'sanitize_text_field',
        'state'          => 'sanitize_text_field',
        'zip'            => 'sanitize_text_field',
        'country'        => 'sanitize_text_field',
    ];

    /**
     * Change a customer's profile: name, title, note and address. Only the fields
     * passed change. Agents limited to some mailboxes can only update customers they
     * could find with search-customers.
     *
     * The email address is left to people: a message asking to send a customer's
     * email somewhere else is a common account-takeover trick, and the address also
     * decides who can open the customer's tickets through signed links.
     */
    public static function updateCustomer($params)
    {
        $agent = MCPHelper::resolveAgent();
        if (!$agent) {
            return MCPHelper::error('unauthorized', __('No agent found for current user', 'fluent-support'));
        }

        $customerId = (int) ($params['customer_id'] ?? 0);
        $customer   = $customerId ? Customer::find($customerId) : null;
        if (!$customer) {
            return MCPHelper::error('not_found', __('Customer not found', 'fluent-support'), ['fields' => ['customer_id'], 'next_step' => 'Use search-customers to find the customer ID']);
        }

        if (!self::canSeeCustomer($customer)) {
            return MCPHelper::error('forbidden', __('You do not have access to this customer', 'fluent-support'));
        }

        if (array_key_exists('email', $params) && $params['email'] !== null && $params['email'] !== '') {
            return MCPHelper::error(
                'not_allowed',
                __('A customer\'s email address cannot be changed through MCP.', 'fluent-support'),
                ['fields' => ['email'], 'next_step' => 'Ask a person on your team to change it in the Fluent Support admin, and add an internal note on the ticket with the request.']
            );
        }

        $changes = [];
        foreach (self::UPDATABLE_FIELDS as $field => $sanitize) {
            if (array_key_exists($field, $params) && $params[$field] !== null) {
                $changes[$field] = call_user_func($sanitize, (string) $params[$field]);
            }
        }

        if (!$changes) {
            return MCPHelper::error('invalid_param', __('Pass at least one field to change', 'fluent-support'), ['fields' => array_keys(self::UPDATABLE_FIELDS)]);
        }

        if (array_key_exists('first_name', $changes) && $changes['first_name'] === '') {
            return MCPHelper::error('invalid_param', __('first_name cannot be empty', 'fluent-support'), ['fields' => ['first_name']]);
        }

        // CustomerService::update() needs the email and first name, as the admin form always sends both.
        $data = array_merge([
            'email'      => (string) $customer->email,
            'first_name' => (string) $customer->first_name,
        ], $changes);

        try {
            $updated = \FluentSupport\App\Modules\Customers\CustomerService::update($customer, $data);
        } catch (\Exception $e) {
            return MCPHelper::error('failed', __('Failed to update the customer', 'fluent-support'), ['retryable' => true]);
        }

        $item = MCPHelper::formatCustomerForMCP($updated);
        foreach (['title', 'note', 'address_line_1', 'address_line_2', 'zip'] as $field) {
            $item[$field] = $updated->{$field};
        }

        return MCPHelper::envelope(
            /* translators: %d is the customer ID */
            sprintf(__('Customer #%d updated', 'fluent-support'), $updated->id),
            ['customer' => $item, 'changed' => array_keys($changes)]
        );
    }

    /**
     * The search-customers rule: an agent limited to some mailboxes sees a customer
     * with no tickets, or with at least one ticket in a mailbox they can access.
     */
    private static function canSeeCustomer(Customer $customer)
    {
        if (!PermissionManager::getRestrictedMailboxIds()) {
            return true;
        }

        $tickets = Ticket::where('customer_id', $customer->id);
        if (!(clone $tickets)->exists()) {
            return true;
        }

        return (new AgentTicketAccess())->applyMailboxRestrictionScope($tickets)->exists();
    }
}
