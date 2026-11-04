<?php

namespace FluentSupport\App\Modules\Customers;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\AvatarUploder;
use FluentSupport\App\Services\CustomerSearch;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\ProfileInfoService;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\Framework\Http\Request\Request;

/**
 * The Customers pages, the customer form, the ticket "change customer" picker
 * and the agent's contact search. Customers are sent from an allow list: never
 * the portal hash, IP addresses or remote ids.
 */
class CustomerController extends Controller
{
    /**
     * Each delete cascades into tickets, conversations and attachments.
     */
    const BULK_LIMIT = 100;

    // city and country fill the list's Location column.
    const LIST_FIELDS = ['id', 'first_name', 'last_name', 'full_name', 'email', 'photo', 'avatar', 'title', 'status', 'user_id', 'person_type', 'city', 'country', 'created_at', 'last_response_at'];

    const FIELDS = ['id', 'first_name', 'last_name', 'full_name', 'email', 'photo', 'avatar', 'title', 'status', 'user_id', 'person_type', 'note', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country', 'created_at', 'updated_at', 'last_response_at'];

    /**
     * Customers, newest first: search (smart_search=yes reads CustomerSearch
     * syntax), status, has_open_tickets and sorting. A plain search for an exact
     * email with no match imports the FluentCRM contact or WordPress user,
     * unless import_contacts=no.
     */
    public function index(Request $request)
    {
        $search = $request->getSafe('search', 'sanitize_text_field');
        $status = $request->getSafe('status', 'sanitize_key');
        $sortBy = $request->getSafe('sort_by', 'sanitize_key');
        $sortType = strtoupper((string) $request->getSafe('sort_type', 'sanitize_key')) === 'ASC' ? 'ASC' : 'DESC';
        $smart = $request->getSafe('smart_search', 'sanitize_key') === 'yes';
        $openOnly = $request->getSafe('has_open_tickets', 'sanitize_key') === 'yes';
        $ranked = $smart && $sortBy === 'relevance' && CustomerSearch::isRankable($search);

        $query = function () use ($search, $status, $sortBy, $sortType, $smart, $openOnly, $ranked) {
            $query = Customer::query();
            if ($ranked) {
                CustomerSearch::orderByRelevance($query, $search);
            } else {
                $sortBy = in_array($sortBy, ['created_at', 'last_response_at', 'first_name', 'id'], true) ? $sortBy : 'created_at';
                // id breaks ties so pages stay stable.
                $query->orderBy($sortBy, $sortType)->orderBy('id', $sortType);
            }
            if ($search) {
                $smart ? CustomerSearch::apply($query, $search) : $query->searchBy($search);
            }
            if ($status && $status !== 'all') {
                $query->filterByStatues([$status]);
            }
            if ($openOnly) {
                // Only tickets the agent may open, so the filter never reveals one.
                $query->whereIn('id', $this->visibleTickets()->select('customer_id')->whereIn('status', Helper::getTkStatusesByGroupName('open') ?: ['new', 'active']));
            }
            return $query->paginate();
        };

        $page = $query();
        $import = !$openOnly && !$status && $request->getSafe('import_contacts', 'sanitize_key') !== 'no';
        if ($page->isEmpty() && $import && CustomerService::importByEmail($search)) {
            $page = $query();
        }

        $page = $page->toArray();
        $page['data'] = $this->withCounts($page['data']);

        $data = ['customers' => $page];
        if ($smart) {
            $data['search_meta'] = CustomerSearch::describe($search, $ranked);
        }

        return $data;
    }

    /**
     * One customer; `with` adds widgets, tickets, ticket_stats and fluentcrm_profile.
     */
    public function show(Request $request, $customer_id)
    {
        $customer = $this->find($customer_id);
        $with = array_map('sanitize_key', (array) $request->get('with', []));

        $data = ['customer' => $this->format($customer)];

        if (in_array('widgets', $with, true)) {
            $data['widgets'] = ProfileInfoService::getProfileExtraWidgets($customer);
        }
        if (in_array('tickets', $with, true)) {
            $data['tickets'] = $this->visibleTickets()
                ->select(['id', 'title', 'status', 'customer_id', 'ticket_number', 'serial_number', 'created_at', 'updated_at'])
                ->where('customer_id', $customer->id)
                ->latest('id')
                ->limit(apply_filters('fluent_support/customer_page_ticket_widgets_limit', 20))
                ->get();
        }
        if (in_array('ticket_stats', $with, true)) {
            $data['ticket_stats'] = array_map('intval', $this->visibleTickets()->where('customer_id', $customer->id)
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all());
        }
        if (in_array('fluentcrm_profile', $with, true)) {
            $data['fluentcrm_profile'] = Helper::getFluentCrmContactData($customer);
        }

        return $data;
    }

    public function create(Request $request)
    {
        // The WordPress user is linked through the email only; a client user_id
        // could make the new record another account's portal identity.
        if ($request->get('user_id')) {
            throw ApiException::invalid(__('user_id is not accepted when creating a customer. The WordPress user is linked by the customer email.', 'fluent-support'));
        }

        $data = $this->input($request);
        if (!is_email($data['email'] ?? '') || empty($data['first_name'])) {
            throw ApiException::invalid(__('A valid email and a first name are required.', 'fluent-support'));
        }
        if (Customer::query()->withoutGlobalScopes()->where('email', $data['email'])->exists()) {
            throw ApiException::invalid(__('The email has already been taken.', 'fluent-support'));
        }

        if ($user = get_user_by('email', $data['email'])) {
            $data['user_id'] = $user->ID;
            $data['first_name'] = $data['first_name'] ?: $user->first_name;
            $data['last_name'] = ($data['last_name'] ?? '') ?: $user->last_name;
        }

        return [
            'message'  => __('Customer has been added', 'fluent-support'),
            'customer' => $this->format(Customer::create($data))
        ];
    }

    public function update(Request $request, $customer_id)
    {
        $data = $this->input($request);
        foreach (Helper::getCustomRegistrationFieldKeys() as $key) {
            if ($request->has($key)) {
                $data[$key] = $request->getSafe($key, 'sanitize_text_field');
            }
        }

        return [
            'message'  => __('Customer has been updated', 'fluent-support'),
            'customer' => $this->format(CustomerService::update($this->find($customer_id), $data))
        ];
    }

    /**
     * Deletes the customer and their tickets.
     */
    public function delete($customer_id)
    {
        CustomerService::delete($this->find($customer_id));

        return ['message' => __('Customer Deleted Successfully', 'fluent-support')];
    }

    public function bulkDelete(Request $request)
    {
        $customers = Customer::whereIn('id', $this->ids($request))->get();
        foreach ($customers as $customer) {
            CustomerService::delete($customer);
        }

        $count = $customers->count();

        return [
            'message'         => $count === 1
                ? __('Customer deleted successfully', 'fluent-support')
                /* translators: %1$d: number of customers deleted */
                : ($count ? sprintf(__('%1$d customers deleted successfully', 'fluent-support'), $count) : __('No customers were deleted', 'fluent-support')),
            'deleted_count'   => $count,
            'total_requested' => count($this->ids($request)),
        ];
    }

    /**
     * Blocks (inactive) or unblocks (active) customers. Agents are never changed.
     */
    public function bulkStatus(Request $request)
    {
        $status = $request->getSafe('status', 'sanitize_key');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw ApiException::invalid(__('Invalid customer status.', 'fluent-support'));
        }

        // An empty status counts as active, so it can be blocked too.
        $updated = Customer::whereIn('id', $this->ids($request))
            ->where(fn($q) => $q->where('status', '!=', $status)->orWhereNull('status'))
            ->update(['status' => $status]);

        return [
            /* translators: %d: number of customers */
            'message' => sprintf(_n('%d customer updated', '%d customers updated', $updated, 'fluent-support'), $updated),
            'updated' => (int) $updated,
        ];
    }

