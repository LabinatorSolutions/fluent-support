// export function createRouter(routes) {
//     const parsePath = (path) => {
//         const parts = path.split('/').filter(Boolean);
//         return parts.map((part) => (part.startsWith(':') ? { param: part.slice(1) } : { static: part }));
//     };
//
//     const matchRoute = (path) => {
//         const currentParts = parsePath(window.location.hash.slice(1) || '/');
//         for (const route of routes) {
//             const routeParts = parsePath(route.path);
//             if (routeParts.length !== currentParts.length) continue;
//
//             const params = {};
//             const matched = routeParts.every((part, index) => {
//                 if (part.static) return part.static === currentParts[index];
//                 params[part.param] = currentParts[index];
//                 return true;
//             });
//
//             if (matched) {
//                 return { ...route, params };
//             }
//         }
//         return null; // No route matched
//     };
//
//     const navigate = (path) => {
//         window.location.hash = path; // Change the URL hash to navigate
//     };
//
//     const listen = (callback) => {
//         const render = () => {
//             const route = matchRoute(window.location.hash.slice(1) || '/'); // Get current route
//             if (route) {
//                 const componentInstance = new route.component(route.params); // Create instance of the component
//                 callback(componentInstance); // Pass the component instance to the callback
//             }
//         };
//         window.addEventListener('hashchange', render); // Listen for hash changes
//         render(); // Initial route render
//     };
//
//     return { navigate, listen };
// }





import { DashboardComponent } from './components/DashboardComponent.js';
import { TicketDetailsComponent } from './components/TicketDetailsComponent.js';
import { CreateTicketComponent } from './components/CreateTicketComponent.js';

export const router = {
    init() {
        window.addEventListener('hashchange', this.handleRouteChange.bind(this));
        this.handleRouteChange(); // Trigger initial route load
    },

    async handleRouteChange() {
        const hash = window.location.hash.slice(1) || '/';
        const container = document.getElementById('fluent_support_client_app');

        if (container) {
            let component = null;

            if (hash === '/') {
                component = await DashboardComponent();
            } else if (hash === '/ticket/create') {
                component = await CreateTicketComponent();
            } else if (hash.startsWith('/ticket/') && hash.includes('/view')) {
                // Extract ticket ID from the URL hash
                const ticketId = hash.split('/')[2];
                component = await TicketDetailsComponent(ticketId); // Pass the ticketId to the component
            } else {
                // If no route matches, display 404 page
                component = this.render404();
            }

            // Clear the container and append the new component
            container.innerHTML = '';
            if (component) {
                container.appendChild(component);
            }
        }
    },

    render404() {
        const notFound = document.createElement('div');
        notFound.innerHTML = '<h1>404 - Page Not Found</h1>';
        return notFound;
    }
};
