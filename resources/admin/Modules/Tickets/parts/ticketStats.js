/**
 * Rows for the ticket view's Ticket Stats widget, built from fields the
 * ticket payload already carries.
 */

/**
 * A duration in seconds as "3d 4h", "2h 14m", "45m" or "Under a minute".
 *
 * @param {number} seconds
 * @param {Function} $t
 */
export function formatDuration(seconds, $t) {
    const total = Math.max(0, Math.floor(Number(seconds) || 0));
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const minutes = Math.floor((total % 3600) / 60);

    if (days) {
        // translators: a duration, %1$s is days and %2$s is hours, for example "3d 4h"
        return $t('%1$sd %2$sh', days, hours);
    }
    if (hours) {
        // translators: a duration, %1$s is hours and %2$s is minutes, for example "2h 14m"
        return $t('%1$sh %2$sm', hours, minutes);
    }
    if (minutes) {
        // translators: a duration in minutes, for example "45m"
        return $t('%sm', minutes);
    }
    return $t('Under a minute');
}

/**
 * @param {Object} ticket Ticket payload from tickets/{id}.
 * @param {Object} helpers { $t, sourceLabel(source), formatDate(date) }
 * @returns {Array<{key: string, label: string, value: string, hint?: string}>}
 */
export function buildTicketStats(ticket, { $t, sourceLabel, formatDate }) {
    if (!ticket || !ticket.id) {
        return [];
    }

    const rows = [];
    const agentStarted = ticket.created_by_agent && ticket.created_by_agent.full_name;
    let createdHint = '';
    if (agentStarted) {
        // translators: %s is the name of the agent who started the ticket
        createdHint = $t('by %s', ticket.created_by_agent.full_name);
    } else if (ticket.source) {
        // translators: %s is where the ticket came from, such as Web or Email
        createdHint = $t('via %s', sourceLabel(ticket.source));
    }
    rows.push({ key: 'created', label: $t('Created'), value: formatDate(ticket.created_at) || '', hint: createdHint });

    let firstResponse;
    if (Number(ticket.first_response_time) > 0) {
        firstResponse = formatDuration(ticket.first_response_time, $t);
    } else if (ticket.status === 'new') {
        firstResponse = $t('Not yet');
    } else {
        // Recorded only when an agent replies while the ticket is still new.
        firstResponse = $t('Not recorded');
    }
    rows.push({ key: 'first_response', label: $t('First response'), value: firstResponse });

    rows.push({ key: 'replies', label: $t('Replies'), value: String(parseInt(ticket.response_count) || 0) });

    if (ticket.status === 'closed' && Number(ticket.total_close_time) > 0) {
        rows.push({
            key: 'resolved',
            label: $t('Resolved in'),
            value: formatDuration(ticket.total_close_time, $t),
            hint: formatDate(ticket.resolved_at) || ''
        });
    }

    return rows;
}
