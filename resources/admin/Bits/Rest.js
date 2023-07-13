const request = function(method, route, data = {}) {
    const url = `${window.fluentSupportAdmin.rest.url}/${route}`;

    const headers = {'X-WP-Nonce': window.fluentSupportAdmin.rest.nonce};

    if (['PUT', 'PATCH', 'DELETE'].indexOf(method.toUpperCase()) !== -1) {
        headers['X-HTTP-Method-Override'] = method;
        method = 'POST';
    }

    return window.jQuery.ajax({
        url: url,
        type: method,
        data: data,
        headers: headers
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


jQuery(($) => {
    (() => {
        $.ajaxSetup({
            success: function (response, status, xhr) {
                const nonce = xhr.getResponseHeader('X-WP-Nonce');
                if (nonce) {
                    window.fluentSupportAdmin.rest.nonce = nonce;
                }
            },
            error: function (response, status, xhr) {
                if (response.responseJSON && response.responseJSON.code == 'rest_cookie_invalid_nonce') {
                    // Send the ajax request to get the new nonce
                    jQuery.post(window.fluentSupportAdmin.ajaxurl, {
                        action: 'fluent_support_renew_rest_nonce'
                    })
                        .then(response => {
                            window.fluentSupportAdmin.rest.nonce = response.nonce;
                        });
                }
            }
        });
    })();
});
