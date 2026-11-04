// Builder fields when Pro hasn't sent advanced_filter_options (free preview; Pro supplies its own list).
export default function defaultFilterOptions($t) {
    return [
        {
            label: $t('Tickets'),
            value: 'tickets',
            children: [
                { label: $t('Title'), value: 'title' },
                { label: $t('Content'), value: 'content' },
                { label: $t('Conversation Content'), value: 'conversation_content' },
                { label: $t('Status'), value: 'status', type: 'selections', component: 'options_selector', option_key: 'ticket_statuses', is_multiple: true, is_singular_value: true },
                { label: $t('Client Priority'), value: 'client_priority', type: 'selections', component: 'options_selector', option_key: 'client_priorities', is_multiple: false, is_singular_value: true },
                { label: $t('Admin Priority'), value: 'priority', type: 'selections', component: 'options_selector', option_key: 'admin_priorities', is_multiple: true, is_singular_value: true },
                { label: $t('Tags'), value: 'tags', type: 'selections', component: 'options_selector', option_key: 'ticket_tags', is_multiple: true },
                { label: $t('Products'), value: 'product', type: 'selections', component: 'options_selector', option_key: 'support_products', is_multiple: true, is_singular_value: true },
                { label: $t('Waiting For Reply'), value: 'waiting_for_reply', type: 'selections', option_key: 'waiting_for_reply', is_singular_value: true, options: { yes: $t('Yes'), no: $t('No') } },
                { label: $t('Assigned Agent'), value: 'agent_id', type: 'selections', component: 'options_selector', option_key: 'support_agents', is_singular_value: true },
                { label: $t('Ticket Mailbox'), value: 'mailbox_id', type: 'selections', component: 'options_selector', option_key: 'mailboxes', is_singular_value: true, is_multiple: true },
                { label: $t('Ticket Created'), value: 'created_at', type: 'dates' },
                { label: $t('Last Response'), value: 'updated_at', type: 'dates' },
                { label: $t('Customer Waiting From'), value: 'waiting_since', type: 'dates' },
                { label: $t('Last Agent Response'), value: 'last_agent_response', type: 'dates' },
                { label: $t('Last Customer Response'), value: 'last_customer_response', type: 'dates' }
            ]
        },
        {
            label: $t('Customer'),
            value: 'customer',
            children: [
                { label: $t('First Name'), value: 'first_name' },
                { label: $t('Last Name'), value: 'last_name' },
                { label: $t('Email'), value: 'email' },
                { label: $t('Address Line 1'), value: 'address_line_1' },
                { label: $t('Address Line 2'), value: 'address_line_2' },
                { label: $t('City'), value: 'city' },
                { label: $t('State'), value: 'state' },
                { label: $t('Postal Code'), value: 'postal_code' },
                { label: $t('Phone'), value: 'phone' }
            ]
        },
        {
            label: $t('Agent'),
            value: 'agent',
            children: [
                { label: $t('First Name'), value: 'first_name' },
                { label: $t('Last Name'), value: 'last_name' },
                { label: $t('Email'), value: 'email' }
            ]
        }
    ];
}
