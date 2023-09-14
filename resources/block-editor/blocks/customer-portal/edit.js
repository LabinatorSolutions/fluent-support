const {Fragment, useState, useEffect} = wp.element;
//Import styles

import './editor.scss';

// Import the style functions from styles.js
import {
    getAllTicketsHeaderStyle,
    getAllTicketsAllButtonStyle,
    getAllTicketsOpenButtonStyle,
    getAllTicketsClosedButtonStyle,
    getAllTicketsLogoutButtonStyle,
    getAllTicketsCreateTicketButtonStyle,
    getAllTicketsTableHeaderStyle,
    getAllTicketsFooterStyle,
    getAllTicketsTableRowStyle,

    // Import  View Ticket style from here
    getViewTicketHeaderStyle,
    getViewTicketIdStyle,
    getViewTicketRefreshButtonStyle,
    getViewTicketAllButtonStyle,
    getViewTicketCloseButtonStyle,
    getViewTicketPageBodyStyle,
    getViewTicketAgentThreadRibbonTailStyle,
    getViewTicketAgentThreadRibbonHeaderStyle,
    getViewTicketThreadStarterStyle,
    getViewTicketThreadStarterTailStyle,
    getViewTicketThreadFollowerTailStyle,
    getViewTicketThreadFollowerStyle,

    //Import create ticket style from here
    getCreateTicketHeaderStyle,
    getCreateTicketHeaderTextStyle,
    getCreateTicketViewAllButtonStyle,
    getCreateTicketBodyStyle,
    getCreateTicketFormStyle,
    getCreateTicketHintMessageStyle,
    getCreateTicketUploadButtonStyle,
    getCreateTicketCreateButtonStyle

    // Import other style functions here...
} from './style';

//Import Inspector controls
import Inspector from './inspector';

