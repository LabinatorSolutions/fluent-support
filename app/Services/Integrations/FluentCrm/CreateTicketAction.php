<?php

namespace FluentSupport\App\Services\Integrations\FluentCrm;

use FluentSupport\App\Modules\Customers\CustomerService;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Support\Arr;

class CreateTicketAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'fs_create_ticket';
        $this->priority = 23;
        parent::__construct();

        add_filter('fluentcrm_ajax_options_fs_inboxes', function ($options) {
            $inboxes = MailBox::select(['id', 'name'])->get();
            $formattedInboxes = [];
            foreach ($inboxes as $inbox) {
                $formattedInboxes[] = [
                    'id'    => $inbox->id,
                    'title' => $inbox->name
                ];
            }
            return $formattedInboxes;
        });
        add_filter('fluentcrm_ajax_options_fs_agents', function ($options) {
            $agents = Agent::select(['id', 'first_name', 'last_name'])->where('status', 'active')->get();
            $formattedAgents = [];
            foreach ($agents as $agent) {
                $formattedAgents[] = [
                    'id'    => $agent->id,
                    'title' => $agent->full_name
                ];
            }
            return $formattedAgents;
        });
    }

    public function getBlock()
    {
        return [
            'category'       => __('Fluent Support', 'fluent-support'),
            'title'          => __('Create Support Ticket', 'fluent-support'),
            'ticket_content' => __('Add a support ticket for contact', 'fluent-support'),
            'icon'           => 'fc-icon-apply_tag',//fluentCrmMix('images/funnel_icons/apply_tag.svg'),
            'settings'       => [
                'ticket_title'       => '',
                'ticket_description' => '',
                'default_agent'      => '',
                'business_inbox'     => ''
            ],
            'priority' => 'normal'
        ];
    }

    public function getBlockFields()
    {
        $formattedPriorities = [];
        foreach (Helper::adminTicketPriorities() as $key => $label) {
            $formattedPriorities[] = [
                'id' => $key,
                'title' => $label
            ];
        }

        return [
            'title'     => __('Create Support Ticket', 'fluent-support'),
            'sub_title' => __('Add a support ticket for contact', 'fluent-support'),
            'fields'    => [
                'ticket_title'   => [
                    'type'        => 'input-text-popper',
                    'label'       => __('Ticket Title (you can use any smart code)', 'fluent-support'),
                    'placeholder' => __('Ticket Title', 'fluent-support')
                ],
                'ticket_content' => [
                    'type'          => 'html_editor',
                    'label'         => __('Ticket Description (you can use any smart code)', 'fluent-support'),
                    'placeholder'   => __('Ticket Description', 'fluent-support'),
                    'smart_codes'   => true,
                    'context_codes' => true
                ],
                'business_inbox' => [
                    'type'        => 'rest_selector',
                    'option_key'  => 'fs_inboxes',
                    'placeholder' => __('Select Business Inbox', 'fluent-support'),
                    'label'       => __('Default Business Inbox', 'fluent-support')
                ],
                'default_agent'  => [
                    'type'        => 'rest_selector',
                    'option_key'  => 'fs_agents',
                    'placeholder' => __('Select Agent (optional)', 'fluent-support'),
                    'label'       => __('Default Assigned Agent (optional)', 'fluent-support')
                ],
                'priority' => [
                    'options' => $formattedPriorities,
                    'label' => __('Priority (Admin)', 'fluent-support'),
                    'placeholder' => __('Select Priority', 'fluent-support'),
                    'type' => 'select'
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        if (empty($sequence->settings['ticket_title']) || empty($sequence->settings['ticket_content'])) {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return false;
        }

        $subscriber->funnel_subscriber_id = $funnelSubscriberId;

        $emailSubject = apply_filters('fluent_crm/parse_campaign_email_text', Arr::get($sequence->settings, 'ticket_title'), $subscriber);
        $emailBody = apply_filters('fluent_crm/parse_campaign_email_text', Arr::get($sequence->settings, 'ticket_content'), $subscriber);

        $ticketData = [
            'title'   => substr(sanitize_text_field($emailSubject), 0, 192),
            // Funnel settings are stored unslashed, so sanitize only.
            'content' => wp_kses_post(Helper::decodeNonMarkupEntities($emailBody)),
            'source'  => 'fluent-crm'
        ];

        if (!empty($sequence->settings['business_inbox'])) {
            $ticketData['mailbox_id'] = (int)$sequence->settings['business_inbox'];
        }

        if (empty($ticketData['mailbox_id'])) {
            $defaultMailBox = Helper::getDefaultMailBox();
            if ($defaultMailBox) {
                $ticketData['mailbox_id'] = $defaultMailBox->id;
            }
        }

        if (!empty($sequence->settings['default_agent'])) {
            $ticketData['agent_id'] = (int) $sequence->settings['default_agent'];
        }

        if (!empty($sequence->settings['priority'])) {
            $ticketData['priority'] = sanitize_text_field($sequence->settings['priority']);
        }

        // The contact's status and hash are FluentCRM's own; a new customer gets the defaults.
        $customerData = Arr::except(
            Arr::only($subscriber->toArray(), (new Customer())->getFillable()),
            ['status', 'hash']
        );

        $customerData['user_id'] = $subscriber->getWpUserId();
        $customerData = array_filter($customerData);

        $customer = CustomerService::findBlockedByEmail($subscriber->email, Arr::get($customerData, 'user_id'))
            ?: CustomerService::findOrCreate($customerData);

        // Older versions copied the contact's 'subscribed' status onto the customer; make those customers active.
        // Only while still 'subscribed', so an agent's block made meanwhile is kept.
        if ($customer && $customer->exists && $customer->status === 'subscribed') {
            Customer::where('id', $customer->id)->where('status', 'subscribed')->update(['status' => 'active']);
            $customer = $customer->fresh();
        }

        if (!$customer || !$customer->exists || $customer->isBlocked()) {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return false;
        }

        $ticketData['customer_id'] = $customer->id;
        (new TicketService())->storeCustomerTicket($ticketData, $customer);

        return true;
    }
}
