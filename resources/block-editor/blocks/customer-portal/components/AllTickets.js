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
    const { attributes, showSection, activeClass, selectedInspector, preventParentPropagation } = props;

    return (
        <div className={'customer-portal-block-wrapper'}>
            {/* All Tickets Header */}
            <div className={`all-tickets-header fst-hover-for-select${activeClass('allTicketsHeaderStyle')}`} style={getAllTicketsHeaderStyle(attributes)} onClick={() => selectedInspector('allTicketsHeaderStyle')}>
                <div className="all-tickets-header-left">
                    <div className={`all-tickets-button-groups${activeClass('filterButtonActiveStyle')}`} onClick={(e)=> preventParentPropagation('filterButtonActiveStyle', e)}>
                        {/* Button: All */}
                        <div className={`all-tickets-button-groups-btn${activeClass('buttonAllStyle')}`} onClick={(e) => preventParentPropagation('buttonAllStyle', e)}>
                            <button className="all-tickets btn-all btn-active" style={getAllTicketsAllButtonStyle(attributes)}>All</button>
                        </div>
                        {/* Button: Open */}
                        <div className={`all-tickets-button-groups-btn${activeClass('buttonOpenStyle')}`} onClick={(e)=> preventParentPropagation('buttonOpenStyle', e)}>
                            <button className="all-tickets btn-open" style={getAllTicketsOpenButtonStyle(attributes)}>Open</button>
                        </div>
                        {/* Button: Closed */}
                        <div className={`all-tickets-button-groups-btn${activeClass('buttonClosedStyle')}`} onClick={(e)=> preventParentPropagation('buttonClosedStyle', e)}>
                            <button className="all-tickets btn-closed" style={getAllTicketsClosedButtonStyle(attributes)}>Closed</button>
                        </div>
                    </div>
                </div>
                <div className="all-tickets-header-right">
                    {/* Button: Logout */}
                    <div className={`all-tickets-action-button-group${activeClass('buttonLogoutStyle')}`} onClick={(e)=>preventParentPropagation('buttonLogoutStyle', e)}>
                        <button className="all-tickets btn-logout" style={getAllTicketsLogoutButtonStyle(attributes)}>Logout</button>
                    </div>
                    {/* Button: Create Ticket */}
                    <div className={`all-tickets-action-button-group${activeClass('buttonCreateTicketStyle')}`} onClick={(e)=> preventParentPropagation('buttonCreateTicketStyle', e)}>
                        <button className="all-tickets btn-success btn-create-ticket" style={getAllTicketsCreateTicketButtonStyle(attributes)} onClick={() => showSection('createTicket')}>Create a New Ticket</button>
                    </div>
                </div>
            </div>

            {/* All Tickets Body */}
            <div className="all-tickets-body">
                <table className="all-tickets-table table_stripe">
                    <thead className={`all-tickets-table-header${activeClass('allTicketsTableHeaderStyle')}`} onClick={() => selectedInspector('allTicketsTableHeaderStyle')}>
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
            <div className={`all-tickets-footer${activeClass('allTicketsFooterStyle')}`} style={getAllTicketsFooterStyle(attributes)} onClick={() => selectedInspector('allTicketsFooterStyle')}></div>
        </div>
    );
}
