export const CreateTicketComponent = async () => {
    const container = document.createElement('div');
    container.innerHTML = `
        <div>
            <h2>Create New Ticket</h2>
            <form id="createTicketForm">
                <div>
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="title" required />
                </div>
                <div>
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="5" required></textarea>
                </div>
                <button type="submit">Create Ticket</button>
            </form>
            <div id="formMessage" style="margin-top: 20px; color: red;"></div>
        </div>
    `;

    const form = container.querySelector('#createTicketForm');
    const message = container.querySelector('#formMessage');

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            await window.app.$post('tickets', data);
            message.style.color = 'green';
            message.innerText = 'Ticket created successfully!';
            form.reset();
        } catch (error) {
            message.style.color = 'red';
            message.innerText = `Error creating ticket: ${error.message}`;
        }
    });

    return container;
};
