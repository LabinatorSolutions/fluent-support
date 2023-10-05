const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ButtonViewAllAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.createTicketViewAllButtonBorderWidth}
                onChange={(v) => setAttributes({createTicketViewAllButtonBorderWidth: v})}
                min={1}
                max={5}
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.createTicketViewAllButtonBorderRadius}
                onChange={(v) => setAttributes({createTicketViewAllButtonBorderRadius: v})}
                min={0}
                max={15}
            />
        </PanelBody>
    );
}
