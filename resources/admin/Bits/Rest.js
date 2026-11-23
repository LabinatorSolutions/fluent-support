import {isSafeRestUrl, appendQuery, INVALID_ROUTE_ERROR} from '../../common/restUrlGuard.js';

/**
 * The agent app's REST helper. It uses XMLHttpRequest and parses every response with JSON.parse,
 * so a response can never run as script (no JSONP).
 *
 * Request bodies stay form-encoded, built the way jQuery.param built them, so PHP reads the same
 * $_POST values as before ("true"/"false" strings, empty arrays left out).
 */

// Port of jQuery 3 jQuery.param (traditional: false)
function buildParams(prefix, obj, add) {
    if (Array.isArray(obj)) {
        obj.forEach((value, index) => {
            if (/\[\]$/.test(prefix)) {
                add(prefix, value);
            } else {
                buildParams(prefix + '[' + (typeof value === 'object' && value != null ? index : '') + ']', value, add);
            }
        });
    } else if (obj !== null && typeof obj === 'object' && !(obj instanceof Date) && !(obj instanceof RegExp) && !(obj instanceof Error)) {
        Object.keys(obj).forEach(name => buildParams(prefix + '[' + name + ']', obj[name], add));
    } else {
        add(prefix, obj);
    }
}

export function toQueryString(data) {
    if (data === null || data === undefined) {
        return '';
    }
    if (typeof data === 'string') {
        return data;
    }
    const parts = [];
    const add = (key, value) => {
        value = typeof value === 'function' ? value() : value;
        parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(value == null ? '' : value));
    };
    Object.keys(data).forEach(key => buildParams(key, data[key], add));
    return parts.join('&');
}

/**
 * A native Promise that also keeps the jqXHR methods the app (and add-ons) chain.
 *
 * On the request itself, done/fail/always behave as in jQuery: they run synchronously when the
 * request settles, in the order they were added and before any then() callback, and a callback
 * added after it settled runs at once. _CreateResponse.vue relies on this order.
 * On a promise from then()/catch() they run as then() callbacks.
 * They return the same promise. abort() works on any promise in the chain.
 */
export class RestRequest extends Promise {
    then(onFulfilled, onRejected) {
        const next = super.then(onFulfilled, onRejected);
        next._xhr = this._xhr;
        return next;
    }

    _on(type, callback) {
        if (typeof callback !== 'function') {
            return this;
        }
        const sync = this._sync;
        if (!sync) {
            if (type === 'done') {
                this.then(callback, () => {});
            } else if (type === 'fail') {
                this.then(null, callback);
            } else {
                this.then(callback, callback);
            }
            return this;
        }
        // jQuery never reported a failed request as an unhandled rejection; a chained done/fail/always keeps that
        if (!sync.handled) {
            sync.handled = true;
            Promise.prototype.then.call(this, undefined, () => {});
        }
        const wanted = type === 'always' || (type === 'done') === sync.ok;
        if (sync.settled) {
            if (wanted) {
                callback(sync.value);
            }
        } else {
            sync.callbacks.push({type, callback});
        }
        return this;
    }

    done(callback) {
        return this._on('done', callback);
    }

    fail(callback) {
        return this._on('fail', callback);
    }

    always(callback) {
        return this._on('always', callback);
    }

    abort() {
        if (this._xhr) {
            this._xhr.abort();
        }
        return this;
    }
}

// Runs the request's done/fail/always callbacks, then settles the promise
function settle(sync, ok, value, resolve, reject) {
    if (sync.settled) {
        return;
    }
    sync.settled = true;
    sync.ok = ok;
    sync.value = value;
    sync.callbacks.forEach(({type, callback}) => {
        if (type === 'always' || (type === 'done') === ok) {
            try {
                callback(value);
            } catch (e) {
                // like a throwing event handler: report it, still settle the request
                setTimeout(() => {
                    throw e;
                });
            }
        }
    });
    sync.callbacks = [];
    if (ok) {
        resolve(value);
    } else {
        reject(value);
    }
}

// Old callers read error.responseJSON (jqXHR style). Non-enumerable, so it never loops in a serialiser.
function asError(error, status) {
    if (error && typeof error === 'object') {
        if (status !== undefined && error.xhr_status === undefined) {
            error.xhr_status = status;
        }
        if (!('responseJSON' in error)) {
            Object.defineProperty(error, 'responseJSON', {value: error, enumerable: false});
        }
    }
    return error;
}

