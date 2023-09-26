const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonOpen( { attributes, setAttributes} ) {
    return (
        <PanelBody title={__('Open', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonOpenBgColor}
                          onChange={(v) => setAttributes({ filterButtonOpenBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonOpenTextColor}
                          onChange={(v) => setAttributes({ filterButtonOpenTextColor: v })}
            />
        </PanelBody>
    );
}
