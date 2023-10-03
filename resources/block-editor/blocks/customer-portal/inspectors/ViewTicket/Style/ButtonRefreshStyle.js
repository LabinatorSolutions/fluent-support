const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function ButtonRefreshStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Refresh', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.refreshButtonBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ refreshButtonBgColor: v })}
            />

            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.refreshButtonTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ refreshButtonTextColor: v })}
            />
        </PanelBody>
    )
}
