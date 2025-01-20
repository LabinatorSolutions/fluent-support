import { $get } from '../utils/request';

export const DashboardComponent = async () => {
    // Create container element
    const container = document.createElement('div');
    container.innerHTML = `
        <div>
            <h2>Dashboard</h2>
            <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ticketList">
                    <tr>
                        <td colspan="4">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;

    const ticketList = container.querySelector('#ticketList');

    try {
        const response = await $get('tickets');
        const tickets = response.tickets.data;

        if (Array.isArray(tickets) && tickets.length > 0) {
            ticketList.innerHTML = tickets
                .map(ticket => `
                    <tr>
                        <td>${ticket.id}</td>
                        <td>${ticket.title}</td>
                        <td>${ticket.status}</td>
                        <td>
                            <button onclick="location.hash='/ticket/${ticket.id}/view'">View</button>
                        </td>
                    </tr>
                `)
                .join('');
        } else {
            ticketList.innerHTML = '<tr><td colspan="4">No tickets found.</td></tr>';
        }
    } catch (error) {
        ticketList.innerHTML = `<tr><td colspan="4">Error fetching tickets: ${error.message}</td></tr>`;
    }

    return container;
};
