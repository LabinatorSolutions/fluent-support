import { $get } from '../utils/request';

export const TicketDetailsComponent = async () => {
    const hash = window.location.hash;
    const ticketIdMatch = hash.match(/\/ticket\/(\d+)\/view/);  // Match the ticket ID from the path

    const container = document.createElement('div');

    container.innerHTML = `
        <div>
            <h2>Ticket Details</h2>
            <div id="ticketDetails">Loading...</div>
            <button onclick="location.hash='/'" style="margin-top: 20px;">Back to Dashboard</button>
        </div>
    `;

    const ticketDetails = container.querySelector('#ticketDetails');

    if (!ticketIdMatch) {
        ticketDetails.innerText = 'Invalid ticket ID.';
        return container;
    }

    const ticketId = ticketIdMatch[1];

    try {
        const ticket = await $get(`tickets/${ticketId}`);

        console.log(ticket)

        ticketDetails.innerHTML = `
            <p><strong>ID:</strong> ${ticket.ticket.id}</p>
            <p><strong>Title:</strong> ${ticket.ticket.title}</p>
            <p><strong>Description:</strong> ${ticket.ticket.content}</p>
            <p><strong>Status:</strong> ${ticket.ticket.status}</p>
        `;
    } catch (error) {
        ticketDetails.innerHTML = `<p>Error fetching ticket details: ${error.message}</p>`;
    }

    return container;
};
