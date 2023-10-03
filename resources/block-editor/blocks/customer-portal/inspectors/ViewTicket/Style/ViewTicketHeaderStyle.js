const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function ViewTicketHeaderStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Page Footer', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.viewTicketHeaderStyleBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ viewTicketHeaderStyleBgColor: v })}
            />

            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.viewTicketHeaderStyleTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ viewTicketHeaderStyleTextColor: v })}
            />

            <p><strong>{__('Ticket Id Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.viewTicketHeaderIdTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ viewTicketHeaderIdTextColor: v })}
            />
        </PanelBody>
    )
}
