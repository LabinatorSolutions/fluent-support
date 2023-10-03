const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function RibbonThreadStarterStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Thread Starter', 'fluent-support')}>
            <p><strong>{__('Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.viewTicketThreadStarterBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ viewTicketThreadStarterBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.viewTicketThreadStarterTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ viewTicketThreadStarterTextColor: v })}
            />
        </PanelBody>
    )
}