    /**
     * The customer form's fields; with user_id, the address part also lists
     * the custom registration fields.
     */
    public function fields(Request $request, $customer_id)
    {
        $field = fn($label, $type = 'input-text', $extra = []) => array_merge(['label' => $label, 'data_type' => 'text', 'placeholder' => $label, 'type' => $type, 'wrapper_class' => 'fs_half_field'], $extra);

        $basic = [
            'first_name'  => $field(__('First Name', 'fluent-support'), 'input-text', ['wrapper_class' => 'fs_half_field required']),
            'last_name'   => $field(__('Last Name', 'fluent-support')),
            'email'       => $field(__('Email', 'fluent-support'), 'input-text', ['data_type' => 'email', 'wrapper_class' => 'fs_half_field required', 'disabled' => !empty($customer_id)]),
            'title'       => $field(__('Job Title', 'fluent-support')),
            'note'        => ['label' => __('Note', 'fluent-support'), 'data_type' => 'textarea', 'placeholder' => __('Note', 'fluent-support'), 'type' => 'input-text'],
            'status'      => [
                'label'     => __('Status', 'fluent-support'),
                'data_type' => 'text',
                'type'      => 'input-radio',
                'options'   => [
                    'active'   => ['id' => 'active', 'value' => 'active', 'label' => __('Active', 'fluent-support')],
                    'inactive' => ['id' => 'inactive', 'value' => 'inactive', 'label' => __('Blocked', 'fluent-support')],
                ],
            ],
            'status_html' => [
                'dependency'    => ['depends_on' => 'status', 'operator' => '=', 'value' => 'inactive'],
                'type'          => 'html-viewer',
                'wrapper_class' => 'fs_warn_alert_wrapper',
                'html'          => __('If you select the <b>Blocked</b> status, this customer will not be able to submit a ticket or any response.', 'fluent-support'),
            ],
        ];

        $address = [
            'address_line_1' => $field(__('Address Line 1', 'fluent-support')),
            'address_line_2' => $field(__('Address Line 2', 'fluent-support')),
            'city'           => $field(__('City', 'fluent-support')),
            'state'          => $field(__('State', 'fluent-support')),
            'zip'            => $field(__('Zip Code', 'fluent-support')),
            'country'        => ['label' => __('Country', 'fluent-support'), 'placeholder' => __('Country', 'fluent-support'), 'type' => 'country-selector', 'wrapper_class' => 'fs_half_field'],
        ];

        if ($request->getInt('user_id')) {
            $address = apply_filters('fluent_support/custom_registration_form_fields', $address);
            foreach ($address as $key => $item) {
                // The form renders custom fields as input-text with the real type in data_type.
                if (($item['type'] ?? '') !== 'country-selector') {
                    $address[$key]['data_type'] = $item['type'] ?? 'input-text';
                    $address[$key]['type'] = 'input-text';
                }
            }
        }

        return ['customerField' => ['basic_fields' => $basic, 'address_fields' => $address, 'loading' => false]];
    }

