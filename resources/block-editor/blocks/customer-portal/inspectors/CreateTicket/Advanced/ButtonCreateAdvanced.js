const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ButtonCreateAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.createTicketCreateButtonBorderWidth}
                onChange={(v) => setAttributes({createTicketCreateButtonBorderWidth: v})}
                min={1}
                max={5}
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.createTicketCreateButtonBorderRadius}
                onChange={(v) => setAttributes({createTicketCreateButtonBorderRadius: v})}
                min={0}
                max={15}
            />
        </PanelBody>
    );
}
