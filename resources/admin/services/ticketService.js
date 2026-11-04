import Rest from '@/admin/Bits/Rest';

// The single ticket screen's API calls (ViewTicket.vue and its parts).
export default {
    get(ticketId, params = {}) {
        return Rest.get(`tickets/${ticketId}`, params);
    },

    draft(ticketId) {
        return Rest.get(`tickets/${ticketId}/draft`);
    },

    discardDraft(draftId) {
        return Rest.delete(`tickets/${draftId}/draft`);
    },

    updateProperty(ticketId, name, value) {
        return Rest.put(`tickets/${ticketId}/property`, { prop_name: name, prop_value: value });
    },

    close(ticketId, silently = 'no') {
        return Rest.post(`tickets/${ticketId}/close`, { close_ticket_silently: silently });
    },

    reopen(ticketId) {
        return Rest.post(`tickets/${ticketId}/re-open`);
    },

    remove(ticketId) {
        return Rest.delete(`tickets/${ticketId}/delete`);
    },

    approveDraftResponse(ticketId, responseId, content) {
        return Rest.put(`tickets/${ticketId}/approve_draft_response/${responseId}`, { content });
    },

    deleteResponse(ticketId, responseId) {
        return Rest.delete(`tickets/${ticketId}/responses/${responseId}`);
    },

    moveToMailbox(fromMailboxId, toMailboxId, ticketId) {
        return Rest.put(`mailboxes/${fromMailboxId}/move_tickets`, {
            new_box_id: toMailboxId,
            ticket_ids: [ticketId],
            move_type: 'Custom',
        });
    },

    // Pro routes below.
    customerTickets(customerId, params) {
        return Rest.get(`tickets/customer_tickets/${customerId}`, params);
    },

    merge(ticketId, ticketIds) {
        return Rest.post(`tickets/${ticketId}/merge_tickets`, { ticket_to_merge: ticketIds });
    },

    split(ticketId, splitTicket) {
        return Rest.post(`tickets/${ticketId}/split_ticket`, { split_ticket: splitTicket });
    },

    addWatchers(ticketId, agentIds) {
        return Rest.post(`tickets/${ticketId}/add_watchers`, { watchers: agentIds });
    },

    summary(ticketId) {
        return Rest.post(`ai/${ticketId}/get-ticket-summary`, { type: 'ticketSummary' });
    },

    tone(ticketId) {
        return Rest.post(`ai/${ticketId}/get-ticket-tone`, { type: 'ticketTone' });
    },
};
