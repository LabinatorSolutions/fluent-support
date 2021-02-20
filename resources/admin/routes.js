import Dashboard from './Components/Dashboard';
import TicketsView from './Modules/Tickets/TicketsView';
import AllTickets from './Modules/Tickets/AllTickets';
import ViewTicket from './Modules/Tickets/ViewTicket';
import SettingsView from './Modules/Settings/SettingsView';
import Products from './Modules/Settings/Products';

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
        children: [
            {
                name: 'products',
                path: 'products',
                component: Products
            }
        ]
    }
];

