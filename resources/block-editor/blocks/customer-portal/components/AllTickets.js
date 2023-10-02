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
// import AllTicketsFooterStyle from "../inspectors/AllTickets/Style/AllTicketsFooterStyle";

export default function AllTickets(props) {
    const {attributes, showCreateTicket, viewTicket, toggleInspector, selectedInspector} = props;
    const getActiveClass = (ind) => {
        if (selectedInspector === ind) {
            return ' fst-block-active-components';
        }
        return '';
    }

    const handleInspectorButtonClick = (inspector) => (e) => {
        e.stopPropagation();
        toggleInspector(inspector);
    };
    return (
        <div className={'customer-portal-block-wrapper'}>
            <div className={"all-tickets-header" + getActiveClass('allTicketsHeaderStyle')} style={getAllTicketsHeaderStyle(attributes)}
                 onClick={() => toggleInspector('allTicketsHeaderStyle')}>
                <div className="all-tickets-header-left">
                    <div className="all-tickets-button-groups">
                        <div className={"all-tickets-button-groups-btn" + getActiveClass('buttonAllStyle')}
                             onClick={handleInspectorButtonClick('buttonAllStyle')}>
                            <button className="all-tickets btn-all btn-active"
                                    style={getAllTicketsAllButtonStyle(attributes)}>All
                            </button>
                        </div>
                        <div className={"all-tickets-button-groups-btn" + getActiveClass('buttonOpenStyle')}
                             onClick={handleInspectorButtonClick('buttonOpenStyle')}>
                            <button className="all-tickets btn-open"
                                    style={getAllTicketsOpenButtonStyle(attributes)}>Open
                            </button>
                        </div>
                        <div className={"all-tickets-button-groups-btn" + getActiveClass('buttonClosedStyle')}
                             onClick={handleInspectorButtonClick('buttonClosedStyle')}>
                            <button className="all-tickets btn-closed"
                                    style={getAllTicketsClosedButtonStyle(attributes)}>Closed
                            </button>
                        </div>
                    </div>
                </div>
                <div className="all-tickets-header-right">
                        <div className={"all-tickets-action-button-group" + getActiveClass('buttonLogoutStyle')}
                             onClick={handleInspectorButtonClick('buttonLogoutStyle')}>
                            <button className="all-tickets btn-logout"
                                    style={getAllTicketsLogoutButtonStyle(attributes)}>Logout
                            </button>
                        </div>
                        <div className={"all-tickets-action-button-group" + getActiveClass('buttonCreateTicketStyle')}
                             onClick={handleInspectorButtonClick('buttonCreateTicketStyle')}>
                            <button className="all-tickets btn-success btn-create-ticket"
                                    style={getAllTicketsCreateTicketButtonStyle(attributes)}
                                    onClick={showCreateTicket}>Create a New
                                Ticket
                            </button>
                        </div>
                </div>
            </div>
            <div className={"all-tickets-body"}>
                <table className="all-tickets-table table_stripe">
                        <thead className={"all-tickets-table-header" + getActiveClass('allTicketsTableHeaderStyle')}
                               onClick={() => toggleInspector('allTicketsTableHeaderStyle')}>
                        <tr>
                            <th style={getAllTicketsTableHeaderStyle(attributes)}>Conversation</th>
                            <th style={getAllTicketsTableHeaderStyle(attributes)}></th>
                            <th style={getAllTicketsTableHeaderStyle(attributes)}>Status</th>
                            <th style={getAllTicketsTableHeaderStyle(attributes)}>Date</th>
                        </tr>
                        </thead>
                    <tbody>
                    <tr onClick={viewTicket}>
                        <td><a className={"ticket-preview"}><strong>Test ticket 1</strong>
                            <div className="prev_text_parent"><p className="ticket-preview-text">Test ticket
                                1</p>
                            </div>
                        </a></td>
                        <td className="ticket-thread-count"><span className="thread-count">0</span></td>
                        <td className="ticket-status"><span
                            className="ticket-status-content">new</span></td>
                        <td className="fs_tk_date"><span className="fs_tk_date">20 hours ago</span></td>
                    </tr>
                    <tr onClick={viewTicket}>
                        <td><a className={"ticket-preview"}><strong>Test ticket 2</strong>
                            <div className="prev_text_parent"><p className="ticket-preview-text">Test ticket
                                2</p>
                            </div>
                        </a></td>
                        <td className="ticket-thread-count"><span className="thread-count">0</span></td>
                        <td className="ticket-status"><span
                            className="ticket-status-content">new</span></td>
                        <td className="fs_tk_date"><span className="fs_tk_date">19 hours ago</span></td>
                    </tr>
                    </tbody>
                </table>
            </div>
                <div className={"all-tickets-footer" + getActiveClass('allTicketsFooterStyle')} style={getAllTicketsFooterStyle(attributes)} onClick={() => toggleInspector('allTicketsFooterStyle')}></div>
        </div>
    );
}