// Fixed text: the route comes from the page URL, and error notices may render HTML
function invalidRoute() {
    const error = asError(Object.assign({}, INVALID_ROUTE_ERROR), 0);
    const promise = RestRequest.reject(error);
    promise._sync = {settled: true, ok: false, value: error, callbacks: []};
    return promise;
}

function renewNonce() {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.fluentSupportAdmin.ajaxurl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.onload = () => {
        try {
            const response = JSON.parse(xhr.responseText);
            if (response && response.nonce) {
                window.fluentSupportAdmin.rest.nonce = response.nonce;
            }
        } catch (e) {
            // keep the old nonce
        }
    };
    xhr.send(toQueryString({action: 'fluent_support_renew_rest_nonce'}));
}

function send(method, url, body, headers) {
    let xhr;
    const sync = {settled: false, ok: false, value: undefined, callbacks: []};
    const promise = new RestRequest((resolvePromise, rejectPromise) => {
        const resolve = (value) => settle(sync, true, value, resolvePromise, rejectPromise);
        const reject = (value) => settle(sync, false, value, resolvePromise, rejectPromise);
        xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        Object.keys(headers).forEach(key => xhr.setRequestHeader(key, headers[key]));

        xhr.onload = function () {
            const nonce = xhr.getResponseHeader('X-WP-Nonce');
            if (nonce) {
                window.fluentSupportAdmin.rest.nonce = nonce;
            }

            const text = xhr.responseText;
            let response = null;
            let parsed = true;
            if (text !== '') {
                try {
                    response = JSON.parse(text);
                } catch (e) {
                    parsed = false;
                }
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                if (!parsed) {
                    reject(asError({message: 'The server returned an unexpected response.', status: xhr.status}, xhr.status));
                    return;
                }
                resolve(response);
                return;
            }

            if (parsed && response && typeof response === 'object') {
                if (response.code === 'rest_cookie_invalid_nonce') {
                    renewNonce();
                }
                reject(asError(response, xhr.status));
                return;
            }

            console.info('Your server firewall blocked the request or it\'s a plugin conflict. Please check the detailed error.');
            console.log({status: xhr.status, statusText: xhr.statusText, responseText: text});
            reject(asError({message: xhr.statusText || 'Request failed. Please try again.', status: xhr.status}, xhr.status));
        };

        xhr.onerror = function () {
            reject(asError({message: 'Request failed. Please try again.', status: xhr.status, statusText: xhr.statusText}, xhr.status));
        };

        // $handleError ignores an aborted request, so a superseded call shows no notice
        xhr.onabort = function () {
            reject(asError({message: 'Request aborted.', statusText: 'abort', status: 0, aborted: true}, 0));
        };

        if (body === null) {
            xhr.send();
        } else {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.send(body);
        }
    });
    promise._xhr = xhr;
    promise._sync = sync;
    return promise;
}

const request = function (method, route, data = {}, baseURL = null) {
    const base = baseURL || window.fluentSupportAdmin.rest.url;
    let url = `${base}/${route}`;

    const headers = {
        'X-WP-Nonce': window.fluentSupportAdmin.rest.nonce,
        // jQuery sent both; some firewall and security plugin rules look for them
        'Accept': 'application/json, */*;q=0.1',
        'X-Requested-With': 'XMLHttpRequest'
    };

    if (['PUT', 'PATCH', 'DELETE'].indexOf(method.toUpperCase()) !== -1) {
        headers['X-HTTP-Method-Override'] = method;
        method = 'POST';
    }

    let body = null;
    if (method.toUpperCase() === 'GET') {
        url = appendQuery(url, toQueryString(data));
    } else {
        body = toQueryString(data);
    }

    // Checked on the final URL, after the GET data is added
    if (!isSafeRestUrl(url, base)) {
        return invalidRoute();
    }

    return send(method, url, body, headers);
};

export default {
    get(route, data = {}, baseURL = null) {
        return request('GET', route, data, baseURL);
    },
    post(route, data = {}, baseURL = null) {
        return request('POST', route, data, baseURL);
    },
    delete(route, data = {}, baseURL = null) {
        return request('DELETE', route, data, baseURL);
    },
    put(route, data = {}, baseURL = null) {
        return request('PUT', route, data, baseURL);
    },
    patch(route, data = {}, baseURL = null) {
        return request('PATCH', route, data, baseURL);
    }
};
