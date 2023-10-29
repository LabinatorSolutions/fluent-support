// Import the style functions from styles.js
import {
    getViewTicketHeaderStyle,
    getViewTicketIdAndTitleStyle,
    getViewTicketIdStyle,
    getViewTicketRefreshButtonStyle,
    getViewTicketAllButtonStyle,
    getViewTicketCloseButtonStyle,
    getViewTicketPageBodyStyle,
    GetThreadItemPersonTextColor,
    getViewTicketAgentThreadRibbonTailStyle,
    getViewTicketAgentThreadRibbonHeaderStyle,
    getViewTicketThreadStarterStyle,
    getViewTicketThreadStarterTailStyle,
    getViewTicketThreadFollowerTailStyle,
    getViewTicketThreadFollowerStyle,
    GetThreadItemActionTextColor,
    GetThreadItemContentColor, GetThreadItemDateTextColor,
} from '../style';
import ThreadItem from './ThreadItem';
/**
 * The ViewTicket component displays ticket details and related threads for block.
 * @param {Object} props - Component props.
 * @param {Object} props.attributes - Component attributes.
 * @param {Function} props.showSection - Function to show a specific section.
 * @param {Function} props.selectedInspector - Function to toggle the inspector.
 * @param {Function} props.preventParentPropagation - Function to toggle the inspector and prevent parent.
 */
