export default {
    get(route, data = {}) {
        return window.FluentFramework.request('GET', route, data);
    },
    post(route, data = {}) {
        return window.FluentFramework.request('POST', route, data);
    },
    delete(route, data = {}) {
        return window.FluentFramework.request('DELETE', route, data);
    },
    put(route, data = {}) {
        return window.FluentFramework.request('PUT', route, data);
    },
    patch(route, data = {}) {
        return window.FluentFramework.request('PATCH', route, data);
    }
};

jQuery(document).ajaxSuccess((event, xhr, settings) => {
    const nonce = xhr.getResponseHeader('X-WP-Nonce');
    if (nonce) {
        window.fluentSupportAdmin.rest.nonce = nonce;
    }
});
