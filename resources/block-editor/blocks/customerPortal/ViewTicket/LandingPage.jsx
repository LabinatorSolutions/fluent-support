const {Fragment, useState} = wp.element;
const {InspectorControls, ColorPalette} = wp.blockEditor;
const {
    PanelBody,
    SelectControl,
    TextControl,
    Button,
    Spinner,
    SearchControl,
    ToggleControl,
    RangeControl
} = wp.components;
const {__} = wp.i18n;

import './view_ticket.scss';

export const ViewTicketBlock = props => {
    const {attributes: blockAttributes, setAttributes, showSection} = props;

    const [activeTab, setActiveTab] = useState('Visual');
    const [details, setDetails] = useState('');
    const [replyText, setReplyText] = useState('');

    const blockStyles = {
        borderRadius: `${blockAttributes.viewTicketBorderRadius || 16}px`,
    };

    const inputStyles = {
        borderRadius: `${blockAttributes.viewTicketInputBorderRadius || 8}px`,
    };

    const buttonStyles = {
        borderRadius: `${blockAttributes.viewTicketButtonBorderRadius || 8}px`,
        backgroundColor: blockAttributes.viewTicketButtonBackgroundColor || '#1E1E1E',
        color: blockAttributes.viewTicketButtonTextColor || '#ffffff',
    };

    const tabStyles = {
        borderRadius: `${blockAttributes.viewTicketTabBorderRadius || 4}px`,
    };

    const handleSubmitReply = () => {
        console.log('Reply submitted:', replyText);
        setReplyText('');
    };

    const handleCloseTicket = () => {
        console.log('Ticket closed');
        showSection('allTickets');
    };

    const handleRefresh = () => {
        console.log('Refreshing ticket');
    };

    return (
        <Fragment>
            <InspectorControls>
                <PanelBody title={__('General Style Settings')} initialOpen={true}>
                    <RangeControl
                        label={__('Border Radius')}
                        value={blockAttributes.viewTicketBorderRadius || 0}
                        onChange={value => setAttributes({viewTicketBorderRadius: value})}
                        min={0}
                        max={20}
                    />
                </PanelBody>
                <PanelBody title={__('Input Style Settings')} initialOpen={false}>
                    <RangeControl
                        label={__('Input Border Radius')}
                        value={blockAttributes.viewTicketInputBorderRadius || 8}
                        onChange={value => setAttributes({viewTicketInputBorderRadius: value})}
                        min={0}
                        max={20}
                    />
                </PanelBody>
                <PanelBody title={__('Button Style Settings')} initialOpen={false}>
                    <RangeControl
                        label={__('Button Border Radius')}
                        value={blockAttributes.viewTicketButtonBorderRadius || 8}
                        onChange={value => setAttributes({viewTicketButtonBorderRadius: value})}
                        min={0}
                        max={20}
                    />

                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Button Text Color')}
                        </label>
                        <ColorPalette
                            value={blockAttributes.viewTicketButtonTextColor}
                            onChange={value => setAttributes({viewTicketButtonTextColor: value})}
                        />
                    </div>

                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Button Background Color')}
                        </label>
                        <ColorPalette
                            value={blockAttributes.viewTicketButtonBackgroundColor}
                            onChange={value => setAttributes({viewTicketButtonBackgroundColor: value})}
                        />
                    </div>
                </PanelBody>
                <PanelBody title={__('Tab Style Settings')} initialOpen={false}>
                    <RangeControl
                        label={__('Tab Border Radius')}
                        value={blockAttributes.viewTicketTabBorderRadius || 4}
                        onChange={value => setAttributes({viewTicketTabBorderRadius: value})}
                        min={0}
                        max={20}
                    />
                </PanelBody>
            </InspectorControls>

            <div className="fs_view_ticket" style={blockStyles}>
                <div className="fs_view_ticket_header">
                    <h2 className="fs_ticket_title">#2053 fluent support</h2>
                    <div className="fs_ticket_status active">Active</div>
                </div>

                <div className="fs_ticket_actions">
                    <button className="fs_refresh_button" onClick={handleRefresh}>
                        <span className="dashicons dashicons-update-alt"></span> Refresh
                    </button>
                    <button className="fs_close_ticket_button" onClick={handleCloseTicket}>
                        Close Ticket
                    </button>
                </div>

                <div className="fs_ticket_privacy_notice">
                    <span className="fs_privacy_icon">i</span>
                    <p className="fs_privacy_text">This ticket is private—only you and official support agents can view it.</p>
                    <button className="fs_close_notice_button">×</button>
                </div>

                <div className="fs_reply_container">
                    <h3 className="fs_reply_header">Write a reply</h3>
                    <div className="fs_ticket_details_container">
                        <div className="fs_ticket_details_tabs">
                            <div className="fs_tabs_wrapper" style={tabStyles}>
                                <button
                                    className={`fs_tab_button ${activeTab === 'Visual' ? 'active' : ''}`}
                                    onClick={() => setActiveTab('Visual')}
                                    style={tabStyles}
                                >
                                    Visual
                                </button>
                                <button
                                    className={`fs_tab_button ${activeTab === 'Text' ? 'active' : ''}`}
                                    onClick={() => setActiveTab('Text')}
                                    style={tabStyles}
                                >
                                    Text
                                </button>
                            </div>
                        </div>
                        <div className="fs_ticket_details_editor">
                            <div className="fs_editor_toolbar">
                                <select className="fs_paragraph_dropdown">
                                    <option>Paragraph</option>
                                </select>
                                <button className="fs_toolbar_button fs_bold"><span
                                    className="dashicons dashicons-editor-bold"></span></button>
                                <button className="fs_toolbar_button fs_italic"><span
                                    className="dashicons dashicons-editor-italic"></span></button>
                                <button className="fs_toolbar_button fs_list_bullets"><span
                                    className="dashicons dashicons-editor-ul"></span></button>
                                <button className="fs_toolbar_button fs_list_numbers"><span
                                    className="dashicons dashicons-editor-ol"></span></button>
                                <button className="fs_toolbar_button fs_link"><span
                                    className="dashicons dashicons-admin-links"></span></button>
                                <button className="fs_toolbar_button fs_quote"><span
                                    className="dashicons dashicons-editor-quote"></span></button>
                                <button className="fs_toolbar_button fs_align_left"><span
                                    className="dashicons dashicons-editor-alignleft"></span></button>
                                <button className="fs_toolbar_button fs_align_center"><span
                                    className="dashicons dashicons-editor-aligncenter"></span></button>
                                <button className="fs_toolbar_button fs_align_right"><span
                                    className="dashicons dashicons-editor-alignright"></span></button>
                                <button className="fs_toolbar_button fs_align_justify"><span
                                    className="dashicons dashicons-editor-justify"></span></button>
                                <button className="fs_toolbar_button fs_font"><span
                                    className="dashicons dashicons-editor-textcolor"></span></button>
                                <button className="fs_toolbar_button fs_undo"><span
                                    className="dashicons dashicons-undo"></span></button>
                                <button className="fs_toolbar_button fs_redo"><span
                                    className="dashicons dashicons-redo"></span></button>
                            </div>
                            <textarea
                                className="fs_editor_textarea"
                                placeholder="Enter ticket details here..."
                                value={details}
                                onChange={(e) => setDetails(e.target.value)}
                                style={inputStyles}
                            ></textarea>
                        </div>
                    </div>
                    <div className="fs_attachment_section">
                        <h4 className="fs_attachment_header">Add Attachment</h4>
                        <button className="fs_browse_file_button" style={inputStyles}>
                            <span className="dashicons dashicons-upload"></span> Browse File
                        </button>
                        <p className="fs_attachment_info">
                            (Supported Types: Photos, CSV, PDF/Docs, Zip, JSON and max file size: 2.0MB)
                        </p>
                    </div>

                    <div className="fs_reply_actions">
                        <a href="#" className="fs_reply_and_close_link">
                            Reply and Close
                        </a>
                        <button
                            className="fs_reply_button"
                            onClick={handleSubmitReply}
                        >
                            Reply <span className="dashicons dashicons-arrow-right-alt"></span>
                        </button>
                    </div>
                </div>

                <div className="fs_conversation_container">
                    <div className="fs_conversation_message fs_user_message">
                        <div className="fs_message_avatar">
                            <img src="/path/to/panda-avatar.jpg" alt="User Avatar"/>
                        </div>
                        <div className="fs_message_content">
                            <div className="fs_message_header">
                                <div className="fs_message_author">
                                    <span className="fs_author_name">You</span>
                                    <span className="fs_author_role">Thread Starter</span>
                                </div>
                            </div>
                            <div className="fs_message_text">
                                <p>hello</p>
                            </div>
                        </div>
                    </div>

                    <div className="fs_conversation_message fs_thread_start">
                        <div className="fs_message_avatar">
                            <img src="/path/to/panda-avatar.jpg" alt="User Avatar"/>
                        </div>
                        <div className="fs_message_content">
                            <div className="fs_message_header">
                                <div className="fs_message_author">
                                    <span className="fs_author_name">You</span>
                                    <span className="fs_author_info">started the conversation</span>
                                </div>
                            </div>
                            <div className="fs_message_text">
                                <p>fluent support</p>
                            </div>
                            <div className="fs_message_footer">
                                <span className="fs_message_timestamp">3 months ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Fragment>
    );
};

export default ViewTicketBlock;
