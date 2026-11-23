// Each page loads as its own chunk the first time its route opens.
const Dashboard = () => import('./Modules/Dashboard/Dashboard.vue');
const Setup = () => import('./Components/Setup');
const TicketsView = () => import('./Modules/Tickets/TicketsView');
const AllTickets = () => import('./Modules/Tickets/AllTickets');
const ViewTicket = () => import('./Modules/Tickets/ViewTicket');

const SettingsView = () => import('./Modules/Settings/SettingsView');
const BusinessSettings = () => import('./Modules/Settings/BusinessSettings');
const Products = () => import('./Modules/Settings/Products');
const TicketTags = () => import('./Modules/Settings/TicketTags');
const Agents = () => import('./Modules/Settings/SupportStaffs');
const IntegrationView = () => import('./Modules/Settings/IntegrationView');
const RecaptchaView = () => import('./Modules/Settings/RecaptchaView');
const TicketFormConfig = () => import("./Modules/Settings/TicketFormConfig");
const CustomFields = () => import("./Modules/Settings/CustomFields");
const LicenseManagement = () => import("./Modules/Settings/LicenseManagement");
const FluentCRMIntegration = () => import("./Modules/Settings/FluentCRMIntegration");
const AutoCloseSettings = () => import("./Modules/Settings/AutoCloseSettings");
const UploadIntegrationView = () => import('./Modules/Settings/FileUploadSettings/UploadIntegrationView');

const Customers = () => import('./Modules/Customers/Customers');
const SavedReplies = () => import('./Modules/SavedReplies/Replies');
const CustomerPage = () => import("./Modules/Customers/CustomerPage");

const MailBoxRoot = () => import('./Modules/MailBoxes/MailBoxRoot');
const ChooseMailBox = () => import('./Modules/MailBoxes/ChooseMailBox');
const MailBoxSettings = () => import('./Modules/MailBoxes/MailBoxSettings');
const MailBoxEmailSettings = () => import('./Modules/MailBoxes/BoxEmailSettings');
const MailBoxEmailPiping = () => import('./Modules/MailBoxes/MailBoxEmailPiping');

const Report = () => import('./Modules/Reports/Report');

const Workflows = () => import('./Modules/Workflows/AllWorkflows');
const EditWorkFlow = () => import('./Modules/Workflows/EditWorkFlow');

const Activity = () => import("./Modules/ActivityLogger/Activity");
const NotificationsPage = () => import('./Modules/Notifications/NotificationsPage.vue');

const IncomingWebhook = () => import("./Modules/Settings/IncomingWebhook");
const RemotePortal = () => import("./Modules/Settings/RemotePortal");
const TicketImporter = () => import("./Modules/Settings/TicketImporter/TicketImporter");
const HelpScoutAuthorization = () => import("./Modules/Settings/TicketImporter/HelpScout/HelpScoutAuthorization");
const IntegrationStatuses = () => import('./Modules/Settings/IntegrationStatuses');
const AIIntegration = () => import("@/admin/Modules/Settings/AIIntegration.vue");
const McpIntegration = () => import("@/admin/Modules/Settings/McpIntegration.vue");
const CronMonitor = () => import("@/admin/Modules/Settings/SystemTools/CronMonitor.vue");
const DatabaseHealth = () => import("@/admin/Modules/Settings/SystemTools/DatabaseHealth.vue");
const DataCleanup = () => import("@/admin/Modules/Settings/SystemTools/DataCleanup.vue");

// The pages agents open most. start.js loads them in the background once the
// app is idle, so opening them later needs no network request.
export const warmRoutes = [Dashboard, TicketsView, AllTickets, ViewTicket];

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
        path: '/setup',
        name: 'setup',
        component: Setup,
        meta: {
            active: ''
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
                path: ':ticket_id(\\d+)/view',
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
                name: 'global_settings',
                path: '',
                component: BusinessSettings
            },
            {
                name: 'reCaptcha',
                path: 'reCaptcha',
                component: RecaptchaView
            },
            {
                name: 'ticket-form-config',
                path: 'ticket-form-config',
                component: TicketFormConfig
            },
            {
                name: 'custom_fields',
                path: 'custom-fields',
                component: CustomFields
            },
            {
                name: 'tags',
                path: 'ticket-tags',
                component: TicketTags
            },
            {
                name: 'products',
                path: 'products',
                component: Products
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
            },
            {
                path: 'upload_integration',
                name: 'upload_integration',
                component: UploadIntegrationView
            },
            {
                path: 'fluentcrm-integration',
                name: 'fluentcrm_integration',
                component: FluentCRMIntegration
            },
            {
                path: 'license-management',
                name: 'license',
                component: LicenseManagement
            },
            {
                path: 'incoming-webhook',
                name: 'incoming-webhook',
                component: IncomingWebhook
            },
            {
                path: 'remote-portal',
                name: 'remote_portal',
                component: RemotePortal
            },
            {
                path: 'auto-close',
                name: 'auto_close',
                component: AutoCloseSettings
            },
            {
                path: 'ticket_importer',
                name: 'ticket_importer',
                component: TicketImporter
            },
            {
                path: '/integration_statuses',
                name: 'integration_statuses',
                component: IntegrationStatuses,
                props: true,
            },
            {
                path: '/ai_integration',
                name: 'ai_integration',
                component: AIIntegration,
                props: true,
            },
            {
                path: '/ai-agents',
                name: 'mcp_settings',
                component: McpIntegration,
            },
            {
                path: 'cron-monitor',
                name: 'cron_monitor',
                component: CronMonitor
            },
            {
                path: 'database-health',
                name: 'database_health',
                component: DatabaseHealth
            },
            {
                path: 'data-cleanup',
                name: 'data_cleanup',
                component: DataCleanup
            },

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
        path: '/notifications',
        name: 'notifications',
        component: NotificationsPage,
        meta: {
            active: ''
        }
    },
    {
        path: '/mailboxes/:box_id(\\d+)',
        component: MailBoxRoot,
        props: true,
        meta: {
            active: 'mailboxes'
        },
        children: [
            {
                props: true,
                path: 'settings',
                name: 'box_settings',
                component: MailBoxSettings
            },
            {
                props: true,
                path: 'email_settings',
                name: 'email_settings',
                component: MailBoxEmailSettings
            },
            {
                props: true,
                path: 'email_piping',
                name: 'email_piping',
                component: MailBoxEmailPiping
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
        path: '/customers/:customer_id(\\d+)',
        name: 'view_customer',
        component: CustomerPage,
        props: true,
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
        component: Report,
        meta: {
            active: 'reports'
        }
    },
    {
        path: '/workflows',
        name: 'workflows',
        component: Workflows,
        meta: {
            active: 'workflows'
        }
    },
    {
        path: '/workflows/:workflow_id(\\d+)/edit',
        name: 'edit-workflow',
        component: EditWorkFlow,
        props: true,
        meta: {
            active: 'workflows'
        }
    },
    {
        path: '/activity',
        name: 'activity',
        component: Activity,
        meta: {
            active: 'activity'
        }
    },
    {
        path: '/help_scout',
        name: 'help_scout',
        component: HelpScoutAuthorization,
        props: true,
    },
    {
        path: "/:catchAll(.*)*",
        name: 'not_found',
        component: Dashboard,
        meta: {
            active: 'dashboard'
        }
    }
];
