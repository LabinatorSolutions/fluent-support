const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonCreateTicketStyle({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('Create a New Ticket', 'fluent-support')} initialOpen={ false }>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.buttonCreateTicketBgColor}
                          onChange={(v) => setAttributes({ buttonCreateTicketBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.buttonCreateTicketTextColor}
                          onChange={(v) => setAttributes({ buttonCreateTicketTextColor: v })}
            />
        </PanelBody>
    );
}
