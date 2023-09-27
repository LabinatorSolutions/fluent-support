const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonAllAdvanced({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonAllBgColor}
                          onChange={(v) => setAttributes({ filterButtonAllBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonAllTextColor}
                          onChange={(v) => setAttributes({ filterButtonAllTextColor: v })}
            />
        </PanelBody>
    );
}
