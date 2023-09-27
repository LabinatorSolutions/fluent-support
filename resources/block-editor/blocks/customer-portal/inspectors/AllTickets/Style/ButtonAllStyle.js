const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonAllStyle({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonAllBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ filterButtonAllBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonAllTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ filterButtonAllTextColor: v })}
            />
        </PanelBody>
    );
}
