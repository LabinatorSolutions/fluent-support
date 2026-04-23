import './Bits/Theme';
import routes from './routes';
import { createWebHashHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia';
import FluentFramework from './Bits/FluentFramework';
import ColorMode from './Pieces/ColorMode.vue';

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

const framerwork = new FluentFramework();

framerwork.app.component('color-mode', ColorMode);

framerwork.app.config.globalProperties.appVars = window.fluentSupportAdmin;
framerwork.app.config.globalProperties.has_pro = window.fluentSupportAdmin.has_pro;

// Expose hooks for third-party JS widget registration
if (window.fluentSupportAdmin) {
    window.fluentSupportAdmin.hooks = {
        addFilter: framerwork.addFilter,
        applyFilters: framerwork.applyFilters,
        addAction: framerwork.addAction,
        doAction: framerwork.doAction,
        removeAllActions: framerwork.removeAllActions,
    };
}

framerwork.app.config.globalProperties.is_mobile = window.innerWidth < 769;


const pinia = createPinia();

window.fluentSupportAppp = framerwork.app.use(pinia).use(router).mount('#alpha_app');

// Scroll effect on header for mobile
function initScrollHeader() {
    const header = document.querySelector('.fs_main_navbar');
    if (!header) {
        return;
    }
    
    const handleScroll = () => {
        const navbar = document.querySelector('.fs_main_navbar');
        const mainAppWrapper = document.querySelector('#alpha_app');

        if (mainAppWrapper) {
            const scrollTop = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
            if (scrollTop > 10) {
                mainAppWrapper.classList.add('fs-scroll-header');
            } else {
                mainAppWrapper.classList.remove('fs-scroll-header');
            }
        }
    };
    
    window.addEventListener('scroll', handleScroll, { passive: true });
    
    // Check initial scroll position
    handleScroll();
}

// Initialize scroll header after app is mounted
setTimeout(() => {
    initScrollHeader();
}, 100);

// Function to update active navbar item
function updateActiveNavItem(route) {
    // Remove current class from all navbar items
    jQuery('.fs_nav_item').removeClass('current');
    jQuery('.fframe_menu_item').removeClass('fs_active');
    
    let active = route.meta?.active;
    if(active) {
        // Add current class to the active navbar item
        jQuery('.fs_nav_item[data-key='+active+']').addClass('current');
        jQuery('.fframe_main-menu-items').find('li[data-key='+active+']').addClass('fs_active');
    }
}

router.afterEach((to, from) => {
    updateActiveNavItem(to);
});

// Handle initial route on page load
router.isReady().then(() => {
    updateActiveNavItem(router.currentRoute.value);
});

if(window.fluentSupportAdmin.is_frontend) {
    jQuery('body').addClass('has_fluent_support');
}

setInterval(() => {
    window.fluentSupportAppp.$get('tickets/ping');
}, 30000);

if (window.location.hash.includes('setup')) {
    jQuery('.fframe_main-menu-items').hide();
}
