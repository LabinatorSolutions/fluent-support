
export const request = (method, route, data = {}) => {
    const url = `${window.fs_customer_portal.rest.url}/${route}`;
    return window.jQuery.ajax({
        url: url,
        type: method,
        data: data,
        beforeSend: xhr => {
            xhr.setRequestHeader('X-WP-Nonce', window.fs_customer_portal.rest.nonce);
        },
    });
};

export const $get = (route, data) => request('GET', route, data);
export const $post = (route, data) => request('POST', route, data);
export const $del = (route, data) => request('DELETE', route, data);
export const $put = (route, data) => request('PUT', route, data);
export const $patch = (route, data) => request('PATCH', route, data);
export const $t = string => window.fs_customer_portal.i18n[string] || string;
