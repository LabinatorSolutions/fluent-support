// Import the style functions from styles.js
import {
    getViewTicketHeaderStyle,
    getViewTicketIdAndTitleStyle,
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
} from '../style';
export default function ViewTicket(props) {
    const { attributes, showTicketsList, toggleInspector, selectedInspector } = props;
    const getActiveClass = (ind) => {
        if (selectedInspector === ind) {
            return ' fst-block-active-components';
        }
        return '';
    }

    const handleInspectorClick = (inspector) => (e) => {
        e.stopPropagation();
        toggleInspector(inspector);
    };
    return (
        <div className={"customer-portal-block-wrapper"}>
            <div className={"show-ticket-header" + getActiveClass('viewTicketHeaderStyle')} style={getViewTicketHeaderStyle(attributes)}
                 onClick={() => toggleInspector('viewTicketHeaderStyle')}>
                <div className={"show-th-header"}>
                    <hgroup>
                        <div className={'show-tk-subject'}>
                            <h2 title="show-tk-edit-subject"
                                style={getViewTicketIdAndTitleStyle(attributes)}>
                                                <span className="show-ticket-id"
                                                      style={getViewTicketIdStyle(attributes)}>#746</span> Check htttp
                            </h2>
                            <div className={"show-tk-tags"}>
                                <span className={"show-badge show-badge-new"}>  new </span>
                            </div>
                        </div>
                        <div className={"show-tk-actions"}>
                            <div className={"show-ticket-button-groups-btn" + getActiveClass('buttonRefreshStyle')}
                                 onClick={handleInspectorClick('buttonRefreshStyle')}>
                                <button className={"show-refresh-button"}
                                        style={getViewTicketRefreshButtonStyle(attributes)}>
                                    &#8635;
                                </button>
                            </div>

                            <div className={"show-ticket-button-groups-btn" + getActiveClass('viewTicketButtonAllStyle')}
                                 onClick={handleInspectorClick('viewTicketButtonAllStyle')}>
                                <a
                                    onClick={showTicketsList}
                                    style={getViewTicketAllButtonStyle(attributes)}
                                >
                                    All
                                </a>
                            </div>

                            <div className={"show-ticket-button-groups-btn" + getActiveClass('buttonCloseTicketStyle')}
                                 onClick={handleInspectorClick('buttonCloseTicketStyle')}>
                                <button
                                    className={"show-close-button"}
                                    style={getViewTicketCloseButtonStyle(attributes)}
                                >
                                    Close Ticket
                                </button>
                            </div>

                        </div>
                    </hgroup>
                </div>
            </div>
            <div className={'show-ticket-body' + getActiveClass('viewTicketBodyStyle')} style={getViewTicketPageBodyStyle(attributes)}
                 onClick={() => toggleInspector('viewTicketBodyStyle')}>
                <div className={'show-reply-box'}>
                        <textarea className="show-reply-text"
                                  placeholder="Click Here to Write a reply"></textarea>
                </div>

                <div className="show-threads-container" style={getViewTicketPageBodyStyle(attributes)}>
                    <article className="show-thread fs_cc_customer fs_conv_type_response" style={getViewTicketThreadFollowerTailStyle(attributes)}
                    >
                        <div className={"fs_thread_follower_style" + getActiveClass('ribbonThreadFollowerStyle')} onClick={handleInspectorClick('ribbonThreadFollowerStyle')}>
                            <span className="fs_thread_ribbon fs_thread_ribbon_customer" style={getViewTicketThreadFollowerStyle(attributes)}>
                                Thread Follower
                            </span>
                        </div>
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
                        <div className={"fs_thread_starter_style" + getActiveClass('ribbonThreadStarterStyle')}  onClick={handleInspectorClick('ribbonThreadStarterStyle')}>
                            <span className="fs_thread_ribbon fs_thread_ribbon_customer" style={getViewTicketThreadStarterStyle(attributes)}>
                                Thread Starter
                            </span>
                        </div>
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
                    <article className={"show-thread fs_agent fs_conv_type_response"} style={getViewTicketAgentThreadRibbonTailStyle(attributes)}>
                        <div className={"fs_thread_ribbon_agent_style" + getActiveClass('ribbonSupportStaffStyle')} onClick={handleInspectorClick('ribbonSupportStaffStyle')}>
                            <span className={"fs_thread_ribbon fs_thread_ribbon_agent"} style={getViewTicketAgentThreadRibbonHeaderStyle(attributes)}>Support Staff</span>
                        </div>
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
    );
}
