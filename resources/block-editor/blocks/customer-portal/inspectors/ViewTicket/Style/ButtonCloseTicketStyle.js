const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function ButtonCloseTicketStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Close Ticket', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.closeTicketButtonBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ closeTicketButtonBgColor: v })}
            />

            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.closeTicketButtonTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ closeTicketButtonTextColor: v })}
            />
        </PanelBody>
    )
}
