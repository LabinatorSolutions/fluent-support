const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonCreateTicketStyle({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('Create a New Ticket', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.buttonCreateTicketBgColor}
                          colors={[
                                { name: 'green', color: '#67C23A' },
                                { name: 'black', color: '#000000' },
                                { name: 'gray', color: '#777777' },
                                { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ buttonCreateTicketBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.buttonCreateTicketTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ buttonCreateTicketTextColor: v })}
            />
        </PanelBody>
    );
}