    public function uploadAvatar(Request $request, AvatarUploder $uploader)
    {
        try {
            return $uploader->addOrUpdateProfileImage($request->files(), $request->getInt('customer_id'), 'customer');
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage(), (int) $e->getCode());
        }
    }

    public function resetAvatar($customer)
    {
        $this->find($customer)->restoreAvatar();

        return ['message' => __('Customer avatar reset to gravatar default', 'fluent-support')];
    }

    /**
     * Contacts for a new ticket: customers, then FluentCRM contacts, then
     * WordPress users (never staff). A partial search needs fst_sensitive_data
     * (filter fluent_support/contact_search_partial_allowed); otherwise the
     * full email. Rate limited.
     */
    public function searchContact(Request $request)
    {
        // '*' is a WP_User_Query wildcard; a lone '*' would list every user.
        $search = trim(str_replace('*', '', (string) $request->getSafe('search', 'sanitize_text_field')));
        $isEmail = (bool) is_email($search);

        if (!$search) {
            throw ApiException::invalid(__('Please provide search string', 'fluent-support'));
        }
        if (!$isEmail && !apply_filters('fluent_support/contact_search_partial_allowed', PermissionManager::currentUserCan('fst_sensitive_data'))) {
            throw ApiException::invalid(__('Enter the customer\'s full email address to find them.', 'fluent-support'));
        }
        if (!$isEmail && mb_strlen($search) < 3) {
            throw ApiException::invalid(__('Please provide at least 3 characters to search', 'fluent-support'));
        }
        if (Helper::hitRateLimit('fs_contact_search_' . get_current_user_id(), 60, 5 * MINUTE_IN_SECONDS)) {
            throw new ApiException(__('Too many contact searches. Please try again in a few minutes.', 'fluent-support'), 429);
        }

        global $wpdb;
        // Escape LIKE wildcards: '_' is valid in emails.
        $like = $wpdb->esc_like($search);
        $columns = ['first_name', 'last_name', 'email', 'id', 'user_id'];
        $result = fn($provider, $data) => ['type' => 'search_result', 'provider' => $provider, 'data' => $data, 'is_email' => $isEmail];

        $customers = $isEmail ? Customer::select($columns)->where('email', $search)->get() : Customer::select($columns)->searchBy($like)->limit(10)->get();
        if (!$customers->isEmpty()) {
            return $result('fluent_support', $customers) + ['search' => $search];
        }

        if (defined('FLUENTCRM')) {
            $contacts = ($isEmail ? \FluentCrm\App\Models\Subscriber::where('email', $search) : \FluentCrm\App\Models\Subscriber::searchBy($like)->limit(10))
                ->select($columns)->get()
                ->filter(fn($contact) => !Helper::isSupportStaffEmail($contact->email))->values();
            if (!$contacts->isEmpty()) {
                return $result('fluent_crm', $contacts);
            }
        }

        $users = (new \WP_User_Query([
            'search'  => $search,
            'number'  => 10,
            'exclude' => array_map('intval', Agent::whereNotNull('user_id')->pluck('user_id')->all()),
        ]))->get_results();
        $users = array_values(array_filter($users, fn($user) => !user_can($user, 'manage_options')));
        if ($users) {
            return $result('wp_users', array_map(fn($user) => [
                'id'         => $user->ID,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'user_id'    => $user->ID,
                'email'      => $user->user_email,
            ], $users));
        }

        return ['type' => 'none', 'provider' => 'none', 'data' => [], 'is_email' => $isEmail];
    }

    private function find($customerId)
    {
        $customer = Customer::find((int) $customerId);
        if (!$customer) {
            throw ApiException::notFound();
        }

        return $customer;
    }

    /**
     * The editable fields sent, sanitized.
     */
    private function input(Request $request)
    {
        $data = [];
        foreach (CustomerService::EDITABLE as $key) {
            if ($request->has($key)) {
                $value = $request->get($key);
                $data[$key] = in_array($key, ['note', 'address_line_1', 'address_line_2'], true) ? sanitize_textarea_field((string) $value) : sanitize_text_field((string) $value);
            }
        }
        if (isset($data['email'])) {
            $data['email'] = sanitize_email($data['email']);
        }
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            unset($data['status']);
        }

        return $data;
    }

    /**
     * customer_ids, positive and unique, at most BULK_LIMIT.
     */
    private function ids(Request $request)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->get('customer_ids', [])))));
        if (!$ids || count($ids) > self::BULK_LIMIT) {
            /* translators: %d: the most customers one request may change */
            throw ApiException::invalid(sprintf(__('Choose between 1 and %d customers.', 'fluent-support'), self::BULK_LIMIT));
        }

        return $ids;
    }

    private function format(Customer $customer)
    {
        $data = array_intersect_key($customer->toArray(), array_flip(self::FIELDS));
        $data['profile_edit_url'] = $customer->getUserProfileEditUrl();

        // Custom registration field values live in the linked user's meta.
        if ($customer->user_id) {
            foreach (apply_filters('fluent_support/custom_registration_form_fields_key', []) as $key) {
                $value = get_user_meta($customer->user_id, $key, true);
                if ($value !== '') {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * List rows with the counts of tickets, open tickets and replies the agent may see.
     */
    private function withCounts(array $rows)
    {
        $ids = array_column($rows, 'id') ?: [0];
        $count = fn($query, $column) => $query->selectRaw("$column AS person, COUNT(*) AS total")->groupBy($column)->pluck('total', 'person')->all();

        $tickets = $count($this->visibleTickets()->whereIn('customer_id', $ids), 'customer_id');
        $open = $count($this->visibleTickets()->whereIn('customer_id', $ids)->whereIn('status', Helper::getTkStatusesByGroupName('open') ?: ['new', 'active']), 'customer_id');
        $replies = $count((new AgentTicketAccess())->applyAccessScopeViaTicket(Conversation::whereIn('person_id', $ids)), 'person_id');

        return array_map(function ($row) use ($tickets, $open, $replies) {
            $id = $row['id'];
            return array_intersect_key($row, array_flip(self::LIST_FIELDS)) + [
                'total_tickets'   => (int) ($tickets[$id] ?? 0),
                'open_tickets'    => (int) ($open[$id] ?? 0),
                'total_responses' => (int) ($replies[$id] ?? 0),
            ] + ($row['user_id'] ? ['user_profile' => admin_url('user-edit.php?user_id=' . $row['user_id'])] : []);
        }, $rows);
    }

    /**
     * Tickets the agent may open (visibility and mailbox restrictions).
     */
    private function visibleTickets()
    {
        return (new AgentTicketAccess())->applyAccessScope(Ticket::query());
    }
}
