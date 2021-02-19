import { createApp } from 'vue';
import Application from './Application';
const app = createApp(Application);
import { createWebHashHistory, createRouter } from 'vue-router'
import routes from "./routes";

import lang from 'element-plus/lib/locale/lang/en'
import locale from 'element-plus/lib/locale'
locale.use(lang);

import {
    ElTable,
    ElTableColumn,
    ElLoading,
    ElPagination,
    ElIcon,
    ElButton,
    ElCheckbox,
    ElRadioGroup,
    ElRadioButton,
    ElInput,
    ElSelect,
    ElOption,
    ElForm,
    ElFormItem
} from 'element-plus';

const components = [
    ElTable,
    ElTableColumn,
    ElPagination,
    ElIcon,
    ElButton,
    ElCheckbox,
    ElRadioGroup,
    ElRadioButton,
    ElForm,
    ElFormItem,
    ElInput,
    ElSelect,
    ElOption,
];

components.forEach(component => {
    app.component(component.name, component)
})

app.use(ElLoading);

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

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

app.mixin({
    data() {
        return {
            appVars: window.fs_customer_portal
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
