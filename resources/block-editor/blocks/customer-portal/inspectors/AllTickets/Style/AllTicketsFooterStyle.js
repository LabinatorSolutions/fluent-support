const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function AllTicketsFooterStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Page Footer', 'fluent-support')} initialOpen={ false }>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.allTicketsFooterBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ allTicketsFooterBgColor: v })}
            />
        </PanelBody>
    )
}
