// @vitest-environment jsdom
// The FluentCommunity menu item: one item on posts and comments, FluentCommunity's own
// confirm dialog with an "email the author" checkbox, a spinner on its button while the
// request runs, one request per click, and a notification (not a redirect) at the end.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const ui = vi.hoisted(() => ({
    box: null,
    dialogs: [],
    notify: null,
    notifyError: null,
}));

vi.mock('@fluentcommunity/element-plus', () => ({
    ElCheckbox: { name: 'ElCheckbox' },
    ElMessageBox: (...args) => ui.box(...args),
    ElNotification: Object.assign((...args) => ui.notify(...args), { error: (...args) => ui.notifyError(...args) }),
}));

const config = {
    url: 'https://example.test/wp-json/fluent-support/v2/community/ticket',
    nonce: 'abc123',
    label: 'Create Support Ticket',
    confirm: 'Create a ticket for %s?',
    notify_label: 'Email the author',
    confirm_button: 'Create Ticket',
    cancel_button: 'Cancel',
    created: 'Support ticket #%s created. Click here to open it.',
    exists: 'Support ticket #%s already exists. Click here to open it.',
    error: 'Could not create the ticket.',
    view: 'View Ticket #%s',
    created_locked: "Support ticket #%s created. You don't have access to open it.",
    exists_label: 'Support ticket exists',
};

// The same shape as @wordpress/hooks, reduced to what the script uses.
const makeHooks = () => {
    const filters = {};
    return {
        namespaces: [],
        addFilter(name, ns, cb) {
            this.namespaces.push(ns);
            (filters[name] = filters[name] || []).push(cb);
        },
        applyFilters: (name, value, ...args) => (filters[name] || []).reduce((v, cb) => cb(v, ...args), value),
    };
};

const load = async () => {
    vi.resetModules();
    await import('../../../resources/admin/community_ticket_menu.js');
};

const respond = (ok, body) => Promise.resolve({ ok, json: () => Promise.resolve(body) });
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const ticketUrl = 'https://example.test/wp-admin/#/tickets/3/view';

// Acts like ElMessageBox: a button press goes through beforeClose, and the promise settles
// only when beforeClose calls done(). autoPress presses a button right away.
const messageBox = (autoPress) => vi.fn((options) => new Promise((resolve, reject) => {
    const instance = { confirmButtonLoading: false };
    let pressed = null;
    const done = () => (pressed === 'confirm' ? resolve('confirm') : reject(pressed));
    const press = (action) => {
        pressed = action;
        options.beforeClose(action, instance, done);
    };
    ui.dialogs.push({ options, instance, press });
    if (autoPress) {
        press(autoPress);
    }
}));

// The dialog body: [<p>question</p>, <ElCheckbox>]; rendered from the options the script passed.
const dialogBody = (options) => options.message().children;
const checkbox = (options) => dialogBody(options)[1];

