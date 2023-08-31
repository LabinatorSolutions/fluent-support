const { __ } = wp.i18n;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, BaseControl, ColorPalette } = wp.components;

const Inspector = ({ attributes, setAttributes }) => {
    //const { buttonAllBgColor, buttonAllTextColor,  } = attributes;

    return (
        <InspectorControls>
            <PanelBody title={__('Fs All Tickets Settings ', 'fluent-support')}>
                <BaseControl label={__('Background Color', 'fluent-support')} id="color">
                    <ColorPalette
                        colors={[
                            { name: 'red', color: '#f00' },
                            { name: 'white', color: '#fff' },
                            { name: 'blue', color: '#00f' },
                        ]}
                        value={attributes.buttonAllBgColor}
                        onChange={(v) => setAttributes({ buttonAllBgColor: v })}
                    />
                </BaseControl>

                <BaseControl label={__('Text Color', 'fluent-support')} id="text_color">
                    <ColorPalette
                        colors={[
                            { name: 'red', color: '#f00' },
                            { name: 'white', color: '#fff' },
                            { name: 'blue', color: '#00f' },
                        ]}
                        value={attributes.buttonAllTextColor}
                        onChange={(v) => setAttributes({ buttonAllTextColor: v })}
                    />
                </BaseControl>
            </PanelBody>

            <PanelBody title={__('Open Tickets Button Settings ', 'fluent-support')}>
                <BaseControl label={__('Background Color', 'fluent-support')} id="btn_bg_color">
                    <ColorPalette
                        colors={[
                            { name: 'red', color: '#f00' },
                            { name: 'white', color: '#fff' },
                            { name: 'blue', color: '#00f' },
                        ]}
                        value={attributes.buttonOpenBgColor}
                        onChange={(v) => setAttributes({ buttonOpenBgColor: v })}
                    />
                </BaseControl>

                <BaseControl label={__('Text Color', 'fluent-support')} id="open_btn_text_color">
                    <ColorPalette
                        colors={[
                            { name: 'red', color: '#f00' },
                            { name: 'white', color: '#fff' },
                            { name: 'blue', color: '#00f' },
                        ]}
                        value={attributes.buttonOpenTextColor}
                        onChange={(v) => setAttributes({ buttonOpenTextColor: v })}
                    />
                </BaseControl>
            </PanelBody>
        </InspectorControls>
    );
};

export default Inspector;
