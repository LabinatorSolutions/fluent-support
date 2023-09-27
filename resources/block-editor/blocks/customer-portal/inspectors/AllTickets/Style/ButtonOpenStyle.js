const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonOpenStyle( { attributes, setAttributes} ) {
    return (
        <PanelBody title={__('Open', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonOpenBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ filterButtonOpenBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonOpenTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ filterButtonOpenTextColor: v })}
            />
        </PanelBody>
    );
}
