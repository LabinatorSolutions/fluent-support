const { __ } = wp.i18n;
const { PanelBody, ColorPalette,RangeControl } = wp.components;
export default function ButtonClosedAdvanced( { attributes, setAttributes} ) {
    return (
        <PanelBody title={__('Closed', 'fluent-support')}
                   className="fst-buttonAll-tab"
        >
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonClosedBgColor}
                          onChange={(v) => setAttributes({ filterButtonClosedBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.filterButtonClosedTextColor}
                          onChange={(v) => setAttributes({ filterButtonClosedTextColor: v })}
            />
            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.filterButtonClosedBorderRadius }
                onChange={(v) => setAttributes({ filterButtonClosedBorderRadius: v })}
                min={ 0 }
                max={ 15 }
            />
        </PanelBody>
    );
}
