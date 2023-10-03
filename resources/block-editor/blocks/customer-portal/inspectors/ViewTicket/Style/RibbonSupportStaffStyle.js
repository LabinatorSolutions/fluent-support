const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function RibbonSupportStaffStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Support Staff ', 'fluent-support')}>
            <p><strong>{__(' Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.ribbonSupportStaffBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ ribbonSupportStaffBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.ribbonSupportStaffTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ ribbonSupportStaffTextColor: v })}
            />
        </PanelBody>
    )
}
