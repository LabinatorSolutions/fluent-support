// @vitest-environment jsdom
// The workflow flow builder cards: condition lines, action details, and the
// action order kept when an older Pro saves actions without it.

import { describe, it, expect, vi } from 'vitest';
import { conditionText, conditionGroups, actionDetail } from '@/admin/Modules/Workflows/workflowSummary';

vi.mock('@/admin/Components/Icons/DynamicIcon.vue', () => ({ default: { render: () => null } }));

const conditions = {
    'ticket.priority': { title: 'Ticket Priority (Agent)', data_type: 'single_dropdown', options: { normal: 'Normal', critical: 'Critical' } },
    'ticket.tags': { title: 'Ticket Tags', data_type: 'multiple_select', options: { 3: 'billing', 4: 'vip' } },
    'message.title': { title: 'Ticket Title', data_type: 'string' },
    'message.attachments': { title: 'Attachments', data_type: 'yes_no', options: { yes: 'Has an attachment', no: 'Does not have an attachment' } },
    'message.added_date_range': { title: 'Added Date Range', data_type: 'date_range' },
    'commerce.products': { title: 'Purchased Product', data_type: 'remote_select' },
};

describe('condition lines', () => {
    it('writes each operator as a sentence with option labels', () => {
        expect(conditionText({ data_key: 'ticket.priority', data_operator: 'equal', data_value: 'critical' }, conditions)).toBe('Ticket Priority (Agent) is Critical');
        expect(conditionText({ data_key: 'ticket.priority', data_operator: 'not_equal', data_value: 'normal' }, conditions)).toBe('Ticket Priority (Agent) is not Normal');
        expect(conditionText({ data_key: 'ticket.tags', data_operator: 'includes_in', data_value: ['3', '4'] }, conditions)).toBe('Ticket Tags is any of billing, vip');
        expect(conditionText({ data_key: 'ticket.tags', data_operator: 'not_includes_in', data_value: ['4'] }, conditions)).toBe('Ticket Tags is none of vip');
        expect(conditionText({ data_key: 'message.title', data_operator: 'not_contains', data_value: 'refund' }, conditions)).toBe('Ticket Title does not contain "refund"');
        expect(conditionText({ data_key: 'message.attachments', data_operator: 'yes' }, conditions)).toBe('Attachments: Has an attachment');
        expect(conditionText({ data_key: 'message.added_date_range', data_operator: 'range', value_1: '2026-01-01 00:00', value_2: '2026-02-01 00:00' }, conditions))
            .toBe('Added Date Range is between 2026-01-01 00:00 and 2026-02-01 00:00');
        expect(conditionText({ data_key: 'commerce.products', data_operator: 'includes_in', data_value: ['7', '9'] }, conditions)).toBe('Purchased Product is any of 2 items');
    });

    it('skips blank and unfinished rows, and groups left empty', () => {
        const groups = [
            [{ data_key: '', data_operator: '', data_value: '' }],
            [{ data_key: 'ticket.priority', data_operator: '', data_value: '' }, { data_key: 'ticket.priority', data_operator: 'equal', data_value: 'critical' }],
            [{ data_key: 'gone.field', data_operator: 'equal', data_value: 'x' }],
        ];

        expect(conditionGroups(groups, conditions)).toEqual([['Ticket Priority (Agent) is Critical']]);
        expect(conditionGroups(undefined, conditions)).toEqual([]);
    });
});

describe('action details', () => {
    const appVars = {
        support_agents: [{ id: '5', first_name: 'Ada', last_name: 'Lovelace' }],
        ticket_tags: [{ id: '3', title: 'billing' }],
    };

    it('names agents, tags and options instead of ids', () => {
        const assign = { fields: { agent_id: { type: 'agent-selectors', extra_options: [{ id: 'unassigned', title: 'Unassigned' }] }, skip_if_exist: { type: 'inline-checkbox' } } };
        expect(actionDetail({ action_name: 'a', settings: { agent_id: '5', skip_if_exist: 'yes' } }, assign, appVars)).toBe('Ada Lovelace');
        expect(actionDetail({ action_name: 'a', settings: { agent_id: 'unassigned' } }, assign, appVars)).toBe('Unassigned');

        const tags = { fields: { tag_ids: { type: 'tag-selectors' } } };
        expect(actionDetail({ action_name: 't', settings: { tag_ids: ['3'] } }, tags, appVars)).toBe('billing');

        const priority = { fields: { priority: { type: 'input-options', options: [{ id: 'high', title: 'High' }] } } };
        expect(actionDetail({ action_name: 'p', settings: { priority: 'high' } }, priority, appVars)).toBe('High');
    });

    it('shows reply text without HTML, shortened', () => {
        const reply = { fields: { response_body: { type: 'wp-editor' } } };
        const detail = actionDetail({ action_name: 'r', settings: { response_body: '<p>Thanks for <b>writing</b>.</p>' + '<p>' + 'x'.repeat(200) + '</p>' } }, reply, appVars);

        expect(detail.startsWith('Thanks for writing.')).toBe(true);
        expect(detail.length).toBe(80);
        expect(detail.endsWith('…')).toBe(true);
    });

    it('is empty when nothing is filled in', () => {
        const tags = { fields: { tag_ids: { type: 'tag-selectors' } } };
        expect(actionDetail({ action_name: 't', settings: { tag_ids: [] } }, tags, appVars)).toBe('');
        expect(actionDetail({ action_name: 't', settings: {} }, undefined, appVars)).toBe('');
    });
});

