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

import './create_ticket.scss';

export const CreateTicketBlock = props => {
    const {attributes: blockAttributes, setAttributes, showSection} = props;

    const [subject, setSubject] = useState('');
    const [details, setDetails] = useState('');
    const [priority, setPriority] = useState('Normal');
    const [name, setName] = useState('');
    const [activeTab, setActiveTab] = useState('Visual');
    const [nameError, setNameError] = useState(false);

    const blockStyles = {
        borderRadius: `${blockAttributes.createTicketBorderRadius || 0}px`,
    };

    const inputStyles = {
        borderRadius: `${blockAttributes.createTicketInputBorderRadius || 8}px`,
    };

    const buttonStyles = {
        borderRadius: `${blockAttributes.createTicketButtonBorderRadius || 0}px`,
        backgroundColor: blockAttributes.createTicketButtonBackgroundColor || '#1E1E1E',
        color: blockAttributes.createTicketButtonTextColor || '#ffffff',
    };

    const tabStyles = {
        borderRadius: `${blockAttributes.createTicketTabBorderRadius || 4}px`,
    };

    const handleSubmit = () => {
        // Validate required fields
        if (!name) {
            setNameError(true);
            return;
        }

        // Handle form submission
        console.log('Ticket submitted:', {subject, details, priority, name});
        showSection('allTickets');
    };

    return (
        <Fragment>
            <InspectorControls>
                <PanelBody title={__('General Style Settings')} initialOpen={true}>
                    <RangeControl
                        label={__('Border Radius')}
                        value={blockAttributes.createTicketBorderRadius || 0}
                        onChange={value => setAttributes({createTicketBorderRadius: value})}
                        min={0}
                        max={20}
                    />
                </PanelBody>
                <PanelBody title={__('Input Style Settings')} initialOpen={false}>
                    <RangeControl
                        label={__('Input Border Radius')}
                        value={blockAttributes.createTicketInputBorderRadius || 8}
                        onChange={value => setAttributes({createTicketInputBorderRadius: value})}
                        min={0}
                        max={20}
                    />
                </PanelBody>
                <PanelBody title={__('Button Style Settings')} initialOpen={false}>
                    <RangeControl
                        label={__('Button Border Radius')}
                        value={blockAttributes.createTicketButtonBorderRadius || 8}
                        onChange={value => setAttributes({createTicketButtonBorderRadius: value})}
                        min={0}
                        max={20}
                    />

                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Button Text Color')}
                        </label>
                        <ColorPalette
                            value={blockAttributes.createTicketButtonTextColor}
                            onChange={value => setAttributes({createTicketButtonTextColor: value})}
                        />
                    </div>

                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Button Background Color')}
                        </label>
                        <ColorPalette
                            value={blockAttributes.createTicketButtonBackgroundColor}
                            onChange={value => setAttributes({createTicketButtonBackgroundColor: value})}
                        />
                    </div>
                </PanelBody>
                <PanelBody title={__('Tab Style Settings')} initialOpen={false}>
                    <RangeControl
                        label={__('Tab Border Radius')}
                        value={blockAttributes.createTicketTabBorderRadius || 4}
                        onChange={value => setAttributes({createTicketTabBorderRadius: value})}
                        min={0}
                        max={20}
                    />
                </PanelBody>
            </InspectorControls>

            <div className="fs_back_nav">
                <button className="fs_back_button" onClick={() => showSection('allTickets')}>
                    <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M2.20437 4.9992L5.91687 8.7117L4.85637 9.7722L0.083374 4.9992L4.85637 0.226196L5.91687 1.2867L2.20437 4.9992Z"
                            fill="#0E121B"
                        />
                    </svg>
                    <span>Back to All Tickets</span>
                </button>
            </div>

            <div className="fs_create_ticket" style={blockStyles}>
                <div className="fs_create_ticket_header">
                    <h2 className="fs_form_title">Submit a Support Ticket</h2>
                </div>

                <div className="fs_form_container">
                    <div className="fs_form_group">
                        <label className="fs_form_label">Subject</label>
                        <input
                            type="text"
                            className="fs_form_input"
                            placeholder="What's this support ticket about?"
                            value={subject}
                            onChange={(e) => setSubject(e.target.value)}
                            style={inputStyles}
                        />
                    </div>

                    <div className="fs_form_group">
                        <label className="fs_form_label">Ticket Details</label>
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
                    </div>

                    <div className="fs_form_group">
                        <label className="fs_form_label">File Upload</label>
                        <button className="fs_browse_file_button" style={inputStyles}>Browse File</button>
                        <div className="fs_upload_text_container">
                            <p className="fs_upload_subtext">(Supported Types: Photos, CSV, PDF/Docs, Zip, JSON and max
                                file size: 2.0MB)</p>
                        </div>
                    </div>

                    <div className="fs_form_group">
                        <label className="fs_form_label">Priority</label>
                        <div className="fs_dropdown_container">
                            <select
                                className="fs_form_dropdown"
                                value={priority}
                                onChange={(e) => setPriority(e.target.value)}
                                style={inputStyles}
                            >
                                <option value="Normal">Normal</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                    </div>

                    <div className="fs_form_group">
                        <label className="fs_form_label">Your Name</label>
                        <input
                            type="text"
                            className={`fs_form_input ${nameError ? 'fs_input_error' : ''}`}
                            placeholder="Enter your name"
                            value={name}
                            onChange={(e) => {
                                setName(e.target.value);
                                setNameError(false);
                            }}
                            style={inputStyles}
                        />
                        {nameError && (
                            <div className="fs_error_message">
                                <span className="fs_error_icon">!</span> This is a hint text to help user.
                            </div>
                        )}
                    </div>

                    <div className="fs_form_actions">
                        <button
                            className="fs_create_ticket_submit_button"
                            onClick={handleSubmit}
                            style={buttonStyles}
                        >
                            Create Ticket
                        </button>
                    </div>
                </div>
            </div>
        </Fragment>
    );
};

export default CreateTicketBlock;
