const { __ } = wp.i18n;
const { InspectorControls } = wp.blockEditor;
const { PanelBody } = wp.components;
import AllTicketsInspectorControls from './AllTicketsInspectorControls';
import CreateTicketInspectorControls from './CreateTicketInspectorControls';
import ViewTicketInspectorControls from './ViewTicketInspectorControls';
const Index = (inspectorProps) => {
    const { attributes, setAttributes, showTickets, showTicket, showForm, mailboxes} = inspectorProps;
    return (
        <InspectorControls>
            {showTickets === true ?
                <AllTicketsInspectorControls attributes={attributes} setAttributes={setAttributes}/> :
                showForm === true ?
                    <CreateTicketInspectorControls attributes={attributes} setAttributes={setAttributes}/> :
                    showTicket === true ?
                    <ViewTicketInspectorControls attributes={attributes} setAttributes={setAttributes}/>: null
            }
            <PanelBody title={__('Default Business Inbox', 'fluent-support')} initialOpen={ false }>
                <select value={attributes.businessBoxId}
                        onChange={(v) => setAttributes({ businessBoxId: v.target.value })}
                >
                    <option value="">{__('Select a mailbox', 'fluent-support')}</option>
                    {mailboxes.map((mailbox) => {
                        return (
                            <option value={mailbox.id} key={mailbox.id}>{mailbox.name +' ('+ mailbox.box_type +')' }</option>
                        );
                    })}
                </select>
            </PanelBody>
        </InspectorControls>
    );
};

export default Index;
