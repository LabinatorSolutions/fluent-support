<?php

namespace FluentSupport\App\Services\Integrations;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Person;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;

class FluentCRM
{
    public function boot()
    {
       // add_filter('fluent_support/customer_extra_widgets', array($this, 'getCRMProfile'), 20, 2);
        add_filter('fluentcrm-support_tickets_providers', array($this, 'pushProvider'));
        add_filter('fluentcrm-get_support_tickets_fluent_support', array($this, 'getSupportTickets'), 10, 2);
    }

    public function getCRMProfile($widgets, $customer)
    {
        $profile = fluentcrm_get_crm_profile_html($customer->email, false);
        if($profile) {
            $widgets['fluentcrm'] = [
                'header' => __('CRM Profile', 'fluent-support'),
                'body_html' => $profile
            ];
        }

        return $widgets;
    }

    public function pushProvider($providers)
    {
        $providers['fluent_support'] = [
            'title' => __('Support Tickets by Fluent Support', 'fluent-crm'),
            'name'  => __('Fluent Support', 'fluent-crm')
        ];

        return $providers;
    }


    public function getSupportTickets($data, $subscriber)
    {
        $supportPerson = Customer::where('email', $subscriber->email)->first();

        if(!$supportPerson) {
            return  $data;
        }

        $tickets = Ticket::where('customer_id', $supportPerson->id)->paginate();

        $formattedTickets = [];
        foreach ($tickets as $ticket) {
            $ticketUrl = Helper::getPortalAdminBaseUrl().'tickets/'.$ticket->id.'/view';
            $actionHTML = '<a target="_blank" href="'.$ticketUrl.'">'.__('View Ticket', 'fluent-crm').'</a>';
            $formattedTickets[] = [
                'id' => '#'.$ticket->id,
                'title' => $ticket->title,
                'status' => '<span class="el-tag">'.__($ticket->status, 'fluent-crm').'</span>',
                'Submitted at' => human_time_diff(strtotime($ticket->created_at), current_time('timestamp')).__(' ago', 'fluent-crm'),
                'action' => $actionHTML
            ];
        }

        return [
            'total' => $tickets->total,
            'data'  => $formattedTickets
        ];
    }

}
