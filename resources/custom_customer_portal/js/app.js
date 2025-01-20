// import { createRouter } from './router.js';
// import Dashboard from './components/Dashboard.js';
// import Ticket from './components/Ticket.js';
// import CreateTicket from './components/CreateTicket.js';
// import { request } from './utils/api.js';
//
// // Initialize Router
// const router = createRouter([
//     { path: '/', component: Dashboard },
//     { path: '/ticket/:ticket_id/view', component: Ticket },
//     { path: '/ticket/create', component: CreateTicket },
// ]);
//
// // Set global variables
// const appVars = window.fs_customer_portal || {};
//
// // Application logic
// document.addEventListener('DOMContentLoaded', () => {
//     const app = document.getElementById('fluent_support_client_app');
//     if (!app) return;
//
//     // Render current route component
//     router.listen((component) => {
//         app.innerHTML = ''; // Clear previous content
//         app.appendChild(component.render()); // Render the new component
//         if (component.mounted) component.mounted(); // Call mounted lifecycle if present
//     });
//
//     // Global utilities
//     window.$t = (string) => appVars.i18n[string] || string;
//     window.api = request;
// });


import { router } from './router.js';
import { bindGlobalListeners, globalMethods } from './utils/request.js';

// Application initialization
const app = {
    vars: window.fs_customer_portal || {},
    init() {
        this.setupRouter();
        this.setupGlobalMethods();
        bindGlobalListeners(this);
    },
    setupRouter() {
        router.init();
    },
    setupGlobalMethods() {
        // Add global methods to app
        Object.assign(this, globalMethods);
    },
};

// Start the application
app.init();


