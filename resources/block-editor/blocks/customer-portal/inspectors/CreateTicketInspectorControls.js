const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;

export default function CreateTicketInspectorControls({ attributes, setAttributes}) {
    return (
        <div>
            <PanelBody title={__('Page Header', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketHeaderBgColor}
                              onChange={(v) => setAttributes({ createTicketHeaderBgColor: v })}
                />

                <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketHeaderTextColor}
                              onChange={(v) => setAttributes({ createTicketHeaderTextColor: v })}
                />
                <PanelBody title={__('View All', 'fluent-support')} initialOpen={ false }>
                    <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.createTicketViewAllButtonBgColor}
                                  onChange={(v) => setAttributes({ createTicketViewAllButtonBgColor: v })}
                    />

                    <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.createTicketViewAllButtonTextColor}
                                  onChange={(v) => setAttributes({ createTicketViewAllButtonTextColor: v })}
                    />

                </PanelBody>
            </PanelBody>
            <PanelBody title={__('Page Body', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketBodyBgColor}
                              onChange={(v) => setAttributes({ createTicketBodyBgColor: v })}
                />

                <p><strong>{__('Input Label Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketInputLabelTextColor}
                              onChange={(v) => setAttributes({ createTicketInputLabelTextColor: v })}
                />

                <p><strong>{__('Hint Message Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketHintMessageTextColor}
                              onChange={(v) => setAttributes({ createTicketHintMessageTextColor: v })}
                />

                <PanelBody title={__('Click to upload', 'fluent-support')} initialOpen={ false }>
                    <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.createTicketUploadButtonBgColor}
                                  onChange={(v) => setAttributes({ createTicketUploadButtonBgColor: v })}
                    />
                    <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.createTicketUploadButtonTextColor}
                                  onChange={(v) => setAttributes({ createTicketUploadButtonTextColor: v })}
                    />
                </PanelBody>
                <PanelBody title={__('Create', 'fluent-support')} initialOpen={ false }>
                    <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.createTicketCreateButtonBgColor}
                                  onChange={(v) => setAttributes({ createTicketCreateButtonBgColor: v })}
                    />
                    <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.createTicketCreateButtonTextColor}
                                  onChange={(v) => setAttributes({ createTicketCreateButtonTextColor: v })}
                    />
                </PanelBody>
            </PanelBody>
        </div>
    )
}
