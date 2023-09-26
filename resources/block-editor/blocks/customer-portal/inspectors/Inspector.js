const { __ } = wp.i18n;
const { InspectorControls } = wp.blockEditor;
const { PanelBody } = wp.components;

import AllTicketsInspectorControls from './AllTicketsInspectorControls';
import CreateTicketInspectorControls from './CreateTicketInspectorControls';
import ViewTicketInspectorControls from './ViewTicketInspectorControls';
const Inspector = (inspectorProps) => {
    const { attributes, setAttributes, showTickets, showTicket, showForm, mailboxes, selectedInspector} = inspectorProps;

    return (
        <InspectorControls>
            {showTickets === true ?
                <AllTicketsInspectorControls
                    attributes={attributes}
                    setAttributes={setAttributes}
                    selectedInspector={selectedInspector}/> :
                showForm === true ?
                    <CreateTicketInspectorControls attributes={attributes} setAttributes={setAttributes}
                                                   selectedInspector={selectedInspector}/> :
                    showTicket === true ?
                        <ViewTicketInspectorControls attributes={attributes} setAttributes={setAttributes}
                                                     selectedInspector={selectedInspector}/>: null
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

export default Inspector;
