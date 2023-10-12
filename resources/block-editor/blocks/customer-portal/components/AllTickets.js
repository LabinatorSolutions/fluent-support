import {
    getAllTicketsHeaderStyle,
    getAllTicketsAllButtonStyle,
    getAllTicketsOpenButtonStyle,
    getAllTicketsClosedButtonStyle,
    getAllTicketsLogoutButtonStyle,
    getAllTicketsCreateTicketButtonStyle,
    getAllTicketsTableHeaderStyle,
    getAllTicketsFooterStyle,
} from '../style';

export default function AllTickets(props) {
    const { attributes, showSection, toggleInspector, selectedInspector } = props;

    /**
     * Get the CSS class for an inspector based on whether it's active.
     * @param {string} inspectorName - The name of the inspector.
     * @returns {string} - The CSS class.
     */
    const getActiveClass = (inspectorName) => {
        return selectedInspector === inspectorName ? ' fst-block-active-components' : '';
    };

    /**
     * Handle clicking on an inspector element.
     * @param {string} inspectorName - The inspector name to toggle.
     * @returns {Function} - Event handler function.
     */
    const handleInspectorButtonClick = (inspectorName) => (e) => {
        e.stopPropagation();
        toggleInspector(inspectorName);
    };

    return (
        <div className={'customer-portal-block-wrapper'}>
            {/* All Tickets Header */}
            <div className={`all-tickets-header fst-hover-for-select${getActiveClass('allTicketsHeaderStyle')}`} style={getAllTicketsHeaderStyle(attributes)} onClick={() => toggleInspector('allTicketsHeaderStyle')}>
                <div className="all-tickets-header-left">
                    <div className={`all-tickets-button-groups${getActiveClass('filterButtonActiveStyle')}`} onClick={handleInspectorButtonClick('filterButtonActiveStyle')}>
                        {/* Button: All */}
                        <div className={`all-tickets-button-groups-btn${getActiveClass('buttonAllStyle')}`} onClick={handleInspectorButtonClick('buttonAllStyle')}>
                            <button className="all-tickets btn-all btn-active" style={getAllTicketsAllButtonStyle(attributes)}>All</button>
                        </div>
                        {/* Button: Open */}
                        <div className={`all-tickets-button-groups-btn${getActiveClass('buttonOpenStyle')}`} onClick={handleInspectorButtonClick('buttonOpenStyle')}>
                            <button className="all-tickets btn-open" style={getAllTicketsOpenButtonStyle(attributes)}>Open</button>
                        </div>
                        {/* Button: Closed */}
                        <div className={`all-tickets-button-groups-btn${getActiveClass('buttonClosedStyle')}`} onClick={handleInspectorButtonClick('buttonClosedStyle')}>
                            <button className="all-tickets btn-closed" style={getAllTicketsClosedButtonStyle(attributes)}>Closed</button>
                        </div>
                    </div>
                </div>
                <div className="all-tickets-header-right">
                    {/* Button: Logout */}
                    <div className={`all-tickets-action-button-group${getActiveClass('buttonLogoutStyle')}`} onClick={handleInspectorButtonClick('buttonLogoutStyle')}>
                        <button className="all-tickets btn-logout" style={getAllTicketsLogoutButtonStyle(attributes)}>Logout</button>
                    </div>
                    {/* Button: Create Ticket */}
                    <div className={`all-tickets-action-button-group${getActiveClass('buttonCreateTicketStyle')}`} onClick={handleInspectorButtonClick('buttonCreateTicketStyle')}>
                        <button className="all-tickets btn-success btn-create-ticket" style={getAllTicketsCreateTicketButtonStyle(attributes)} onClick={() => showSection('createTicket')}>Create a New Ticket</button>
                    </div>
                </div>
            </div>

            {/* All Tickets Body */}
            <div className="all-tickets-body">
                <table className="all-tickets-table table_stripe">
                    <thead className={`all-tickets-table-header${getActiveClass('allTicketsTableHeaderStyle')}`} onClick={() => toggleInspector('allTicketsTableHeaderStyle')}>
                    <tr>
                        <th style={getAllTicketsTableHeaderStyle(attributes)}>Conversation</th>
                        <th style={getAllTicketsTableHeaderStyle(attributes)}></th>
                        <th style={getAllTicketsTableHeaderStyle(attributes)}>Status</th>
                        <th style={getAllTicketsTableHeaderStyle(attributes)}>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    {/* Sample table rows, replace with dynamic data */}
                    <tr onClick={() => showSection('viewTicket')}>
                        <td>
                            <a className="ticket-preview">
                                <strong>Test ticket 1</strong>
                                <div className="prev_text_parent">
                                    <p className="ticket-preview-text">Test ticket 1</p>
                                </div>
                            </a>
                        </td>
                        <td className="ticket-thread-count"><span className="thread-count">0</span></td>
                        <td className="ticket-status"><span className="ticket-status-content">new</span></td>
                        <td className="fs_tk_date"><span className="fs_tk_date">20 hours ago</span></td>
                    </tr>
                    <tr onClick={() => showSection('viewTicket')}>
                        <td>
                            <a className="ticket-preview">
                                <strong>Test ticket 2</strong>
                                <div className="prev_text_parent">
                                    <p className="ticket-preview-text">Test ticket 2</p>
                                </div>
                            </a>
                        </td>
                        <td className="ticket-thread-count"><span className="thread-count">0</span></td>
                        <td className="ticket-status"><span className="ticket-status-content">new</span></td>
                        <td className="fs_tk_date"><span className="fs_tk_date">19 hours ago</span></td>
                    </tr>
                    </tbody>
                </table>
            </div>

            {/* All Tickets Footer */}
            <div className={`all-tickets-footer${getActiveClass('allTicketsFooterStyle')}`} style={getAllTicketsFooterStyle(attributes)} onClick={() => toggleInspector('allTicketsFooterStyle')}></div>
        </div>
    );
}
