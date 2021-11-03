import { createApp } from 'vue';
import Application from './Application';
import { createWebHashHistory, createRouter } from 'vue-router'
import routes from "./routes";

import  ElUpload from "element-plus/lib/el-upload";
import  ElLoading from "element-plus/lib/el-loading";
import  ElSkeleton from "element-plus/lib/el-skeleton";
import  ElButton from "element-plus/lib/el-button";
import  ElCheckbox from "element-plus/lib/el-checkbox";
import  ElForm from "element-plus/lib/el-form";
import  ElFormItem from "element-plus/lib/el-form-item";
import  ElInput from "element-plus/lib/el-input";
import  ElSelect from "element-plus/lib/el-select";
import  ElOption from "element-plus/lib/el-option";

const app = createApp(Application);

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

app.component(ElUpload.name, ElUpload)
app.component(ElSkeleton.name, ElSkeleton)
app.component(ElButton.name, ElButton)
app.component(ElCheckbox.name, ElCheckbox)
app.component(ElForm.name, ElForm)
app.component(ElFormItem.name, ElFormItem)
app.component(ElInput.name, ElInput)
app.component(ElSelect.name, ElSelect)
app.component(ElOption.name, ElOption)

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
