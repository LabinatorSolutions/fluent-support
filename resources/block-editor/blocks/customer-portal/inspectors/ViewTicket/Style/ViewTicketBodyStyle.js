const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function ViewTicketBodyStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Page Body', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.viewTicketPageBodyBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ viewTicketPageBodyBgColor: v })}
            />
        </PanelBody>
    )
}