export default function ViewTicket(props) {
    const { attributes, showSection, activeClass, selectedInspector, preventParentPropagation } = props;

    return (
        <div className={"customer-portal-block-wrapper"}>
            {/* Show Ticket Header */}
            <div className={"show-ticket-header" + activeClass('viewTicketHeaderStyle')} style={getViewTicketHeaderStyle(attributes)}
                 onClick={() => selectedInspector('viewTicketHeaderStyle')}>
                <div className={"show-th-header"}>
                    <hgroup>
                        <div className="show-tk-subject">
                            <h2 title="show-tk-edit-subject" style={getViewTicketIdAndTitleStyle(attributes)}>
                                <span className="show-ticket-id" style={getViewTicketIdStyle(attributes)}>#746</span> Check htttp
                            </h2>
                            <div className="show-tk-tags">
                                <span className="show-badge show-badge-new">  new </span>
                            </div>
                        </div>
                        <div className="show-tk-actions">
                            {/* Button Refresh */}
                            <div className={`show-ticket-button-groups-btn${activeClass('buttonRefreshStyle')}`} onClick={(e)=> preventParentPropagation('buttonRefreshStyle', e)}>
                                <button className="show-refresh-button" style={getViewTicketRefreshButtonStyle(attributes)}>
                                    &#8635;
                                </button>
                            </div>
                            {/* View Ticket All */}
                            <div className={`show-ticket-button-groups-btn${activeClass('viewTicketButtonAllStyle')}`} onClick={(e)=>preventParentPropagation('viewTicketButtonAllStyle', e)}>
                                <a onClick={() => showSection('allTickets')} style={getViewTicketAllButtonStyle(attributes)}>All</a>
                            </div>
                            {/* Button Close Ticket */}
                            <div className={`show-ticket-button-groups-btn${activeClass('buttonCloseTicketStyle')}`} onClick={(e)=>preventParentPropagation('buttonCloseTicketStyle', e)}>
                                <button className="show-close-button" style={getViewTicketCloseButtonStyle(attributes)}>Close Ticket</button>
                            </div>
                        </div>
                    </hgroup>
                </div>
            </div>
            {/* Show Ticket Body */}
            <div className={'show-ticket-body' + activeClass('viewTicketBodyStyle')} style={getViewTicketPageBodyStyle(attributes)}
                 onClick={() => selectedInspector('viewTicketBodyStyle')}>
                <div className={'show-reply-box'}>
                        <textarea className="show-reply-text"
                                  placeholder="Click Here to Write a reply"></textarea>
                </div>
                {/* Display thread items*/}
                <div className="show-threads-container" style={getViewTicketPageBodyStyle(attributes)}>
                    {/* Display thread items Thread Follower*/}
                    <ThreadItem
                        threadArticleStyleClass={'fs_cc_customer fs_conv_type_response'}
                        getThreadTailStyle={getViewTicketThreadFollowerTailStyle(attributes)}
                        threadStyleClass={'fs_thread_follower_style'}
                        activeClassName={'ribbonThreadFollowerStyle'}
                        activeClass={activeClass}
                        ribbonTypeStyleClass={'fs_thread_ribbon fs_thread_ribbon_customer'}
                        getRibbonHeaderStyle={getViewTicketThreadFollowerStyle(attributes)}
                        preventParentPropagation={preventParentPropagation}
                        ribbonStyle={'ribbonThreadFollowerStyle'}
                        threadContent={{
                            type: 'Thread Follower',
                            person: 'John Doe',
                            avatar: 'https://secure.gravatar.com/avatar/?s=96&amp;d=mm&amp;r=g',
                            alterAvatar: 'John Doe',
                            title: 'replied',
                            date: '2023-09-07T10:32:49+00:00',
                            body: 'I think I can add some more details like the following'
                        }}
                        pageBodyStyle={{
                            nameColor: GetThreadItemPersonTextColor(attributes),
                            actionColor: GetThreadItemActionTextColor(attributes),
                            contentColor: GetThreadItemContentColor(attributes),
                            dateColor: GetThreadItemDateTextColor(attributes),
                        }}
                    />
                    {/* Display thread items Thread Starter*/}
                    <ThreadItem
                        threadArticleStyleClass={'fs_customer fs_conv_type_response'}
                        getThreadTailStyle={getViewTicketThreadStarterTailStyle(attributes)}
                        threadStyleClass={'fs_thread_starter_style'}
                        activeClassName={'ribbonThreadStarterStyle'}
                        activeClass={activeClass}
                        ribbonTypeStyleClass={'fs_thread_ribbon fs_thread_ribbon_customer'}
                        getRibbonHeaderStyle={getViewTicketThreadStarterStyle(attributes)}
                        preventParentPropagation={preventParentPropagation}
                        ribbonStyle={'ribbonThreadStarterStyle'}
                        threadContent={{
                            type: 'Thread Starter',
                            person: 'You',
                            avatar: 'https://secure.gravatar.com/avatar/?s=96&amp;d=mm&amp;r=g',
                            alterAvatar: 'Auth Test',
                            title: 'replied',
                            date: '2023-09-07T10:32:49+00:00',
                            body: 'The details description was missing in the previous mail. Please find the details description below.'
                        }}
                        pageBodyStyle={{
                            nameColor: GetThreadItemPersonTextColor(attributes),
                            actionColor: GetThreadItemActionTextColor(attributes),
                            contentColor: GetThreadItemContentColor(attributes),
                            dateColor: GetThreadItemDateTextColor(attributes),
                        }}
                    />
                    {/* Display thread items Support Agent*/}
                    <ThreadItem
                        threadArticleStyleClass={'fs_agent fs_conv_type_response'}
                        getThreadTailStyle={getViewTicketAgentThreadRibbonTailStyle(attributes)}
                        threadStyleClass={'fs_thread_ribbon_agent_style'}
                        activeClassName={'ribbonSupportStaffStyle'}
                        activeClass={activeClass}
                        ribbonTypeStyleClass={'fs_thread_ribbon fs_thread_ribbon_agent'}
                        getRibbonHeaderStyle={getViewTicketAgentThreadRibbonHeaderStyle(attributes)}
                        preventParentPropagation={preventParentPropagation}
                        ribbonStyle={'ribbonSupportStaffStyle'}
                        threadContent={{
                            type: 'Support Staff',
                            person: 'Russel Deb',
                            avatar: 'https://secure.gravatar.com/avatar/?s=96&amp;d=mm&amp;r=g',
                            alterAvatar: 'Russel Deb',
                            title: 'replied',
                            date: '2023-09-07T10:32:49+00:00',
                            body: 'check workflow'
                        }}
                        pageBodyStyle={{
                            nameColor: GetThreadItemPersonTextColor(attributes),
                            actionColor: GetThreadItemActionTextColor(attributes),
                            contentColor: GetThreadItemContentColor(attributes),
                            dateColor: GetThreadItemDateTextColor(attributes),
                        }}
                    />
                    {/* Display thread items conversation starter*/}
                    <ThreadItem
                        threadArticleStyleClass={'conversion-starter'}
                        getThreadTailStyle={{}}
                        threadStyleClass={'show-thread-content'}
                        activeClassName={''}
                        activeClass={activeClass}
                        ribbonTypeStyleClass={''}
                        getRibbonHeaderStyle={{}}
                        preventParentPropagation={''}
                        ribbonStyle={''}
                        threadContent={{
                            type: 'starter',
                            person: 'You',
                            avatar: 'https://secure.gravatar.com/avatar/?s=96&amp;d=mm&amp;r=g',
                            alterAvatar: 'Auth Test',
                            title: 'started the conversation',
                            date: '2023-08-30T09:32:16+00:00',
                            body: 'I was trying to install the plugin and it is not working. Please check the following details.'
                        }}
                        pageBodyStyle={{
                            nameColor: GetThreadItemPersonTextColor(attributes),
                            actionColor: GetThreadItemActionTextColor(attributes),
                            contentColor: GetThreadItemContentColor(attributes),
                            dateColor: GetThreadItemDateTextColor(attributes),
                        }}
                    />
                </div>
            </div>
        </div>
    );
}
