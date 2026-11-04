<?php

namespace FluentSupport\App\Services\Integrations\FluentCrm;

use FluentCrm\App\Models\Subscriber;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

class FluentCRMWidgets
{
    public function boot()
    {
        add_action('fluent_support/customer_created', array($this, 'maybeCreateContact'), 10, 1);
        add_filter('fluentcrm-support_tickets_providers', array($this, 'pushProvider'));
        add_filter('fluentcrm-get_support_tickets_fluent_support', array($this, 'getSupportTickets'), 10, 2);

        new CreateTicketAction();
    }

    public function pushProvider($providers)
    {
        $providers['fluent_support'] = [
            'title' => __('Support Tickets by Fluent Support', 'fluent-support'),
            'name'  => __('Fluent Support', 'fluent-support')
        ];

        return $providers;
    }

    public function getSupportTickets($data, $subscriber)
    {
        // CRM access isn't ticket access: require the single-ticket view gate, then list only openable tickets.
        $agent = Helper::getAgentByUserId();

        $canViewTickets = PermissionManager::userCan([
            'fst_view_tickets',
            'fst_draft_reply',
            'fst_manage_own_tickets',
            'fst_manage_unassigned_tickets',
            'fst_manage_other_tickets'
        ]);

        if (!$agent || !$canViewTickets) {
            return $data;
        }

        $supportPerson = Customer::where('email', $subscriber->email)->first();

        if (!$supportPerson) {
            return $data;
        }

        $tickets = (new AgentTicketAccess())
            ->applyAccessScope(Ticket::where('customer_id', $supportPerson->id), $agent)
            ->latest('id')
            ->paginate();

        $formattedTickets = [];
        foreach ($tickets as $ticket) {
            $ticketUrl = Helper::getPortalAdminBaseUrl() . 'tickets/' . $ticket->id . '/view';
            $actionHTML = '<a target="_blank" href="' . $ticketUrl . '">' . __('View Ticket', 'fluent-support') . '</a>';
            $formattedTickets[] = [
                'id'           => '#' . $ticket->id,
                // FluentCRM renders these columns as HTML.
                'title'        => esc_html($ticket->title),
                'status'       => '<span class="el-tag">' . $ticket->status . '</span>',
                /* translators: %s is a human-readable time difference, e.g. "2 hours" */
                'Submitted at' => sprintf(__('%s ago', 'fluent-support'), human_time_diff(strtotime($ticket->created_at), current_time('timestamp'))),
                'action'       => $actionHTML
            ];
        }

        return [
            'total' => $tickets->total(),
            'data'  => $formattedTickets
        ];
    }

    public function maybeCreateContact($customer)
    {
        $syncSettings = $this->getSyncSettings();
        if ($syncSettings['enabled'] != 'yes') {
            return false;
        }

        $email = $customer->email;

        if (!class_exists('\FluentCrm\App\Models\Subscriber')) {
            return false;
        }

        $subscriber = Subscriber::where('email', $email)->first();

        $customerData = array_filter([
            'first_name'     => $customer->first_name,
            'last_name'      => $customer->last_name,
            'email'          => $customer->email,
            'address_line_1' => $customer->address_line_1,
            'address_line_2' => $customer->address_line_2,
            'postal_code'    => $customer->zip,
            'city'           => $customer->city,
            'state'          => $customer->state,
            'country'        => $customer->country,
        ]);


        if ($subscriber) {
            unset($customerData['email']);
            $subscriber->fill($customerData);
            $subscriber->save();
        } else {
            $customerData['status'] = $syncSettings['default_status'];
            $subscriber = FluentCrmApi('contacts')->createOrUpdate($customerData);

            if (!$subscriber) {
                return false;
            }

            if ($customerData['status'] == 'pending' && $subscriber->status == 'pending') {
                $subscriber->sendDoubleOptinEmail();
            }
        }

        if ($syncSettings['assigned_list']) {
            $subscriber->attachLists([$syncSettings['assigned_list']]);
        }

        if ($syncSettings['assigned_tags']) {
            $subscriber->attachTags($syncSettings['assigned_tags']);
        }

        return true;
    }

    private function getSyncSettings()
    {
        $settings = Helper::getOption('_fluentcrm_intergration_settings');

        $settingDefault = [
            'enabled'        => 'no',
            'default_status' => 'subscribed',
            'assigned_list'  => '',
            'assigned_tags'  => []
        ];

        if (!$settings) {
            return $settingDefault;
        }

        return wp_parse_args($settings, $settingDefault);
    }
}
