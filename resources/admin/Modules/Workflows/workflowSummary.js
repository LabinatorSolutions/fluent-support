import { $t, $_n } from '@/admin/Bits/i18n';

/**
 * Plain-language text for the workflow flow builder cards: one line per
 * condition row and one detail line per action. Pure functions, so the
 * editor and the JS specs share them.
 */

const MAX_DETAIL = 80;

function shorten(text) {
    text = String(text || '').replace(/\s+/g, ' ').trim();
    return text.length > MAX_DETAIL ? text.slice(0, MAX_DETAIL - 1) + '…' : text;
}

function stripHtml(html) {
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
    return doc.body.textContent || '';
}

function asList(value) {
    if (Array.isArray(value)) {
        return value;
    }
    return value === '' || value === null || value === undefined ? [] : [value];
}

// Options come as {key: label} or [{id, title}].
function optionLabel(options, value) {
    if (Array.isArray(options)) {
        const found = options.find(option => String(option.id) === String(value));
        return found ? found.title : String(value);
    }
    if (options && Object.prototype.hasOwnProperty.call(options, value)) {
        return options[value];
    }
    return String(value);
}

function labels(options, value) {
    return asList(value).map(item => optionLabel(options, item)).join(', ');
}

/**
 * One condition row as a sentence, or '' when the row is blank or unfinished.
 *
 * @param {Object} row        {data_key, data_operator, data_value, value_1, value_2}
 * @param {Object} conditions trigger_fields.conditions from workflows/{id}
 */
export function conditionText(row, conditions) {
    const definition = row && row.data_key ? conditions[row.data_key] : null;
    if (!definition || !row.data_operator) {
        return '';
    }

    const field = definition.title;
    const operator = row.data_operator;
    const value = row.data_value;

    switch (definition.data_type) {
        case 'string':
            return operator === 'not_contains'
                /* translators: %1$s is a field name, %2$s the text it must not contain */
                ? $t('%1$s does not contain "%2$s"', field, value)
                /* translators: %1$s is a field name, %2$s the text it must contain */
                : $t('%1$s contains "%2$s"', field, value);
        case 'single_dropdown':
            return operator === 'not_equal'
                /* translators: %1$s is a field name, %2$s a value such as a priority or mailbox */
                ? $t('%1$s is not %2$s', field, optionLabel(definition.options, value))
                /* translators: %1$s is a field name, %2$s a value such as a priority or mailbox */
                : $t('%1$s is %2$s', field, optionLabel(definition.options, value));
        case 'multiple_select':
        case 'remote_select': {
            // Remote options are not loaded here, so say how many were picked.
            const picked = definition.data_type === 'remote_select'
                /* translators: %d is how many items a workflow condition lists */
                ? $_n('%d item', '%d items', asList(value).length)
                : labels(definition.options, value);
            return operator === 'not_includes_in'
                /* translators: %1$s is a field name, %2$s a list of values */
                ? $t('%1$s is none of %2$s', field, picked)
                /* translators: %1$s is a field name, %2$s a list of values */
                : $t('%1$s is any of %2$s', field, picked);
        }
        case 'yes_no':
            /* translators: %1$s is a field name, %2$s the chosen option, such as "Has an attachment" */
            return $t('%1$s: %2$s', field, optionLabel(definition.options, operator));
        case 'date_range':
        case 'time_range':
            if (operator === 'range') {
                /* translators: %1$s is a field name, %2$s and %3$s are the start and end of a date or time range */
                return $t('%1$s is between %2$s and %3$s', field, row.value_1 || '?', row.value_2 || '?');
            }
            return operator === '<'
                /* translators: %1$s is a field name, %2$s a date */
                ? $t('%1$s is before %2$s', field, value)
                /* translators: %1$s is a field name, %2$s a date */
                : $t('%1$s is after %2$s', field, value);
        default:
            return field;
    }
}

/**
 * The condition groups as lines, without blank rows and empty groups.
 *
 * @return {string[][]} one array of lines per group
 */
export function conditionGroups(groups, conditions) {
    return (groups || [])
        .map(group => (group || []).map(row => conditionText(row, conditions || {})).filter(Boolean))
        .filter(lines => lines.length);
}

/**
 * A short line saying what an action does, from its form fields and settings.
 * Returns '' when nothing is filled in yet.
 *
 * @param {Object} action     {action_name, settings}
 * @param {Object} definition action_fields[action_name]: {title, fields}
 * @param {Object} appVars    window.fluentSupportAdmin (agents and tags)
 */
export function actionDetail(action, definition, appVars) {
    if (!definition || !action || !action.settings) {
        return '';
    }

    const settings = action.settings;
    const parts = [];

    Object.keys(definition.fields || {}).forEach(key => {
        const field = definition.fields[key];
        const value = settings[key];
        if (!field || value === '' || value === null || value === undefined || (Array.isArray(value) && !value.length)) {
            return;
        }

        switch (field.type) {
            case 'agent-selectors': {
                const agents = (appVars.support_agents || []).map(agent => ({ id: agent.id, title: agent.full_name || [agent.first_name, agent.last_name].join(' ') }));
                parts.push(labels(agents.concat(field.extra_options || []), value));
                break;
            }
            case 'tag-selectors':
                parts.push(labels((appVars.ticket_tags || []).map(tag => ({ id: tag.id, title: tag.title })), value));
                break;
            case 'input-options':
            case 'checkbox-group':
                parts.push(labels(field.options, value));
                break;
            case 'input-text':
                parts.push(String(value));
                break;
            case 'wp-editor':
                parts.push(stripHtml(value));
                break;
        }
    });

    return shorten(parts.filter(Boolean).join(' · '));
}
