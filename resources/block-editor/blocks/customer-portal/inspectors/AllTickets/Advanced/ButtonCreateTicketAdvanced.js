const { __ } = wp.i18n;
const { PanelBody, RangeControl } = wp.components;
export default function ButtonCreateTicketAdvanced({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('Create Ticket', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.buttonCreateTicketBorderWidth}
                onChange={(v) => setAttributes({buttonCreateTicketBorderWidth: v})}
                min={1}
                max={5}
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.buttonCreateTicketBorderRadius}
                onChange={(v) => setAttributes({buttonCreateTicketBorderRadius: v})}
                min={0}
                max={15}
            />
        </PanelBody>
    );
}
