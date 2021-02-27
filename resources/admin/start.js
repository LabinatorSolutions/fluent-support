import routes from './routes';
import { createWebHashHistory, createRouter } from 'vue-router'
import FluentFramework from './Bits/FluentFramework';
const router = createRouter({
    history: createWebHashHistory(),
    routes
});

window.FluentFramework.app.$rest = window.FluentFramework.$rest;
window.FluentFramework.app.$get = window.FluentFramework.$get;
window.FluentFramework.app.$post = window.FluentFramework.$post;
window.FluentFramework.app.$del = window.FluentFramework.$del;
window.FluentFramework.app.$put = window.FluentFramework.$put;
window.FluentFramework.app.$patch = window.FluentFramework.$patch;

window.FluentFramework.app.$success = function(msg) {
    return window.FluentFramework.app.$notify.success({
        title: 'Great!',
        message: msg,
        position: 'bottom-right'
    });
};

window.FluentFramework.app.$error = function(msg) {
    return window.FluentFramework.app.$notify.error({
        title: 'Oops!',
        message: msg,
        position: 'bottom-right'
    });
};

window.FluentFramework.request = function(method, route, data = {}) {
    const url = `${window.fluentSupportAdmin.rest.url}/${route}`;

    return window.jQuery.ajax({
        url: url,
        type: method,
        data: data,
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', window.fluentSupportAdmin.rest.nonce);
        }
    });
};

const framerwork = new FluentFramework();
framerwork.app.use(router).mount('#alpha_app');


router.afterEach((to, from) => {
    jQuery('.fframe_menu_item').removeClass('fs_active');
    let active = to.meta.active;
    if(active) {
        jQuery('.fframe_main-menu-items').find('li[data-key='+active+']').addClass('fs_active');
    }
});

