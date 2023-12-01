import { createApp } from 'vue';
import Application from './Application';
import { createWebHashHistory, createRouter } from 'vue-router'
import routes from "./routes";

import {
    ElLoading
} from 'element-plus';

import {
    Refresh,
    Paperclip,
    ArrowUpBold,
    ArrowDownBold

} from '@element-plus/icons-vue/dist';

const app = createApp(Application);

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

const components = [
    // Icon component
    Refresh,
    Paperclip,
    ArrowUpBold,
    ArrowDownBold
];

components.forEach(component => {
    app.component(component.name, component)
})

app.use(ElLoading);

const request = function(method, route, data = {}) {
    const url = `${window.fs_customer_portal.rest.url}/${route}`;

    return window.jQuery.ajax({
        url: url,
        type: method,
        data: data,
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', window.fs_customer_portal.rest.nonce);
        }
    });
};

app.config.globalProperties.appVars = window.fs_customer_portal;

app.mixin({
    data() {
        return {
        }
    },
    methods: {
        $get(route, data) {
            return request('GET', route, data)
        },
        $post(route, data) {
            return request('POST', route, data)
        },
        $del(route, data) {
            return request('DELETE', route, data);
        },
        $put(route, data) {
            return request('PUT', route, data);
        },
        $patch(route, data) {
            return request('PATCH', route, data);
        },
        convertToText(obj) {
            const string = [];
            if (typeof (obj) === 'object' && (obj.join === undefined)) {
                for (const prop in obj) {
                    string.push(this.convertToText(obj[prop]));
                }
            } else if (typeof (obj) === 'object' && !(obj.join === undefined)) {
                for (const prop in obj) {
                    string.push(this.convertToText(obj[prop]));
                }
            } else if (typeof (obj) === 'function') {

            } else if (typeof (obj) === 'string') {
                string.push(obj)
            }
            return string.join('<br />')
        },
        $t(string) {
            return window.window.fs_customer_portal.i18n[string] || string;
        }
    }
});

app.use(router).mount('#fluent_support_client_app');

jQuery(document).ajaxSuccess((event, xhr, settings) => {
    const nonce = xhr.getResponseHeader('X-WP-Nonce');
    if (nonce) {
        window.fs_customer_portal.rest.nonce = nonce;
    }
});
