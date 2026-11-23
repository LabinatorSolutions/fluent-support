// Adds "Create Support Ticket" to the FluentCommunity post and comment menus.
// A plain ES module, loaded by FluentCommunity between its start script and the app.
// Uses FluentCommunity's own Vue and Element Plus (shared through its import map) for the dialogs.
import { h, ref } from '@fluentcommunity/vue';
import { ElCheckbox, ElMessageBox, ElNotification } from '@fluentcommunity/element-plus';
import { hooks } from '@fluentcommunity/ui';

(function () {
    var config = window.fsCommunityTicket;

    if (!config || !hooks) {
        return;
    }

    var FIELD = 'fluent_support_ticket';
    var working = false;

    function send(type, subject, notify) {
        return fetch(config.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                // FluentCommunity renews its REST nonce in long-open tabs; ours is the page-load copy.
                'X-WP-Nonce': (window.fluentComAdmin && window.fluentComAdmin.rest && window.fluentComAdmin.rest.nonce) || config.nonce
            },
            body: JSON.stringify({ type: type, id: subject.id, notify: notify })
        }).catch(function () {
            // Network failure: the browser's own message ("Failed to fetch") means nothing to a moderator.
            throw new Error(config.error);
        }).then(function (response) {
            // An HTML error page (fatal, proxy error) is not JSON: fall back to the generic message.
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                data = data || {};
                if (!response.ok || !data.ticket_id) {
                    throw new Error((!response.ok && data.message) || config.error);
                }
                return data;
            });
        });
    }

    // The admin URL lives on the ticket data; window.open keeps the community tab.
    function openTicket(url) {
        window.open(url, '_blank', 'noopener');
    }

    function fill(text, value) {
        return text.replace('%s', function () {
            return value;
        });
    }

    function showCreated(data) {
        var number = data.ticket_number || data.ticket_id;

        // No URL: the agent may not open this ticket (for example, they manage only their own tickets).
        if (!data.ticket_url) {
            ElNotification({
                type: 'success',
                message: fill(config.created_locked, number),
                duration: 8000
            });
            return;
        }

        ElNotification({
            type: data.already_exists ? 'info' : 'success',
            message: fill(data.already_exists ? config.exists : config.created, number),
            duration: 8000,
            onClick: function () {
                openTicket(data.ticket_url);
            }
        });
    }

    // One confirm dialog; the checkbox decides whether the author is emailed.
    function createTicket(type, subject) {
        if (working) {
            return;
        }

        var name = (subject.xprofile && subject.xprofile.display_name) || '';
        var notify = ref(true);

        working = true;

        ElMessageBox({
            title: config.label,
            message: function () {
                return h('div', [
                    h('p', fill(config.confirm, name)),
                    h(ElCheckbox, {
                        modelValue: notify.value,
                        'onUpdate:modelValue': function (value) {
                            notify.value = value;
                        }
                    }, function () {
                        return config.notify_label;
                    })
                ]);
            },
            showCancelButton: true,
            confirmButtonText: config.confirm_button,
            cancelButtonText: config.cancel_button,
            confirmButtonClass: 'fcom_primary_button',
            // The dialog stays open with a spinner on the button until the request ends.
            beforeClose: function (action, instance, done) {
                if (instance.confirmButtonLoading) {
                    return;
                }
                if (action !== 'confirm') {
                    done();
                    return;
                }

                instance.confirmButtonLoading = true;
                send(type, subject, notify.value)
                    .then(function (data) {
                        // The post or comment is reactive: its menu switches to "View Ticket" at once.
                        subject[FIELD] = data.ticket_url
                            ? { id: data.ticket_id, number: String(data.ticket_number || data.ticket_id), url: data.ticket_url }
                            : { exists: true };
                        showCreated(data);
                    }, function (error) {
                        ElNotification.error(error.message || config.error);
                    })
                    .then(function () {
                        instance.confirmButtonLoading = false;
                        done();
                    });
            }
        })
            .catch(function () {
                // Cancelled.
            })
            .then(function () {
                working = false;
            });
    }

    // fluent_support_ticket comes with the post or comment from the API (moderator agents only):
    // {id, number, url} for a ticket the agent may open, {exists: true} for one they may not.
    function items(type, subject) {
        var ticket = subject[FIELD];

        if (ticket && ticket.url) {
            return [{
                label: fill(config.view, ticket.number),
                action: function () {
                    openTicket(ticket.url);
                }
            }];
        }

        if (ticket && ticket.exists) {
            return [{
                label: config.exists_label,
                disabled: true,
                action: function () {}
            }];
        }

        // Restricted from the mailbox new tickets go to: no item, as FluentCommunity hides what a user may not do.
        if (config.can_create === false) {
            return [];
        }

        return [{
            label: config.label,
            action: function () {
                createTicket(type, subject);
            }
        }];
    }

    hooks.addFilter('fluent_com_feed_action_items', 'fluent_support_community_ticket', function (list, feed) {
        return list.concat(items('feed', feed));
    });

    hooks.addFilter('fluent_com_comment_action_items', 'fluent_support_community_ticket', function (list, comment) {
        return list.concat(items('comment', comment));
    });
})();
