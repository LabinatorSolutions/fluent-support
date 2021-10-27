import routes from './routes';
import { createWebHashHistory, createRouter } from 'vue-router'
import FluentFramework from './Bits/FluentFramework';

const router = createRouter({
    history: createWebHashHistory(),
    routes
});


const framerwork = new FluentFramework();

framerwork.app.config.globalProperties.appVars = window.fluentSupportAdmin;
framerwork.app.config.globalProperties.has_pro = window.fluentSupportAdmin.has_pro;

window.fluentSupportAppp = framerwork.app.use(router).mount('#alpha_app');


router.afterEach((to, from) => {
    jQuery('.fframe_menu_item').removeClass('fs_active');
    let active = to.meta.active;
    if(active) {
        jQuery('.fframe_main-menu-items').find('li[data-key='+active+']').addClass('fs_active');
    }
});
