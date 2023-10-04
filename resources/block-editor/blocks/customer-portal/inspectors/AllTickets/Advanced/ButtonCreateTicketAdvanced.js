const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonCreateTicketAdvanced({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            <p><strong>{__('Border Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.buttonCreateTicketBorderColor}
                          onChange={(v) => setAttributes({ buttonCreateTicketBorderColor: v })}
            />
        </PanelBody>
    );
}