// A jQuery-style request settled by hand: resolve(data) or reject(error).
function pending() {
    const thens = [], catches = [], always = [];
    const chain = {
        then(cb) { thens.push(cb); return chain; },
        catch(cb) { catches.push(cb); return chain; },
        always(cb) { always.push(cb); return chain; },
        resolve(data) { thens.forEach(cb => cb(data)); always.forEach(cb => cb()); },
        reject(error) { catches.forEach(cb => cb(error)); always.forEach(cb => cb()); },
    };
    return chain;
}

// The editor's methods bound to a plain object standing in for the component.
async function editor() {
    const { default: EditWorkFlow } = await import('@/admin/Modules/Workflows/EditWorkFlow.vue');
    const ctx = {
        ...EditWorkFlow.data.call({}),
        workflow_id: 9,
        workflow: { id: 9, title: 'Test', trigger_type: 'manual', status: 'draft' },
        posts: [],
        errors: [],
        $post(url, data) { const request = pending(); ctx.posts.push({ url, data, request }); return request; },
        $notify() {},
        $handleError(error) { ctx.errors.push(error); },
        renewOptions() {},
    };
    Object.keys(EditWorkFlow.methods).forEach(name => { ctx[name] = EditWorkFlow.methods[name].bind(ctx); });
    return ctx;
}

describe('action order on older Pro', () => {
    // Sent: existing 12, a new action, existing 10. Old Pro created 15 and returned id order.
    const sent = () => [{ id: 12 }, { action_name: 'new' }, { id: 10 }];
    const lost = () => [{ id: '10' }, { id: '12' }, { id: '15' }];

    it('saves the order again and finishes after that request succeeds', async () => {
        const ctx = await editor();
        const saved = lost();
        let done = false;
        ctx.keepActionOrder(sent(), saved, () => { done = true; });

        expect(ctx.posts.map(post => [post.url, post.data])).toEqual([['workflows/action-sequence', { workflow_id: 9, action_sequence: [12, 15, 10] }]]);
        expect(done).toBe(false);

        ctx.posts[0].request.resolve({});
        expect(done).toBe(true);
        expect(saved.map(action => action.id)).toEqual(['12', '15', '10']);
    });

    it('keeps the server order when the order request fails', async () => {
        const ctx = await editor();
        const saved = lost();
        let done = false;
        ctx.keepActionOrder(sent(), saved, () => { done = true; });

        ctx.posts[0].request.reject(new Error('network'));
        expect(done).toBe(true);
        expect(ctx.errors).toHaveLength(1);
        expect(saved.map(action => action.id)).toEqual(['10', '12', '15']);
    });

    it('does nothing when the response is already in order', async () => {
        const ctx = await editor();
        let done = false;
        ctx.keepActionOrder([{ id: 12 }, { action_name: 'new' }], [{ id: '12' }, { id: '15' }], () => { done = true; });

        expect(ctx.posts).toEqual([]);
        expect(done).toBe(true);
    });
});

describe('editing while a save is pending', () => {
    it('blocks opening steps until the response arrives', async () => {
        const ctx = await editor();
        ctx.actions = [{ id: 12, title: 'Add Tags', action_name: 'fs_action_add_tags', settings: {}, _key: 'action_a' }];

        ctx.updateWorkFlow();
        expect(ctx.saving).toBe(true);

        ctx.openEditor('action_a');
        ctx.openPicker(1);
        expect(ctx.drawer.open).toBe(false);

        ctx.posts[0].request.resolve({ message: 'Saved', workflow: ctx.workflow, actions: [{ id: 12, title: 'Add Tags', action_name: 'fs_action_add_tags', settings: {} }] });
        expect(ctx.saving).toBe(false);

        ctx.openPicker(1);
        expect(ctx.drawer.open).toBe(true);
    });

    it('stays saving until the old-Pro order request settles, then stops on failure too', async () => {
        const ctx = await editor();
        ctx.actions = [
            { id: 12, title: 'A', action_name: 'a', settings: {}, _key: 'action_a' },
            { id: 10, title: 'B', action_name: 'b', settings: {}, _key: 'action_b' },
        ];

        ctx.updateWorkFlow();
        ctx.posts[0].request.resolve({ message: 'Saved', workflow: ctx.workflow, actions: [{ id: '10', settings: {} }, { id: '12', settings: {} }] });
        expect(ctx.posts[1].url).toBe('workflows/action-sequence');
        expect(ctx.saving).toBe(true);

        ctx.posts[1].request.reject(new Error('network'));
        expect(ctx.saving).toBe(false);
        // The editor shows the order the server kept, so the page does not claim a save that failed.
        expect(ctx.actions.map(action => action.id)).toEqual(['10', '12']);
    });
});
