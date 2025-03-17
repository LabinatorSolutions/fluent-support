const { Fragment, useState } = wp.element;
const { __ } = wp.i18n;

import './create_ticket.scss';
import CreateTicketInspectorControls, { generateStyles } from './InspectorSettings';

export const CreateTicketBlock = props => {
    const { attributes: blockAttributes, setAttributes, showSection } = props;

    const [subject, setSubject] = useState('');
    const [details, setDetails] = useState('');
    const [priority, setPriority] = useState('Normal');
    const [name, setName] = useState('');
    const [activeTab, setActiveTab] = useState('Visual');
    const [nameError, setNameError] = useState(false);

    const {
        blockStyles,
        primaryButtonStyles,
        secondaryButtonStyles,
        inputFieldStyle
    } = generateStyles(blockAttributes);

    return (
        <Fragment>
            <CreateTicketInspectorControls
                attributes={blockAttributes}
                setAttributes={setAttributes}
            />

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
                            style={inputFieldStyle}
                        />
                    </div>

                    <div className="fs_form_group">
                        <label className="fs_form_label">Ticket Details</label>
                        <div className="fs_ticket_details_container">
                            <div className="fs_ticket_details_tabs">
                                <div className="fs_tabs_wrapper">
                                    <button
                                        className={`fs_tab_button ${activeTab === 'Visual' ? 'active' : ''}`}
                                        onClick={() => setActiveTab('Visual')}
                                    >
                                        Visual
                                    </button>
                                    <button
                                        className={`fs_tab_button ${activeTab === 'Text' ? 'active' : ''}`}
                                        onClick={() => setActiveTab('Text')}
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
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <div className="fs_form_group">
                        <label className="fs_form_label">File Upload</label>
                        <button className="fs_browse_file_button" style={secondaryButtonStyles}>Browse File</button>
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
                                style={inputFieldStyle}
                                onChange={(e) => setPriority(e.target.value)}
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
                            style={inputFieldStyle}
                            onChange={(e) => {
                                setName(e.target.value);
                                setNameError(false);
                            }}
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
                            style={primaryButtonStyles}
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
