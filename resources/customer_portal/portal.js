import { createApp } from 'vue';
import Application from './Application';
import { createWebHashHistory, createRouter } from 'vue-router'
import routes from "./routes";
import {ElNotification} from 'element-plus'
import Rest from './Rest.js';
import { $t, $_n, $_x } from './i18n.js';

import {
    Paperclip,
    Delete,
    Loading,
    CircleCloseFilled,
    CircleCheckFilled
} from '@element-plus/icons-vue';

const app = createApp(Application);

app.config.globalProperties.$notify = ElNotification;

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

const components = [
    // Icon component
    Paperclip,
    Delete,
    Loading,
    CircleCloseFilled,
    CircleCheckFilled
];

components.forEach(component => {
    app.component(component.name, component)
})

app.config.globalProperties.appVars = window.fs_customer_portal;

app.mixin({
    data() {
        return {
        }
    },
    methods: {
        $get: Rest.get,
        $post: Rest.post,
        $del: Rest.delete,
        $put: Rest.put,
        $patch: Rest.patch,
        $uploadFile: Rest.uploadFile,
        $handleError(response) {
            let errorMessage = '';
            if (typeof response === 'string') {
                errorMessage = response;
            } else if (response && response.message) {
                errorMessage = response.message;
            } else {
                errorMessage = this.convertToText(response);
            }
            if (!errorMessage) {
                errorMessage = 'Something is wrong!';
            }

            if (typeof errorMessage != 'string') {
                errorMessage = this.convertToText(errorMessage);
            }

            this.$notify({
                type: 'error',
                title: 'Error',
                message: errorMessage,
                dangerouslyUseHTMLString: false
            });
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
        $t,
        $_n,
        $_x
    }
});


const mountApp = () => {
    const el = document.getElementById('fluent_support_client_app');
    if (!el) return;
    app.use(router).mount(el);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountApp);
} else {
    mountApp();
}

jQuery(document).ajaxSuccess((event, xhr, settings) => {
    const nonce = xhr.getResponseHeader('X-WP-Nonce');
    if (nonce) {
        window.fs_customer_portal.rest.nonce = nonce;
    }
});
