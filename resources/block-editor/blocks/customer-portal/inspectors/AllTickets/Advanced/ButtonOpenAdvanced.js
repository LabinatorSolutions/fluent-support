const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonOpenAdvanced( { attributes, setAttributes} ) {
    return (
        <PanelBody title={__('Open', 'fluent-support')}>
            <p><strong>{__('Border Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonOpenBorderColor}
                          onChange={(v) => setAttributes({ filterButtonOpenBorderColor: v })}
            />

            filterButtonOpenBorderColor
        </PanelBody>
    );
}
