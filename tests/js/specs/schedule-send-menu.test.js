// @vitest-environment jsdom
// The ▾ schedule menu: a ticket holds one scheduled reply, so with one pending the menu
// shows that reply instead of scheduling another, and only the reply being edited can be rescheduled.

import { describe, it, expect, afterEach } from 'vitest';
import { createApp, h, nextTick } from 'vue';

// Thu, Oct 29 2026 09:00 UTC
const pendingAt = 1793264400;
const pending = { id: 7, schedule: { scheduled_at: pendingAt, close_ticket: 'no' } };

let app;
let editRequests;

const mount = async (props) => {
    const { default: ScheduleSendMenu } = await import('@/admin/Modules/Tickets/parts/_ScheduleSendMenu.vue');
    const el = document.createElement('div');
    document.body.appendChild(el);
    editRequests = 0;

    app = createApp({
        render: () => h(ScheduleSendMenu, {
            mode: 'pro', busy: false, disabled: false, needsText: false, ...props,
            onEditPending: () => editRequests++
        })
    });
    app.config.warnHandler = () => {};
    app.component('ElPopover', { render() { return h('div', [this.$slots.reference?.(), this.$slots.default?.()]); } });
    app.component('ElButton', {
        props: ['disabled'],
        emits: ['click'],
        render() {
            return h('button', { disabled: this.disabled, onClick: () => !this.disabled && this.$emit('click') }, this.$slots.default?.());
        }
    });
    app.config.globalProperties.$t = (s, ...args) => args.reduce((out, arg) => out.replace('%s', arg), s);
    app.mount(el);
    await nextTick();
    return el;
};

const menuItem = (el) => el.querySelector('.fs_schedule_menu button');

describe('schedule send menu', () => {
    afterEach(() => {
        app?.unmount();
        document.body.innerHTML = '';
    });

    it('opens_the_time_form_when_the_ticket_has_no_scheduled_reply', async () => {
        const el = await mount({});

        expect(el.querySelector('.fs_schedule_blocked')).toBeNull();
        expect(menuItem(el).textContent).toContain('Send it later at a time you pick');

        menuItem(el).click();
        await nextTick();

        expect(el.querySelector('.fs_schedule_form')).not.toBeNull();
    });

    it('shows_the_pending_reply_instead_of_scheduling_a_second_one', async () => {
        const el = await mount({ blocked: pending, canEdit: true });
        const card = el.querySelector('.fs_schedule_blocked');

        expect(menuItem(el)).toBeNull();
        expect(el.querySelector('.fs_schedule_form')).toBeNull();
        expect(card.textContent).toContain('Reply already scheduled');
        expect(card.textContent).toContain('Sends Thu, Oct 29 · 9:00 AM UTC');

        card.querySelector('button').click();
        await nextTick();

        expect(editRequests).toBe(1);
    });

    it('offers_no_edit_button_to_an_agent_who_cannot_change_the_reply', async () => {
        const el = await mount({ blocked: pending, canEdit: false });
        const card = el.querySelector('.fs_schedule_blocked');

        expect(card.querySelector('button')).toBeNull();
        expect(card.textContent).toContain('A ticket can have one scheduled reply. Edit or cancel it to schedule this one.');
    });

    it('offers_reschedule_only_for_the_reply_being_edited', async () => {
        const el = await mount({ pending });

        expect(el.querySelector('.fs_schedule_blocked')).toBeNull();
        expect(menuItem(el).textContent).toContain('Reschedule…');
        expect(menuItem(el).textContent).toContain('Currently Thu 9:00 AM UTC');
    });
});
