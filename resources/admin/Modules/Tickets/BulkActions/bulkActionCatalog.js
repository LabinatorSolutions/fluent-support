/**
 * What the ticket bulk bar offers, and the request each action sends.
 * Same shape as FluentCRM's contact bulk bar, so both products read alike.
 *
 * input:
 *   reply     opens the bulk reply dialog (no panel)
 *   agent     single choice from support agents, grouped by agent group
 *   choice    single choice from `items`
 *   terms     multiple tags, with an Add / Remove toggle
 *   workflow  single choice from manual workflows; runs through its own endpoint
 *   confirm   no field; the panel itself is the confirmation
 *   delete    like confirm, drawn as destructive
 */

const MANAGE_PERMISSIONS = ['fst_manage_own_tickets', 'fst_manage_unassigned_tickets', 'fst_manage_other_tickets'];

/**
 * @param {Object} ctx
 * @param {Function} ctx.$t
 * @param {Object} ctx.appVars
 * @returns {{primary: Array, groups: Array, destructive: Object|null}}
 */
export function buildTicketBulkCatalog({ $t, appVars }) {
    const vars = appVars || {};
    const permissions = (vars.me && vars.me.permissions) || [];
    const canManage = permissions.some(permission => MANAGE_PERMISSIONS.includes(permission));
    // The server requires the assign capability on top of ticket management for both assign actions.
    const canAssign = canManage && permissions.includes('fst_assign_agents');
    const canDelete = canManage && permissions.includes('fst_delete_tickets');

    const toItems = (map) => Object.keys(map || {}).map(id => ({ id, title: map[id] }));

    const primary = [
        { key: 'reply', label: $t('Reply'), input: 'reply' },
        canAssign && { key: 'assign', label: $t('Assign'), input: 'agent' },
        { key: 'tags', label: $t('Tags'), input: 'terms' },
        { key: 'close', label: $t('Close'), input: 'confirm' }
    ].filter(Boolean);

    const ticketItems = [
        { key: 'priority', label: $t('Priority'), input: 'choice', items: toItems(vars.admin_priorities) },
        { key: 'product', label: $t('Product'), input: 'choice', items: (vars.support_products || []).map(product => ({ id: product.id, title: product.title })) },
        { key: 'reopen', label: $t('Reopen'), input: 'confirm' }
    ].filter(item => item.input !== 'choice' || item.items.length);

    const groups = [{ label: $t('Ticket'), items: ticketItems }];

    const agentGroups = vars.agent_groups || [];
    if (canAssign && agentGroups.length) {
        groups.push({
            label: $t('Assignment'),
            items: [{ key: 'agent_group', label: $t('Assign to agent group'), input: 'choice', items: agentGroups.map(group => ({ id: group.id, title: group.title })) }]
        });
    }

    const workflows = vars.manual_workflows || [];
    if (workflows.length) {
        groups.push({
            label: $t('Automation'),
            items: [{ key: 'workflow', label: $t('Run workflow'), input: 'workflow', items: workflows.map(workflow => ({ id: workflow.id, title: workflow.title })) }]
        });
    }

    return {
        primary,
        groups,
        destructive: canDelete ? { key: 'delete', label: $t('Delete'), input: 'delete' } : null
    };
}

/**
 * The tickets/bulk-actions request for one action. `value` is the panel's
 * choice; for tags it is { mode: 'add'|'remove', ids: [] }.
 *
 * @returns {Object|null} null for actions that do not use tickets/bulk-actions.
 */
export function buildTicketBulkPayload(action, value) {
    switch (action.key) {
        case 'assign':
            return { bulk_action: 'assign_agent', agent_id: value };
        case 'agent_group':
            return { bulk_action: 'assign_agent_group', agent_group_id: value };
        case 'tags':
            return { bulk_action: value.mode === 'remove' ? 'remove_tags' : 'assign_tags', tag_ids: value.ids };
        case 'priority':
            return { bulk_action: 'change_priority', priority: value };
        case 'product':
            return { bulk_action: 'change_product', product_id: value };
        case 'close':
            return { bulk_action: 'close_tickets' };
        case 'reopen':
            return { bulk_action: 'reopen_tickets' };
        case 'delete':
            return { bulk_action: 'delete_tickets' };
    }
    return null;
}

/**
 * How many of the selected tickets carry each tag, keyed by tag id as a string.
 *
 * @param {Array} tickets Selected ticket rows, each with a `tags` array.
 */
export function countTagCoverage(tickets) {
    const coverage = {};
    (tickets || []).forEach(ticket => {
        (ticket.tags || []).forEach(tag => {
            const id = String(tag.id);
            coverage[id] = (coverage[id] || 0) + 1;
        });
    });
    return coverage;
}
