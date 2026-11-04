/**
 * Customer search field keys, shared by the `/` menu and the search tips dialog.
 * Keys must match CustomerSearch::FIELDS; unknown keys are searched as plain text.
 * Ported from FluentCRM's contact search (Contacts/Searcher/searchFields.js).
 *
 * @param {Function} $t Translate function from the calling component.
 * @returns {Array<{key: string, label: string, hint: string}>}
 */
export function getSearchFields($t) {
    return [
        { key: 'email', label: $t('Email'), hint: 'acme.com' },
        { key: 'name', label: $t('Name'), hint: 'timothy cook' },
        { key: 'first', label: $t('First name'), hint: 'timothy' },
        { key: 'last', label: $t('Last name'), hint: 'cook' },
        { key: 'title', label: $t('Job Title'), hint: 'manager' },
        { key: 'address', label: $t('Address'), hint: 'main street' },
        { key: 'city', label: $t('City'), hint: '"new york"' },
        { key: 'state', label: $t('State'), hint: 'california' },
        { key: 'zip', label: $t('Postal code'), hint: '10001' },
        { key: 'country', label: $t('Country'), hint: 'US' },
        { key: 'status', label: $t('Status'), hint: 'blocked' },
        { key: 'tickets', label: $t('Tickets'), hint: '>2' },
        { key: 'open', label: $t('Open tickets'), hint: '0' },
        { key: 'created', label: $t('Created at'), hint: '2026-05' },
        { key: 'activity', label: $t('Last Activity'), hint: '<2026-01-01' },
        { key: 'user', label: $t('WordPress user ID'), hint: '42' },
        { key: 'id', label: $t('Customer IDs'), hint: '12,48' }
    ];
}

/**
 * Find the `/word` being typed right before the cursor, if any.
 * Only a `/` at the start or after whitespace counts.
 *
 * @param {string} value Full input text.
 * @param {number} caret Cursor position.
 * @returns {{query: string, start: number, end: number}|null}
 */
export function findSlashToken(value, caret) {
    const before = String(value || '').slice(0, caret);
    const match = before.match(/(^|\s)\/([^\s:"\/]*)$/u);
    if (!match) {
        return null;
    }
    const query = match[2];
    return { query, start: caret - query.length - 1, end: caret };
}

/**
 * Fields matching a `/` query: key prefix matches first, then label matches.
 *
 * @param {Array} fields From getSearchFields().
 * @param {string} query Text after the `/`.
 * @returns {Array}
 */
export function filterSearchFields(fields, query) {
    const q = String(query || '').toLowerCase();
    if (!q) {
        return fields;
    }
    const byKey = fields.filter(field => field.key.startsWith(q));
    const byLabel = fields.filter(field => !byKey.includes(field) && String(field.label).toLowerCase().includes(q));
    return byKey.concat(byLabel);
}
