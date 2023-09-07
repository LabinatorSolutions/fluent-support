const { Fragment, useState, useEffect} = wp.element;
//Import styles
import './editor.scss';
//Import Inspector controls
import Inspector from './inspector';
export default function Edit( { attributes, setAttributes } ) {
    const ticketHeaderStyle = {
        backgroundColor: attributes.ticketHeaderBgColor,
    }
    const ticketHeaderLeftStyle = {
        float: 'left',
    }
    const ticketHeaderRightStyle = {
        float: 'right',
    }
    const allBtnStyle = {
        backgroundColor: attributes.buttonAllBgColor,
        color: attributes.buttonAllTextColor,
        padding: '10px 20px',
    }

    const openBtnStyle = {
        backgroundColor: attributes.buttonOpenBgColor,
        color: attributes.buttonOpenTextColor,
        padding: '10px 20px',
    }

    const closedBtnStyle = {
        backgroundColor: attributes.buttonClosedBgColor,
        color: attributes.buttonClosedTextColor,
        padding: '10px 20px',
    }

    const createTicketBtnStyle = {
        backgroundColor: attributes.buttonCreateTicketBgColor,
        color: attributes.buttonCreateTicketTextColor,
        padding: '10px 20px',
    }

    const createTicketHeaderStyle = {
        backgroundColor: attributes.createTicketFormHeaderBgColor,
    }

    const [showTickets, setShowTickets] = useState(false);
    const [showForm, setShowForm] = useState(false);
    const [showTicket, setShowTicket] = useState(false);

    const showCreateTicket = () => {
        setShowForm(true);
        setShowTickets(false);
        setShowTicket(false);
    }

    const showTicketsList = () => {
        setShowForm(false);
        setShowTickets(true);
        setShowTicket(false);
    }

    const viewTicket = () => {
        setShowForm(false);
        setShowTickets(false);
        setShowTicket(true);
    }

    useEffect(()=>{
        setShowTickets(true);
    },true);

    return (
        <Fragment>
            <Inspector attributes={attributes} setAttributes={setAttributes} />
            {showTickets === true ?
                <div className={'block-wrapper'}>
                <div className={'ticket-header'} style={ticketHeaderStyle}>
                    <div className={'ticket-header-left'} style={ticketHeaderLeftStyle}>
                        <button style={allBtnStyle}>
                            All
                        </button>
                        <button style={openBtnStyle}>
                            Open
                        </button>
                        <button style={closedBtnStyle}>
                            Closed
                        </button>
                    </div>
                    <div className={'ticket-header-right'} style={ticketHeaderRightStyle}>
                        <button style={createTicketBtnStyle} onClick={showCreateTicket}>
                            Create a New Ticket
                        </button>
                    </div>
                </div>
                <div className={'ticket-tables'}>
                    <table className={'ticket-table'}>
                        <thead className={'ticket-table-header'}>
                            <tr>
                                <th>Conversation</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr onClick={viewTicket}>
                                <td>1</td>
                                <td>Test Ticket</td>
                                <td>General</td>
                            </tr>
                            <tr onClick={viewTicket}>
                                <td>2</td>
                                <td>Test Ticket</td>
                                <td>General</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div className={'ticket-footer'}>

                </div>
            </div>
            : showForm === true ?
                <div className={'block-wrapper'}>
                    <div className={'create-ticket-header'}>
                        <div className={'create-ticket-header-left'}>
                            <h3>Submit a Support Ticket</h3>
                        </div>
                        <div className={'create-ticket-header-right'}>
                            <button onClick={() => showTicketsList()}>View All</button>
                        </div>
                    </div>
                    <div className={'create-ticket-body'}>
                        <div className={'create-ticket-body-left'}>
                            <div className={'create-ticket-body-left-header'}>
                                <h3>General</h3>
                            </div>
                        </div>
                    </div>
                </div>
            : showTicket === true ?
                <div className={'block-wrapper'}>
                    <div className={'show-ticket-header'}>
                        <div className={'show-ticket-header-left'}>
                            <h3>#216 Testing demo ticket</h3>
                        </div>
                        <div className={'show-ticket-header-right'}>
                            <button>Refresh</button>
                            <button onClick={() => showTicketsList()}>All</button>
                            <button>Close Ticket</button>
                        </div>
                    </div>
                    <div className={'create-ticket-body'}>
                        <div className={'create-ticket-body-left'}>
                            <div className={'create-ticket-body-left-header'}>
                                <h3>General</h3>
                            </div>
                        </div>
                    </div>
                </div>
            : null}
        </Fragment>
    );
}
