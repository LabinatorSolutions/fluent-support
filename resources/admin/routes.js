import Dashboard from './Components/Dashboard';
import TicketsView from './Modules/Tickets/TicketsView';
import AllTickets from './Modules/Tickets/AllTickets';
import ViewTicket from './Modules/Tickets/ViewTicket';
import SettingsView from './Modules/Settings/SettingsView';
import Products from './Modules/Settings/Products';
import EmailNotifications from './Modules/Settings/EmailNotifications';
import BusinessSettings from './Modules/Settings/BusinessSettings';
import Agents from './Modules/Settings/SupportStaffs';
import IntegrationView from './Modules/Settings/IntegrationView';

import Customers from './Modules/Customers/Customers';
import SavedReplies from './Modules/SavedReplies/Replies';
import Reports from './Modules/Reports/Reports';

import MailBoxRoot from './Modules/MailBoxes/MailBoxRoot';
import ChooseMailBox from './Modules/MailBoxes/ChooseMailBox';
import MailBoxSettings from './Modules/MailBoxes/MailBoxSettings';

export default [
    {
        path: '/',
        name: 'dashboard',
        component: Dashboard,
        meta: {
            active: 'dashboard'
        }
    },
    {
        path: '/tickets',
        component: TicketsView,
        meta: {
            active: 'tickets'
        },
        children: [
            {
                name: 'tickets',
                path: '',
                component: AllTickets
            },
            {
                name: 'view_ticket',
                path: ':ticket_id/view',
                component: ViewTicket,
                props: true
            },
        ]
    },
    {
        path: '/settings',
        component: SettingsView,
        meta: {
            active: 'settings'
        },
        children: [
            {
                name: 'business_settings',
                path: '',
                component: BusinessSettings
            },
            {
                name: 'products',
                path: 'products',
                component: Products
            },
            {
                name: 'email_notifications',
                path: 'email_notifications',
                component: EmailNotifications
            },
            {
                path: 'support-staffs',
                name: 'support-staffs',
                component: Agents
            },
            {
                path: 'integration',
                name: 'integration',
                component: IntegrationView
            }
        ]
    },
    {
        path: '/mailboxes',
        component: ChooseMailBox,
        name: 'mailboxes',
        meta: {
            active: 'mailboxes'
        }
    },
    {
        path: '/mailboxes/:box_id',
        component: MailBoxRoot,
        props: true,
        meta: {
            active: 'mailboxes'
        },
        children: [
            {
                path: 'settings',
                name: 'box_settings',
                component: MailBoxSettings
            }
        ]
    },
    {
        path: '/customers',
        name: 'Customers',
        component: Customers,
        meta: {
            active: 'customers'
        }
    },
    {
        path: '/saved-replies',
        name: 'saved_replies',
        component: SavedReplies,
        meta: {
            active: 'saved_replies'
        }
    },
    {
        path: '/reports',
        name: 'reports',
        component: Reports,
        meta: {
            active: 'reports'
        }
    }
];

