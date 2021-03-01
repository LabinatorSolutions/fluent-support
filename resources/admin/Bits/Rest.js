const request = function(method, route, data = {}) {
    const url = `${window.fluentSupportAdmin.rest.url}/${route}`;

    return window.jQuery.ajax({
        url: url,
        type: method,
        data: data,
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', window.fluentSupportAdmin.rest.nonce);
        }
    });
}

export default {
    get(route, data = {}) {
        return request('GET', route, data);
    },
    post(route, data = {}) {
        return request('POST', route, data);
    },
    delete(route, data = {}) {
        return request('DELETE', route, data);
    },
    put(route, data = {}) {
        return request('PUT', route, data);
    },
    patch(route, data = {}) {
        return request('PATCH', route, data);
    }
};

jQuery(document).ajaxSuccess((event, xhr, settings) => {
    const nonce = xhr.getResponseHeader('X-WP-Nonce');
    if (nonce) {
        window.fluentSupportAdmin.rest.nonce = nonce;
    }
});
