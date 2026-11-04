<?php return [
    '_Modal.js' => [
        'file' => 'admin/js/Modal.js',
        'name' => 'Modal',
        'imports' => [
            '_app.js'
        ]
    ],
    '_NarrowPromo.js' => [
        'file' => 'admin/js/NarrowPromo.js',
        'name' => 'NarrowPromo',
        'imports' => [
            '_app.js'
        ]
    ],
    '_ProShowcase.js' => [
        'file' => 'admin/js/ProShowcase.js',
        'name' => 'ProShowcase',
        'imports' => [
            '_app.js'
        ]
    ],
    '_RemoteSelector.js' => [
        'file' => 'admin/js/RemoteSelector.js',
        'name' => 'RemoteSelector',
        'imports' => [
            '_app.js'
        ]
    ],
    '_TableEmptyStateImage.js' => [
        'file' => 'admin/js/TableEmptyStateImage.js',
        'name' => 'TableEmptyStateImage',
        'imports' => [
            '_app.js'
        ]
    ],
    '_TableMoreActions.js' => [
        'file' => 'admin/js/TableMoreActions.js',
        'name' => 'TableMoreActions',
        'imports' => [
            '_app.js'
        ]
    ],
    '_TableSkeleton.js' => [
        'file' => 'admin/js/TableSkeleton.js',
        'name' => 'TableSkeleton',
        'imports' => [
            '_app.js'
        ]
    ],
    '__CustomerForm.js' => [
        'file' => 'admin/js/_CustomerForm.js',
        'name' => '_CustomerForm',
        'imports' => [
            '_app.js'
        ]
    ],
    '__ScheduledReplyBadge.js' => [
        'file' => 'admin/js/_ScheduledReplyBadge.js',
        'name' => '_ScheduledReplyBadge',
        'imports' => [
            '_app.js',
            '_NarrowPromo.js'
        ]
    ],
    '__StructuredWidget.js' => [
        'file' => 'admin/js/_StructuredWidget.js',
        'name' => '_StructuredWidget',
        'imports' => [
            '_app.js'
        ]
    ],
    '__Tags.js' => [
        'file' => 'admin/js/_Tags.js',
        'name' => '_Tags',
        'imports' => [
            '_app.js'
        ]
    ],
    '_app.js' => [
        'file' => 'admin/js/app.js',
        'name' => 'app',
        'dynamicImports' => [
            'resources/admin/Modules/Dashboard/Dashboard.vue',
            'resources/admin/Components/Setup.vue',
            'resources/admin/Modules/Tickets/TicketsView.vue',
            'resources/admin/Modules/Tickets/AllTickets.vue',
            'resources/admin/Modules/Tickets/ViewTicket.vue',
            'resources/admin/Modules/Settings/SettingsView.vue',
            'resources/admin/Modules/Settings/BusinessSettings.vue',
            'resources/admin/Modules/Settings/Products.vue',
            'resources/admin/Modules/Settings/TicketTags.vue',
            'resources/admin/Modules/Settings/SupportStaffs.vue',
            'resources/admin/Modules/Settings/IntegrationView.vue',
            'resources/admin/Modules/Settings/RecaptchaView.vue',
            'resources/admin/Modules/Settings/TicketFormConfig.vue',
            'resources/admin/Modules/Settings/CustomFields.vue',
            'resources/admin/Modules/Settings/LicenseManagement.vue',
            'resources/admin/Modules/Settings/FluentCRMIntegration.vue',
            'resources/admin/Modules/Settings/AutoCloseSettings.vue',
            'resources/admin/Modules/Settings/FileUploadSettings/UploadIntegrationView.vue',
            'resources/admin/Modules/Customers/Customers.vue',
            'resources/admin/Modules/SavedReplies/Replies.vue',
            'resources/admin/Modules/Customers/CustomerPage.vue',
            'resources/admin/Modules/MailBoxes/MailBoxRoot.vue',
            'resources/admin/Modules/MailBoxes/ChooseMailBox.vue',
            'resources/admin/Modules/MailBoxes/MailBoxSettings.vue',
            'resources/admin/Modules/MailBoxes/BoxEmailSettings.vue',
            'resources/admin/Modules/MailBoxes/MailBoxEmailPiping.vue',
            'resources/admin/Modules/Reports/Report.vue',
            'resources/admin/Modules/Workflows/AllWorkflows.vue',
            'resources/admin/Modules/Workflows/EditWorkFlow.vue',
            'resources/admin/Modules/ActivityLogger/Activity.vue',
            'resources/admin/Modules/Notifications/NotificationsPage.vue',
            'resources/admin/Modules/Settings/IncomingWebhook.vue',
            'resources/admin/Modules/Settings/RemotePortal.vue',
            'resources/admin/Modules/Settings/TicketImporter/TicketImporter.vue',
            'resources/admin/Modules/Settings/TicketImporter/HelpScout/HelpScoutAuthorization.vue',
            'resources/admin/Modules/Settings/IntegrationStatuses.vue',
            'resources/admin/Modules/Settings/AIIntegration.vue',
            'resources/admin/Modules/Settings/McpIntegration.vue',
            'resources/admin/Modules/Settings/SystemTools/CronMonitor.vue',
            'resources/admin/Modules/Settings/SystemTools/DatabaseHealth.vue',
            'resources/admin/Modules/Settings/SystemTools/DataCleanup.vue'
        ]
    ],
    '_customerPermissions.js' => [
        'file' => 'admin/js/customerPermissions.js',
        'name' => 'customerPermissions'
    ],
    '_dateShortCuts.js' => [
        'file' => 'admin/js/dateShortCuts.js',
        'name' => 'dateShortCuts',
        'imports' => [
            '_app.js'
        ]
    ],
    '_shortcuts.js' => [
        'file' => 'admin/js/shortcuts.js',
        'name' => 'shortcuts',
        'imports' => [
            '_app.js'
        ]
    ],
    '_vendor-echarts.js' => [
        'file' => 'admin/js/vendor-echarts.js',
        'name' => 'vendor-echarts'
    ],
    '_vuedraggable.umd.js' => [
        'file' => 'admin/js/vuedraggable.umd.js',
        'name' => 'vuedraggable.umd',
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Components/Setup.vue' => [
        'file' => 'admin/js/Components/Setup.js',
        'name' => 'Setup',
        'src' => 'resources/admin/Components/Setup.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/ActivityLogger/Activity.vue' => [
        'file' => 'admin/js/Modules/ActivityLogger/Activity.js',
        'name' => 'Activity',
        'src' => 'resources/admin/Modules/ActivityLogger/Activity.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_dateShortCuts.js',
            '_TableSkeleton.js'
        ]
    ],
    'resources/admin/Modules/Customers/CustomerPage.vue' => [
        'file' => 'admin/js/Modules/Customers/CustomerPage.js',
        'name' => 'CustomerPage',
        'src' => 'resources/admin/Modules/Customers/CustomerPage.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '__CustomerForm.js',
            '_TableEmptyStateImage.js',
            '_TableSkeleton.js',
            '__StructuredWidget.js',
            '_customerPermissions.js'
        ]
    ],
    'resources/admin/Modules/Customers/Customers.vue' => [
        'file' => 'admin/js/Modules/Customers/Customers.js',
        'name' => 'Customers',
        'src' => 'resources/admin/Modules/Customers/Customers.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '__CustomerForm.js',
            '_TableEmptyStateImage.js',
            '_TableSkeleton.js',
            '_customerPermissions.js'
        ]
    ],
    'resources/admin/Modules/Dashboard/Dashboard.vue' => [
        'file' => 'admin/js/Modules/Dashboard/Dashboard.js',
        'name' => 'Dashboard',
        'src' => 'resources/admin/Modules/Dashboard/Dashboard.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_vuedraggable.umd.js',
            '__Tags.js'
        ]
    ],
    'resources/admin/Modules/MailBoxes/BoxEmailSettings.vue' => [
        'file' => 'admin/js/Modules/MailBoxes/BoxEmailSettings.js',
        'name' => 'BoxEmailSettings',
        'src' => 'resources/admin/Modules/MailBoxes/BoxEmailSettings.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_Modal.js',
            '_TableSkeleton.js'
        ]
    ],
    'resources/admin/Modules/MailBoxes/ChooseMailBox.vue' => [
        'file' => 'admin/js/Modules/MailBoxes/ChooseMailBox.js',
        'name' => 'ChooseMailBox',
        'src' => 'resources/admin/Modules/MailBoxes/ChooseMailBox.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_RemoteSelector.js',
            '_NarrowPromo.js'
        ]
    ],
    'resources/admin/Modules/MailBoxes/MailBoxEmailPiping.vue' => [
        'file' => 'admin/js/Modules/MailBoxes/MailBoxEmailPiping.js',
        'name' => 'MailBoxEmailPiping',
        'src' => 'resources/admin/Modules/MailBoxes/MailBoxEmailPiping.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/MailBoxes/MailBoxRoot.vue' => [
        'file' => 'admin/js/Modules/MailBoxes/MailBoxRoot.js',
        'name' => 'MailBoxRoot',
        'src' => 'resources/admin/Modules/MailBoxes/MailBoxRoot.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/MailBoxes/MailBoxSettings.vue' => [
        'file' => 'admin/js/Modules/MailBoxes/MailBoxSettings.js',
        'name' => 'MailBoxSettings',
        'src' => 'resources/admin/Modules/MailBoxes/MailBoxSettings.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Notifications/NotificationsPage.vue' => [
        'file' => 'admin/js/Modules/Notifications/NotificationsPage.js',
        'name' => 'NotificationsPage',
        'src' => 'resources/admin/Modules/Notifications/NotificationsPage.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Reports/Report.vue' => [
        'file' => 'admin/js/Modules/Reports/Report.js',
        'name' => 'Report',
        'src' => 'resources/admin/Modules/Reports/Report.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_vendor-echarts.js',
            '_TableSkeleton.js',
            '_dateShortCuts.js',
            '_NarrowPromo.js',
            '_Modal.js'
        ]
    ],
    'resources/admin/Modules/SavedReplies/Replies.vue' => [
        'file' => 'admin/js/Modules/SavedReplies/Replies.js',
        'name' => 'Replies',
        'src' => 'resources/admin/Modules/SavedReplies/Replies.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_TableMoreActions.js',
            '_ProShowcase.js',
            '_TableSkeleton.js'
        ]
    ],
    'resources/admin/Modules/Settings/AIIntegration.vue' => [
        'file' => 'admin/js/Modules/Settings/AIIntegration.js',
        'name' => 'AIIntegration',
        'src' => 'resources/admin/Modules/Settings/AIIntegration.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_NarrowPromo.js'
        ]
    ],
    'resources/admin/Modules/Settings/AutoCloseSettings.vue' => [
        'file' => 'admin/js/Modules/Settings/AutoCloseSettings.js',
        'name' => 'AutoCloseSettings',
        'src' => 'resources/admin/Modules/Settings/AutoCloseSettings.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_ProShowcase.js'
        ]
    ],
    'resources/admin/Modules/Settings/BusinessSettings.vue' => [
        'file' => 'admin/js/Modules/Settings/BusinessSettings.js',
        'name' => 'BusinessSettings',
        'src' => 'resources/admin/Modules/Settings/BusinessSettings.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/CustomFields.vue' => [
        'file' => 'admin/js/Modules/Settings/CustomFields.js',
        'name' => 'CustomFields',
        'src' => 'resources/admin/Modules/Settings/CustomFields.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_TableMoreActions.js',
            '_TableEmptyStateImage.js',
            '_ProShowcase.js',
            '_TableSkeleton.js',
            '_vuedraggable.umd.js'
        ]
    ],
    'resources/admin/Modules/Settings/FileUploadSettings/UploadIntegrationView.vue' => [
        'file' => 'admin/js/Modules/Settings/FileUploadSettings/UploadIntegrationView.js',
        'name' => 'UploadIntegrationView',
        'src' => 'resources/admin/Modules/Settings/FileUploadSettings/UploadIntegrationView.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/FluentCRMIntegration.vue' => [
        'file' => 'admin/js/Modules/Settings/FluentCRMIntegration.js',
        'name' => 'FluentCRMIntegration',
        'src' => 'resources/admin/Modules/Settings/FluentCRMIntegration.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_NarrowPromo.js'
        ]
    ],
    'resources/admin/Modules/Settings/IncomingWebhook.vue' => [
        'file' => 'admin/js/Modules/Settings/IncomingWebhook.js',
        'name' => 'IncomingWebhook',
        'src' => 'resources/admin/Modules/Settings/IncomingWebhook.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_ProShowcase.js',
            '_TableSkeleton.js'
        ]
    ],
    'resources/admin/Modules/Settings/IntegrationStatuses.vue' => [
        'file' => 'admin/js/Modules/Settings/IntegrationStatuses.js',
        'name' => 'IntegrationStatuses',
        'src' => 'resources/admin/Modules/Settings/IntegrationStatuses.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/IntegrationView.vue' => [
        'file' => 'admin/js/Modules/Settings/IntegrationView.js',
        'name' => 'IntegrationView',
        'src' => 'resources/admin/Modules/Settings/IntegrationView.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_ProShowcase.js'
        ]
    ],
    'resources/admin/Modules/Settings/LicenseManagement.vue' => [
        'file' => 'admin/js/Modules/Settings/LicenseManagement.js',
        'name' => 'LicenseManagement',
        'src' => 'resources/admin/Modules/Settings/LicenseManagement.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/McpIntegration.vue' => [
        'file' => 'admin/js/Modules/Settings/McpIntegration.js',
        'name' => 'McpIntegration',
        'src' => 'resources/admin/Modules/Settings/McpIntegration.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/Products.vue' => [
        'file' => 'admin/js/Modules/Settings/Products.js',
        'name' => 'Products',
        'src' => 'resources/admin/Modules/Settings/Products.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_TableMoreActions.js',
            '_TableEmptyStateImage.js',
            '_TableSkeleton.js'
        ]
    ],
    'resources/admin/Modules/Settings/RecaptchaView.vue' => [
        'file' => 'admin/js/Modules/Settings/RecaptchaView.js',
        'name' => 'RecaptchaView',
        'src' => 'resources/admin/Modules/Settings/RecaptchaView.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/RemotePortal.vue' => [
        'file' => 'admin/js/Modules/Settings/RemotePortal.js',
        'name' => 'RemotePortal',
        'src' => 'resources/admin/Modules/Settings/RemotePortal.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_ProShowcase.js'
        ]
    ],
    'resources/admin/Modules/Settings/SettingsView.vue' => [
        'file' => 'admin/js/Modules/Settings/SettingsView.js',
        'name' => 'SettingsView',
        'src' => 'resources/admin/Modules/Settings/SettingsView.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/SupportStaffs.vue' => [
        'file' => 'admin/js/Modules/Settings/SupportStaffs.js',
        'name' => 'SupportStaffs',
        'src' => 'resources/admin/Modules/Settings/SupportStaffs.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_TableMoreActions.js',
            '_TableEmptyStateImage.js',
            '_TableSkeleton.js'
        ]
    ],
    'resources/admin/Modules/Settings/SystemTools/CronMonitor.vue' => [
        'file' => 'admin/js/Modules/Settings/SystemTools/CronMonitor.js',
        'name' => 'CronMonitor',
        'src' => 'resources/admin/Modules/Settings/SystemTools/CronMonitor.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/SystemTools/DataCleanup.vue' => [
        'file' => 'admin/js/Modules/Settings/SystemTools/DataCleanup.js',
        'name' => 'DataCleanup',
        'src' => 'resources/admin/Modules/Settings/SystemTools/DataCleanup.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/SystemTools/DatabaseHealth.vue' => [
        'file' => 'admin/js/Modules/Settings/SystemTools/DatabaseHealth.js',
        'name' => 'DatabaseHealth',
        'src' => 'resources/admin/Modules/Settings/SystemTools/DatabaseHealth.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/TicketFormConfig.vue' => [
        'file' => 'admin/js/Modules/Settings/TicketFormConfig.js',
        'name' => 'TicketFormConfig',
        'src' => 'resources/admin/Modules/Settings/TicketFormConfig.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_ProShowcase.js'
        ]
    ],
    'resources/admin/Modules/Settings/TicketImporter/HelpScout/HelpScoutAuthorization.vue' => [
        'file' => 'admin/js/Modules/Settings/TicketImporter/HelpScout/HelpScoutAuthorization.js',
        'name' => 'HelpScoutAuthorization',
        'src' => 'resources/admin/Modules/Settings/TicketImporter/HelpScout/HelpScoutAuthorization.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/TicketImporter/TicketImporter.vue' => [
        'file' => 'admin/js/Modules/Settings/TicketImporter/TicketImporter.js',
        'name' => 'TicketImporter',
        'src' => 'resources/admin/Modules/Settings/TicketImporter/TicketImporter.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/admin/Modules/Settings/TicketTags.vue' => [
        'file' => 'admin/js/Modules/Settings/TicketTags.js',
        'name' => 'TicketTags',
        'src' => 'resources/admin/Modules/Settings/TicketTags.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_TableMoreActions.js',
            '_TableEmptyStateImage.js',
            '_ProShowcase.js',
            '_TableSkeleton.js'
        ]
    ],
    'resources/admin/Modules/Tickets/AllTickets.vue' => [
        'file' => 'admin/js/Modules/Tickets/AllTickets.js',
        'name' => 'AllTickets',
        'src' => 'resources/admin/Modules/Tickets/AllTickets.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_RemoteSelector.js',
            '__ScheduledReplyBadge.js',
            '_shortcuts.js',
            '_ProShowcase.js',
            '__Tags.js',
            '_TableSkeleton.js',
            '_NarrowPromo.js'
        ]
    ],
    'resources/admin/Modules/Tickets/TicketsView.vue' => [
        'file' => 'admin/js/Modules/Tickets/TicketsView.js',
        'name' => 'TicketsView',
        'src' => 'resources/admin/Modules/Tickets/TicketsView.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_shortcuts.js'
        ]
    ],
    'resources/admin/Modules/Tickets/ViewTicket.vue' => [
        'file' => 'admin/js/Modules/Tickets/ViewTicket.js',
        'name' => 'ViewTicket',
        'src' => 'resources/admin/Modules/Tickets/ViewTicket.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_shortcuts.js',
            '__ScheduledReplyBadge.js',
            '__CustomerForm.js',
            '_RemoteSelector.js',
            '__StructuredWidget.js',
            '__Tags.js',
            '_TableSkeleton.js',
            '_NarrowPromo.js'
        ]
    ],
    'resources/admin/Modules/Workflows/AllWorkflows.vue' => [
        'file' => 'admin/js/Modules/Workflows/AllWorkflows.js',
        'name' => 'AllWorkflows',
        'src' => 'resources/admin/Modules/Workflows/AllWorkflows.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_TableMoreActions.js',
            '_ProShowcase.js',
            '_TableSkeleton.js'
        ]
    ],
    'resources/admin/Modules/Workflows/EditWorkFlow.vue' => [
        'file' => 'admin/js/Modules/Workflows/EditWorkFlow.js',
        'name' => 'EditWorkFlow',
        'src' => 'resources/admin/Modules/Workflows/EditWorkFlow.vue',
        'isDynamicEntry' => true,
        'imports' => [
            '_app.js',
            '_vuedraggable.umd.js'
        ]
    ],
    'resources/admin/global_admin.js' => [
        'file' => 'admin/js/global_admin.js',
        'name' => 'global_admin',
        'src' => 'resources/admin/global_admin.js',
        'isEntry' => true
    ],
    'resources/admin/global_summary.js' => [
        'file' => 'admin/js/global_summary.js',
        'name' => 'global_summary',
        'src' => 'resources/admin/global_summary.js',
        'isEntry' => true
    ],
    'resources/admin/start.js' => [
        'file' => 'admin/js/start.js',
        'name' => 'start',
        'src' => 'resources/admin/start.js',
        'isEntry' => true,
        'imports' => [
            '_app.js'
        ]
    ],
    'resources/customer_portal/login_helper.js' => [
        'file' => 'portal/js/login_helper.js',
        'name' => 'login_helper',
        'src' => 'resources/customer_portal/login_helper.js',
        'isEntry' => true
    ],
    'resources/scss/all_public.scss' => [
        'file' => 'admin/css/all_public.css',
        'src' => 'resources/scss/all_public.scss',
        'isEntry' => true
    ],
    'resources/scss/alpha-admin.scss' => [
        'file' => 'admin/css/alpha-admin.css',
        'src' => 'resources/scss/alpha-admin.scss',
        'isEntry' => true
    ],
    'resources/customer_portal/portal.js' => [
        'file' => 'portal/js/app.js',
        'name' => 'portal',
        'src' => 'resources/customer_portal/portal.js',
        'isEntry' => true
    ],
    'style.css' => [
        'file' => 'portal/css/app.css',
        'src' => 'style.css'
    ]
];