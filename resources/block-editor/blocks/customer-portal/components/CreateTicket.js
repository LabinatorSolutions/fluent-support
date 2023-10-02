// Import the style functions from styles.js
import {
    getCreateTicketHeaderStyle,
    getCreateTicketHeaderTextStyle,
    getCreateTicketViewAllButtonStyle,
    getCreateTicketBodyStyle,
    getCreateTicketFormStyle,
    getCreateTicketHintMessageStyle,
    getCreateTicketUploadButtonStyle,
    getCreateTicketCreateButtonStyle
} from '../style';
export default function CreateTicket(props) {
    const {attributes, showTicketsList,toggleInspector, selectedInspector} = props;
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
            <div className={'create-ticket-header' + getActiveClass('createTicketsHeaderStyle')} style={getCreateTicketHeaderStyle(attributes)}
                 onClick={() => toggleInspector('createTicketsHeaderStyle')}>
                <div className={'create-ticket-header-left'}>
                    <h3 style={getCreateTicketHeaderTextStyle(attributes)}>Submit a Support Ticket</h3>
                </div>
                <div className={'create-ticket-header-right'}>
                    <div className={'create-tickets-button-groups-btn' +  getActiveClass('buttonViewAllStyle')}
                         onClick={handleInspectorButtonClick('buttonViewAllStyle')}
                    >
                        <button className={'create-ticket-view-all'} style={getCreateTicketViewAllButtonStyle(attributes)}
                                onClick={() => showTicketsList()}>View All
                        </button>
                    </div>

                </div>
            </div>
            <div className={'create-ticket-body' +  getActiveClass('createTicketsBodyStyle')} style={getCreateTicketBodyStyle(attributes)}
                 onClick={() => toggleInspector('createTicketsBodyStyle')}>
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
    );
}