describe('community ticket menu', () => {
    let hooks;

    beforeEach(() => {
        hooks = makeHooks();
        window.fsCommunityTicket = config;
        window.FluentCommunityUtil = { hooks };
        window.open = vi.fn();
        window.confirm = vi.fn();
        window.alert = vi.fn();
        ui.dialogs = [];
        ui.box = messageBox('confirm');
        ui.notify = vi.fn();
        ui.notifyError = vi.fn();
    });

    afterEach(() => {
        delete window.fsCommunityTicket;
        delete window.FluentCommunityUtil;
        delete window.fluentComAdmin;
    });

    it('adds one item to the post menu and keeps the existing ones', async () => {
        await load();
        const items = hooks.applyFilters('fluent_com_feed_action_items', [{ label: 'Other', action() {} }], { id: 5 });

        expect(items.map((i) => i.label)).toEqual(['Other', config.label]);
    });

    it('adds one item to the comment menu after the built-in ones', async () => {
        await load();
        const items = hooks.applyFilters('fluent_com_comment_action_items', [{ label: 'Copy Link', action() {} }], { id: 9 });

        expect(items.map((i) => i.label)).toEqual(['Copy Link', config.label]);
    });

    it('registers under an underscore namespace, as FluentCommunity add-ons do', async () => {
        await load();

        expect(hooks.namespaces).toEqual(['fluent_support_community_ticket', 'fluent_support_community_ticket']);
    });

    it('shows View Ticket for a post that has a ticket, and opens it without a dialog or request', async () => {
        const fetchMock = vi.fn();
        window.fetch = fetchMock;
        await load();

        const feed = { id: 5, fluent_support_ticket: { id: 3, number: '315542', url: ticketUrl } };
        const [item] = hooks.applyFilters('fluent_com_feed_action_items', [], feed);
        expect(item.label).toBe('View Ticket #315542');

        item.action();
        expect(window.open).toHaveBeenCalledWith(ticketUrl, '_blank', 'noopener');
        expect(ui.box).not.toHaveBeenCalled();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('shows a disabled item for a ticket the agent may not open', async () => {
        await load();

        const [item] = hooks.applyFilters('fluent_com_comment_action_items', [], { id: 9, fluent_support_ticket: { exists: true } });

        expect(item.label).toBe('Support ticket exists');
        expect(item.disabled).toBe(true);
        item.action();
        expect(ui.box).not.toHaveBeenCalled();
        expect(window.open).not.toHaveBeenCalled();
    });

    it('adds no item when the agent may not create tickets in the default mailbox, but still shows existing tickets', async () => {
        window.fsCommunityTicket = { ...config, can_create: false };
        await load();

        expect(hooks.applyFilters('fluent_com_feed_action_items', [{ label: 'Other', action() {} }], { id: 5 }).map((i) => i.label)).toEqual(['Other']);
        expect(hooks.applyFilters('fluent_com_comment_action_items', [], { id: 9 })).toEqual([]);

        const [existing] = hooks.applyFilters('fluent_com_feed_action_items', [], { id: 6, fluent_support_ticket: { id: 3, number: '315542', url: ticketUrl } });
        expect(existing.label).toBe('View Ticket #315542');
    });

    it('gives no link and a disabled item when the agent may not open the ticket they created', async () => {
        window.fetch = vi.fn(() => respond(true, { ticket_id: 4, ticket_number: '315550', ticket_url: null, already_exists: false }));
        await load();

        const comment = { id: 9 };
        hooks.applyFilters('fluent_com_comment_action_items', [], comment)[0].action();
        await flush();

        const notification = ui.notify.mock.calls[0][0];
        expect(notification.message).toBe("Support ticket #315550 created. You don't have access to open it.");
        expect(notification.onClick).toBeUndefined();
        expect(ui.notifyError).not.toHaveBeenCalled();
        expect(comment.fluent_support_ticket).toEqual({ exists: true });

        const [item] = hooks.applyFilters('fluent_com_comment_action_items', [], comment);
        expect(item.label).toBe('Support ticket exists');
        expect(item.disabled).toBe(true);
    });

    it('switches the item to View Ticket once the ticket is created', async () => {
        window.fetch = vi.fn(() => respond(true, { ticket_id: 3, ticket_number: '315542', ticket_url: ticketUrl, already_exists: false }));
        await load();

        const comment = { id: 9 };
        hooks.applyFilters('fluent_com_comment_action_items', [], comment)[0].action();
        await flush();

        expect(comment.fluent_support_ticket).toEqual({ id: 3, number: '315542', url: ticketUrl });
        expect(hooks.applyFilters('fluent_com_comment_action_items', [], comment)[0].label).toBe('View Ticket #315542');
    });

    it('does nothing without the config or without FluentCommunity hooks', async () => {
        delete window.fsCommunityTicket;
        await load();

        expect(hooks.applyFilters('fluent_com_feed_action_items', [], {})).toEqual([]);
    });

    it('confirms in the FluentCommunity dialog, posts with the nonce, then notifies without leaving the page', async () => {
        const fetchMock = vi.fn(() => respond(true, { ticket_id: 3, ticket_url: ticketUrl, already_exists: false }));
        window.fetch = fetchMock;
        await load();

        const [item] = hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5, xprofile: { display_name: 'Sam' } });
        item.action();
        await flush();

        const dialog = ui.box.mock.calls[0][0];
        expect(dialog).toEqual(expect.objectContaining({
            title: config.label,
            showCancelButton: true,
            confirmButtonText: 'Create Ticket',
            cancelButtonText: 'Cancel',
        }));
        expect(dialogBody(dialog)[0].children).toBe('Create a ticket for Sam?');
        expect(checkbox(dialog).props.modelValue).toBe(true);
        expect(checkbox(dialog).children.default()).toBe('Email the author');
        expect(window.confirm).not.toHaveBeenCalled();

        const [url, options] = fetchMock.mock.calls[0];
        expect(url).toBe(config.url);
        expect(options.headers['X-WP-Nonce']).toBe('abc123');
        expect(JSON.parse(options.body)).toEqual({ type: 'feed', id: 5, notify: true });

        expect(window.open).not.toHaveBeenCalled();
        const notification = ui.notify.mock.calls[0][0];
        expect(notification.type).toBe('success');
        expect(notification.message).toBe('Support ticket #3 created. Click here to open it.');

        notification.onClick();
        expect(window.open).toHaveBeenCalledWith(ticketUrl, '_blank', 'noopener');
    });

    it('says the ticket already exists when the post has one', async () => {
        window.fetch = vi.fn(() => respond(true, { ticket_id: 3, ticket_url: ticketUrl, already_exists: true }));
        await load();

        hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5 })[0].action();
        await flush();

        expect(ui.notify.mock.calls[0][0]).toEqual(expect.objectContaining({
            type: 'info',
            message: 'Support ticket #3 already exists. Click here to open it.',
        }));
    });

    it('shows the ticket number the admin shows, not the row id', async () => {
        window.fetch = vi.fn(() => respond(true, { ticket_id: 3, ticket_number: 'FS-1003', ticket_url: ticketUrl }));
        await load();

        hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5 })[0].action();
        await flush();

        expect(ui.notify.mock.calls[0][0].message).toBe('Support ticket #FS-1003 created. Click here to open it.');
    });

    it("sends FluentCommunity's renewed nonce when it has one", async () => {
        const fetchMock = vi.fn(() => respond(true, { ticket_id: 3, ticket_url: ticketUrl }));
        window.fetch = fetchMock;
        window.fluentComAdmin = { rest: { nonce: 'renewed456' } };
        await load();

        hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5 })[0].action();
        await flush();

        expect(fetchMock.mock.calls[0][1].headers['X-WP-Nonce']).toBe('renewed456');
    });

    it('shows the generic error for an HTML error page and for a network failure', async () => {
        await load();
        const [item] = hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5 });

        window.fetch = vi.fn(() => Promise.resolve({ ok: false, json: () => Promise.reject(new SyntaxError("Unexpected token '<'")) }));
        item.action();
        await flush();

        window.fetch = vi.fn(() => Promise.reject(new TypeError('Failed to fetch')));
        item.action();
        await flush();

        expect(ui.notifyError.mock.calls).toEqual([[config.error], [config.error]]);
    });

    it('sends notify false when the checkbox is unticked, on a comment', async () => {
        const fetchMock = vi.fn(() => respond(true, { ticket_id: 4, ticket_url: ticketUrl }));
        window.fetch = fetchMock;
        ui.box = messageBox();
        await load();

        hooks.applyFilters('fluent_com_comment_action_items', [], { id: 9 })[0].action();
        const dialog = ui.box.mock.calls[0][0];
        checkbox(dialog).props['onUpdate:modelValue'](false);
        expect(checkbox(dialog).props.modelValue).toBe(false);
        ui.dialogs[0].press('confirm');
        await flush();

        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ type: 'comment', id: 9, notify: false });
    });

    it('starts each dialog with the checkbox ticked', async () => {
        window.fetch = vi.fn(() => respond(true, { ticket_id: 4, ticket_url: ticketUrl }));
        await load();

        const [item] = hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5 });
        item.action();
        checkbox(ui.box.mock.calls[0][0]).props['onUpdate:modelValue'](false);
        await flush();
        item.action();
        await flush();

        expect(checkbox(ui.box.mock.calls[1][0]).props.modelValue).toBe(true);
    });

    it('keeps the dialog open with a spinner until the request ends, and ignores closing it meanwhile', async () => {
        let finish;
        window.fetch = vi.fn(() => new Promise((resolve) => { finish = resolve; }));
        ui.box = messageBox();
        await load();

        let closed = false;
        hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5 })[0].action();
        // Element Plus keeps the last pressed button, so this settles as a rejection; only closing matters.
        ui.box.mock.results[0].value.then(() => { closed = true; }, () => { closed = true; });
        const dialog = ui.dialogs[0];

        dialog.press('confirm');
        await flush();
        expect(dialog.instance.confirmButtonLoading).toBe(true);

        dialog.press('cancel');
        await flush();
        expect(closed).toBe(false);
        expect(ui.notify).not.toHaveBeenCalled();

        finish({ ok: true, json: () => Promise.resolve({ ticket_id: 3, ticket_url: ticketUrl }) });
        await flush();
        expect(dialog.instance.confirmButtonLoading).toBe(false);
        expect(closed).toBe(true);
        expect(ui.notify).toHaveBeenCalledTimes(1);
    });

    it('sends one request for a double click', async () => {
        const fetchMock = vi.fn(() => new Promise(() => {}));
        window.fetch = fetchMock;
        await load();

        const [item] = hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5 });
        item.action();
        item.action();
        await flush();

        expect(ui.box).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('sends nothing when the agent cancels, and allows another try', async () => {
        const fetchMock = vi.fn(() => respond(true, { ticket_id: 3, ticket_url: ticketUrl }));
        window.fetch = fetchMock;
        ui.box = messageBox('cancel');
        await load();

        const [item] = hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5 });
        item.action();
        await flush();

        expect(fetchMock).not.toHaveBeenCalled();
        expect(ui.notify).not.toHaveBeenCalled();
        expect(ui.notifyError).not.toHaveBeenCalled();

        ui.box = messageBox('confirm');
        item.action();
        await flush();
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('shows the server message as an error notification and allows another try', async () => {
        const fetchMock = vi.fn(() => respond(false, { message: 'Sorry, you cannot.' }));
        window.fetch = fetchMock;
        await load();

        const [item] = hooks.applyFilters('fluent_com_feed_action_items', [], { id: 5 });
        item.action();
        await flush();

        expect(ui.notifyError).toHaveBeenCalledWith('Sorry, you cannot.');
        expect(window.alert).not.toHaveBeenCalled();

        item.action();
        await flush();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });
});
