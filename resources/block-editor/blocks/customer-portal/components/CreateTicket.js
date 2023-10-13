// Import the style functions from styles.js
import {
    getCreateTicketHeaderStyle,
    getCreateTicketHeaderTextStyle,
    getCreateTicketViewAllButtonStyle,
    getCreateTicketBodyStyle,
    getCreateTicketHintMessageStyle,
    getCreateTicketUploadButtonStyle,
    getCreateTicketCreateButtonStyle
} from '../style';
export default function CreateTicket(props) {
    const { attributes, showSection, activeClass, selectedInspector, preventParentPropagation} = props;

    return (
        <div className={"customer-portal-block-wrapper"}>
            <div className={"create-ticket-header" + activeClass("createTicketsHeaderStyle")} style={getCreateTicketHeaderStyle(attributes)}
                 onClick={() => selectedInspector("createTicketHeaderStyle")}>
                <div className={"create-ticket-header-left"}>
                    <h3 style={getCreateTicketHeaderTextStyle(attributes)}>Submit a Support Ticket</h3>
                </div>
                <div className={"create-ticket-header-right"}>
                    <div className={"create-ticket-button-groups-btn" +  activeClass("buttonViewAllStyle")}
                         onClick={(e)=> preventParentPropagation("buttonViewAllStyle", e)}
                    >
                        <button className={"create-ticket-view-all"} style={getCreateTicketViewAllButtonStyle(attributes)}
                                onClick={() =>  showSection("allTickets") }>View All
                        </button>
                    </div>

                </div>
            </div>
            <div className={"create-ticket-body" +  activeClass("createTicketsBodyStyle")} style={getCreateTicketBodyStyle(attributes)}
                 onClick={() => selectedInspector("createTicketsBodyStyle")}>
                <form className={"create-ticket-form"} >
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

                            <div className={"create-ticket-upload"}>
                                <div className={"create-ticket-button-groups-btn" + activeClass("buttonClickToUploadStyle")}
                                    onClick={(e) =>preventParentPropagation("buttonClickToUploadStyle", e)}>
                                    <button className="create-ticket-upload-button"
                                            style={getCreateTicketUploadButtonStyle(attributes)} type="button">
                                        Click to upload
                                    </button>
                                </div>

                            </div>
                            <div className="create-ticket-upload-tip" style={getCreateTicketHintMessageStyle(attributes)}>
                                Supported Types: Photos, CSV, PDF/Docs, Zip, JSON and max file size: 2.0MB
                            </div>
                            <ul className="create-ticket-upload-list"></ul>
                        </div>
                        <div className="create-ticket-custom-fields"></div>
                        <div className="create-ticket-form-item">
                            <div className="create-ticket-button">
                                <div className={"create-ticket-button-groups-btn" + activeClass("buttonCreateStyle")}
                                     onClick={(e) => preventParentPropagation("buttonCreateStyle", e)}>
                                    <button className="create-ticket-button-inner"
                                            style={getCreateTicketCreateButtonStyle(attributes)} type="button">
                                        Create
                                    </button>
                                </div>

                            </div>
                        </div>
                </form>
            </div>
        </div>
    );
}
