const {Fragment, useState, useEffect} = wp.element;
//Import styles
import './editor.scss';
//Import Inspector controls
import Inspector from './inspector';

export default function Edit({attributes, setAttributes}) {
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

    const refreshButtonStyle = {
        padding: '3px 10px',
        backgroundColor: attributes.refreshButtonBgColor,
        color: attributes.refreshButtonTextColor,
        border: 'none',
        borderRadius: '5px',
        cursor: 'pointer',
        marginRight: '12px',
    };

    const allButtonStyle = {
        fontSize: '12px',
        padding: '5px 11px',
        borderRadius: 'calc(0.25em - 1px)',
        backgroundColor: attributes.allButtonBgColor,
        color: attributes.allButtonTextColor,
        border: 'none',
        cursor: 'pointer',
        marginRight: '12px',
    };

    const closeTicketButton = {
        fontSize: '12px',
        padding: '5px 11px',
        borderRadius: 'calc(0.25em - 1px)',
        backgroundColor: attributes.closeTicketButtonBgColor,
        color: attributes.closeTicketTextColor,
        border: 'none',
        cursor: 'pointer',
    };

    const viewTicketHeaderStyle = {
        display: 'block',
        backgroundColor: attributes.viewTicketHeaderStyleBgColor,
        color: attributes.viewTicketHeaderStyleTextColor,
        padding: '20px 20px',
        borderTopRightRadius: '5px',
        borderTopLeftRadius: '5px',
    };

    const replyBoxStyle = {
        background: attributes.replyBoxBgColor,
        padding: '15px 20px'
    }

    const threadContentStyle = {
        background: attributes.threadContentBgColor,
    }

    const allTicketsAllButtonStyle = {
        color: attributes.allTicketsAllButtonTextColor,
        background: attributes.allTicketsAllButtonBgColor,
    }

    const allTicketsHeaderStyle = {
        background: attributes.allTicketsHeaderBgColor,
    }

    const allTicketsOpenButtonStyle = {
        color: attributes.allTicketsOpenButtonTextColor,
        background: attributes.allTicketsOpenButtonBgColor,
    }

    const allTicketsClosedButtonStyle = {
        color: attributes.allTicketsClosedButtonTextColor,
        background: attributes.allTicketsClosedButtonBgColor,
    }

    const allTicketsLogoutButtonStyle = {
        color: attributes.allTicketsLogoutButtonTextColor,
        background: attributes.allTicketsLogoutButtonBgColor,
    }

    const allTicketsCreateTicketButtonStyle = {
        color: attributes.allTicketsCreateTicketButtonTextColor,
        background: attributes.allTicketsCreateTicketButtonBgColor,
    }

    const allTicketsTableHeaderStyle = {
        color: attributes.allTicketsTableHeaderTextColor,
        background: attributes.allTicketsTableHeaderBgColor,
    }

    const allTicketsFooterStyle = {
        background: attributes.allTicketsFooterBgColor,
    }

    useEffect(() => {
        setShowTickets(true);
    }, true);

    return (
        <Fragment>
            <Inspector attributes={attributes} setAttributes={setAttributes}/>
            {showTickets === true ?
                <div className={'block-wrapper'}>
                    <div className={"all-tickets-header"} style={allTicketsHeaderStyle}>
                        <div class="all-tickets-header-left">
                            <div class="all-tickets-button-groups">
                                <button className="all-tickets btn-all btn-active" style={allTicketsAllButtonStyle}>All</button>
                                <button className="all-tickets btn-open" style={allTicketsOpenButtonStyle}>Open</button>
                                <button className="all-tickets btn-closed" style={allTicketsClosedButtonStyle}>Closed</button>
                            </div>
                        </div>
                        <div className="all-tickets-header-right">
                            <button className="all-tickets btn-logout" style={allTicketsLogoutButtonStyle}>Logout</button>
                            <button className="all-tickets btn-success btn-create-ticket" style={allTicketsCreateTicketButtonStyle}>Create a New Ticket</button>
                        </div>
                    </div>
                    <div className={"all-tickets-body"}>
                        <table className="all-tickets-table table_stripe">
                            <thead style={allTicketsTableHeaderStyle}>
                            <tr>
                                <th>Conversation</th>
                                <th></th>
                                <th>Status</th>
                                <th>Date</th>
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
                    <div class="all-tickets-footer" style={allTicketsFooterStyle}></div>

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
                            <div className={'show-ticket-header'} style={viewTicketHeaderStyle}>
                                <div className={'show-th-header'}>
                                    <hgroup>
                                        <div className={'show-tk-subject'}>
                                            <h2 title="show-tk-edit-subject">
                                                <span className="show-ticket-id">#746</span> Check htttp
                                            </h2>
                                            <div className={"show-tk-tags"}>
                                        <span className={"show-badge show-badge-new"}>
                                            new
                                        </span>
                                            </div>
                                        </div>
                                        <div className={"show-tk-actions"}>
                                            <button style={refreshButtonStyle}>
                                                &#8635;
                                            </button>
                                            <a
                                                onClick={showTicketsList}
                                                style={allButtonStyle}
                                            >
                                                All
                                            </a>
                                            <button
                                                style={closeTicketButton}
                                            >
                                                Close Ticket
                                            </button>
                                        </div>
                                    </hgroup>
                                </div>
                            </div>
                            <div className={'show-ticket-body'}>
                                <div className={'show-reply-box'} style={replyBoxStyle}>
                                    <textarea className="show-reply-text"
                                              placeholder="Click Here to Write a reply"></textarea>
                                </div>
                                <div className="show-threads-container">
                                    <article className="show-thread conversion-starter">
                                        <div className="show-thread-content" style={threadContentStyle}>
                                            <section className="show-avatar">
                                                <img src="https://secure.gravatar.com/avatar/?s=96&amp;d=mm&amp;r=g"
                                                     alt="auth test"/>
                                            </section>
                                            <section className="show-thread-wrap">
                                                <section className="show-thread-message">
                                                    <div className="show-thread-head">
                                                        <div className="show-thread-title">
                                                            <strong>You</strong> started the conversation
                                                        </div>
                                                        <div className="show-thread-actions">2023-08-30T09:32:16+00:00
                                                        </div>
                                                    </div>
                                                    <div className="show-thread-body">
                                                        <p>Check htttp</p>
                                                    </div>
                                                </section>
                                            </section>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>
                        : null}
        </Fragment>
    );
}
