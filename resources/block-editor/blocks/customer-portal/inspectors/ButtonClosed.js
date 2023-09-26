const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonClosed( { attributes, setAttributes} ) {
    return (
        <PanelBody title={__('Closed', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonClosedBgColor}
                          onChange={(v) => setAttributes({ filterButtonClosedBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonClosedTextColor}
                          onChange={(v) => setAttributes({ filterButtonClosedTextColor: v })}
            />
        </PanelBody>
    );
}
