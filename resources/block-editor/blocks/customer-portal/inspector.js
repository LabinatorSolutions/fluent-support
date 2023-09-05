const { __ } = wp.i18n;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, BaseControl, ColorPalette } = wp.components;

const Inspector = ({ attributes, setAttributes }) => {

    return (
        <InspectorControls>
            <PanelBody title={__('Ticket Header', 'fluent-support')}>
                <BaseControl label={__('Background Color', 'fluent-support')} id="color">
                    <ColorPalette
                        colors={[
                            { name: 'red', color: '#f00' },
                            { name: 'white', color: '#fff' },
                            { name: 'blue', color: '#00f' },
                        ]}
                        value={attributes.ticketHeaderBgColor}
                        onChange={(v) => setAttributes({ ticketHeaderBgColor: v })}
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
            <PanelBody title={__('All', 'fluent-support')}>
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

            <PanelBody title={__('Open', 'fluent-support')}>
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
