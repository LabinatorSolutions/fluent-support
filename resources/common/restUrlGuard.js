/**
 * Ported from FluentCommunity src/Bits/Rest.js (PR #1440). Keep the two in step.
 *
 * Route params come from the page URL, so a crafted link reaches the REST helpers.
 * A safe URL stays inside the plugin's REST base and has no param that changes the method or the route.
 */

// Params that change how WordPress REST handles a request. A GET with ?_method=DELETE runs as a DELETE.
const OVERRIDE_PARAMS = ['_method', '_jsonp', '_envelope'];

// A fixed message: error notices can render HTML, so the route (from the page URL) must not appear in it
export const INVALID_ROUTE_ERROR = Object.freeze({message: 'Invalid REST route.', code: 'fst_invalid_rest_route'});

/**
 * The name PHP gives a query key ($_GET), as php_register_variable does:
 * - it ends the key at a null byte and drops leading spaces;
 * - "a[x]" is the array "a", and "." or " " in "a" reads as "_";
 * - when the first "[" has no "]" after it, the key is not an array: every " ", "." and "["
 *   reads as "_" ("rest[route" is "rest_route").
 * Checked against PHP 8.2 and 8.4 parse_str on 4,000 generated keys.
 */
export function phpQueryKey(key) {
    key = key.split('\0')[0].replace(/^ +/, '');
    const bracket = key.indexOf('[');
    if (bracket !== -1 && key.indexOf(']', bracket) !== -1) {
        return key.slice(0, bracket).replace(/[ .]/g, '_');
    }
    return key.replace(/[ .[]/g, '_');
}

const bases = {};

// Parsed once per base, then reused for every request
function getRestBase(raw) {
    if (!bases[raw]) {
        const url = new URL(raw, window.location.href);
        const route = url.searchParams.get('rest_route'); // set on sites with plain permalinks
        bases[raw] = {
            origin: url.origin,
            pathname: url.pathname,
            plain: route !== null,
            prefix: (route !== null ? route : url.pathname).replace(/\/$/, '') + '/'
        };
    }
    return bases[raw];
}

/**
 * @param {string} fullUrl  The final URL, with the query string already added
 * @param {string} restBase The plugin's configured REST URL (rest.url)
 */
export function isSafeRestUrl(fullUrl, restBase) {
    // "#" cuts off the rest of the URL, and browsers and servers read "\" differently
    if (typeof fullUrl !== 'string' || /[#\\]/.test(fullUrl)) {
        return false;
    }

    let base, url;
    try {
        base = getRestBase(restBase);
        url = new URL(fullUrl, window.location.href);
    } catch (e) {
        return false;
    }

    let routeParams = 0;
    for (const key of url.searchParams.keys()) {
        const name = phpQueryKey(key);
        if (OVERRIDE_PARAMS.includes(name)) {
            return false;
        }
        if (name === 'rest_route') {
            routeParams++;
        }
    }

    const path = (base.plain ? url.searchParams.get('rest_route') : url.pathname) || '';

    return url.origin === base.origin
        && routeParams === (base.plain ? 1 : 0)
        && (!base.plain || url.pathname === base.pathname)
        && path.startsWith(base.prefix)
        && !path.split('/').some(part => part === '.' || part === '..');
}

export function appendQuery(url, queryString) {
    if (!queryString) {
        return url;
    }
    return url + (url.indexOf('?') !== -1 ? '&' : '?') + queryString;
}