export default function Edit({attributes, setAttributes}) {
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

    useEffect(() => {
        setShowTickets(true);
    }, true);

    return (
        <Fragment>
            <Inspector attributes={attributes} setAttributes={setAttributes} showTickets={showTickets}
                       createTicket={showForm} viewTicket={showTicket}/>
            {showTickets === true ?
                <div className={'block-wrapper'}>
                    <div className={"all-tickets-header"} style={getAllTicketsHeaderStyle(attributes)}>
                        <div className="all-tickets-header-left">
                            <div className="all-tickets-button-groups">
                                <button className="all-tickets btn-all btn-active"
                                        style={getAllTicketsAllButtonStyle(attributes)}>All
                                </button>
                                <button className="all-tickets btn-open"
                                        style={getAllTicketsOpenButtonStyle(attributes)}>Open
                                </button>
                                <button className="all-tickets btn-closed"
                                        style={getAllTicketsClosedButtonStyle(attributes)}>Closed
                                </button>
                            </div>
                        </div>
                        <div className="all-tickets-header-right">
                            <button className="all-tickets btn-logout"
                                    style={getAllTicketsLogoutButtonStyle(attributes)}>Logout
                            </button>
                            <button className="all-tickets btn-success btn-create-ticket"
                                    style={getAllTicketsCreateTicketButtonStyle(attributes)}
                                    onClick={showCreateTicket}>Create a New
                                Ticket
                            </button>
                        </div>
                    </div>
                    <div className={"all-tickets-body"}>
                        <table className="all-tickets-table table_stripe">
                            <thead style={getAllTicketsTableHeaderStyle(attributes)}>
                            <tr>
                                <th style={getAllTicketsTableHeaderStyle(attributes)}>Conversation</th>
                                <th style={getAllTicketsTableHeaderStyle(attributes)}></th>
                                <th style={getAllTicketsTableHeaderStyle(attributes)}>Status</th>
                                <th style={getAllTicketsTableHeaderStyle(attributes)}>Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr onClick={viewTicket} style={getAllTicketsTableRowStyle(attributes)}>
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
                    <div className="all-tickets-footer" style={getAllTicketsFooterStyle(attributes)}></div>

                </div>
                : showForm === true ?
                    <div className={'block-wrapper'}>
                        <div className={'create-ticket-header'} style={getCreateTicketHeaderStyle(attributes)}>
                            <div className={'create-ticket-header-left'}>
                                <h3 style={getCreateTicketHeaderTextStyle(attributes)}>Submit a Support Ticket</h3>
                            </div>
                            <div className={'create-ticket-header-right'}>
                                <button className={'create-ticket-view-all'} style={getCreateTicketViewAllButtonStyle(attributes)}
                                        onClick={() => showTicketsList()}>View All
                                </button>
                            </div>
                        </div>
                        <div className={'create-ticket-body'} style={getCreateTicketBodyStyle(attributes)}>
                            <form className="create-ticket-form" style={getCreateTicketFormStyle(attributes)}>
                                <div className="create-ticket-form-item">
                                    <label htmlFor="subject" className="create-ticket-label">
                                        Subject heading new
                                    </label>
                                    <div className="create-ticket-input">
                                        <input
                                            type="text"
                                            id="subject"
                                            placeholder="What's this support ticket about?"
                                            className="create-ticket-input-inner"
                                        />
                                    </div>
                                </div>
                                <div className="create-ticket-form-item">
                                    <div className="create-ticket-label">Form content heading</div>
                                    <div className="create-ticket-textarea">
                                          <textarea
                                              id="content"
                                              placeholder="Enter your content here"
                                              className="create-ticket-textarea-inner"
                                          ></textarea>
                                    </div>
                                    <p className="create-ticket-help" style={getCreateTicketHintMessageStyle(attributes)}>Content help
                                        message</p>
                                </div>
                                <div className="create-ticket-attachments">
                                    <div className="create-ticket-upload">
                                        <button className="create-ticket-upload-button"
                                             style={getCreateTicketUploadButtonStyle(attributes)} type="button">
                                            Click to upload
                                        </button>
                                    </div>
                                    <div className="create-ticket-upload-tip" style={getCreateTicketHintMessageStyle(attributes)}>
                                        Supported Types: Photos, CSV, PDF/Docs, Zip, JSON and max file size: 2.0MB
                                    </div>
                                    <ul className="create-ticket-upload-list"></ul>
                                </div>
                                <div className="create-ticket-custom-fields"></div>
                                <div className="create-ticket-form-item">
                                    <div className="create-ticket-button">
                                        <button className="create-ticket-button-inner"
                                                style={getCreateTicketCreateButtonStyle(attributes)} type="button">
                                            Create
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    : showTicket === true ?
                        <div className={'block-wrapper'}>
                            <div className={'show-ticket-header'} style={getViewTicketHeaderStyle(attributes)}>
                                <div className={'show-th-header'}>
                                    <hgroup>
                                        <div className={'show-tk-subject'}>
                                            <h2 title="show-tk-edit-subject"
                                                style={getViewTicketHeaderStyle(attributes)}>
                                                <span className="show-ticket-id"
                                                      style={getViewTicketIdStyle(attributes)}>#746</span> Check htttp
                                            </h2>
                                            <div className={"show-tk-tags"}>
                                                <span className={"show-badge show-badge-new"}>  new </span>
                                            </div>
                                        </div>
                                        <div className={"show-tk-actions"}>
                                            <button className={"show-refresh-button"}
                                                    style={getViewTicketRefreshButtonStyle(attributes)}>
                                                &#8635;
                                            </button>
                                            <a
                                                onClick={showTicketsList}
                                                style={getViewTicketAllButtonStyle(attributes)}
                                            >
                                                All
                                            </a>
                                            <button
                                                className={"show-close-button"}
                                                style={getViewTicketCloseButtonStyle(attributes)}
                                            >
                                                Close Ticket
                                            </button>
                                        </div>
                                    </hgroup>
                                </div>
                            </div>
                            <div className={'show-ticket-body'} style={getViewTicketPageBodyStyle(attributes)}>
                                <div className={'show-reply-box'}>
                                    <textarea className="show-reply-text"
                                              placeholder="Click Here to Write a reply"></textarea>
                                </div>

                                <div className="show-threads-container" style={getViewTicketPageBodyStyle(attributes)}>
                                    <article className="show-thread fs_customer fs_conv_type_response" style={getViewTicketThreadFollowerTailStyle(attributes)}
                                    >
                                        <span
                                            className="fs_thread_ribbon fs_thread_ribbon_customer" style={getViewTicketThreadFollowerStyle(attributes)}>Thread Follower</span>
                                        <div className="show-thread-content">
                                            <section className="show-avatar"><img
                                                src="https://secure.gravatar.com/avatar/?s=96&amp;d=mm&amp;r=g"
                                                alt="auth test"/>
                                            </section>
                                            <section className="show-thread-wrap">
                                                <section className="show-thread-message">
                                                    <div className="show-thread-head">
                                                        <div className="show-thread-title">
                                                            <strong>You</strong> replied
                                                        </div>
                                                        <div className="show-thread-actions"
                                                        >2023-09-07T10:32:49+00:00
                                                        </div>
                                                    </div>
                                                    <div className="show-thread-body"><p>new reply</p>
                                                    </div>
                                                </section>
                                            </section>
                                        </div>
                                    </article>
                                    <article className="show-thread fs_customer fs_conv_type_response" style={getViewTicketThreadStarterTailStyle(attributes)}
                                    >
                                        <span
                                            className="fs_thread_ribbon fs_thread_ribbon_customer" style={getViewTicketThreadStarterStyle(attributes)}>Thread Starter</span>
                                        <div className="show-thread-content">
                                            <section className="show-avatar"><img
                                                src="https://secure.gravatar.com/avatar/?s=96&amp;d=mm&amp;r=g"
                                                alt="auth test"/>
                                            </section>
                                            <section className="show-thread-wrap">
                                                <section className="show-thread-message">
                                                    <div className="show-thread-head">
                                                        <div className="show-thread-title">
                                                            <strong>You</strong> replied
                                                        </div>
                                                        <div className="show-thread-actions"
                                                        >2023-09-07T10:32:49+00:00
                                                        </div>
                                                    </div>
                                                    <div className="show-thread-body"><p>new reply</p>
                                                    </div>
                                                </section>
                                            </section>
                                        </div>
                                    </article>
                                    <article className="show-thread fs_agent fs_conv_type_response" style={getViewTicketAgentThreadRibbonTailStyle(attributes)}
                                    >
                                        <span className="fs_thread_ribbon fs_thread_ribbon_agent" style={getViewTicketAgentThreadRibbonHeaderStyle(attributes)}>Support Staff</span>
                                        <div className="show-thread-content">
                                            <section className="show-avatar"><img
                                                src="https://secure.gravatar.com/avatar/?s=96&amp;d=mm&amp;r=g"
                                                alt="auth test"/>
                                            </section>
                                            <section className="show-thread-wrap">
                                                <section className="show-thread-message">
                                                    <div className="show-thread-head">
                                                        <div className="show-thread-title">
                                                            <strong>Bijit Deb</strong> replied
                                                        </div>
                                                        <div className="show-thread-actions"
                                                        >2023-09-07T10:32:49+00:00
                                                        </div>
                                                    </div>
                                                    <div className="show-thread-body"><p>check workflow</p>
                                                    </div>
                                                </section>
                                            </section>
                                        </div>
                                    </article>
                                    <article className="show-thread conversion-starter">
                                        <div className="show-thread-content">
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
                                                        <div className="show-thread-actions"
                                                             >2023-08-30T09:32:16+00:00
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
