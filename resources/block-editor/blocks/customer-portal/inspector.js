const { __ } = wp.i18n;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, BasePanel, ToggleControl, ColorPalette } = wp.components;

const Inspector = ({ attributes, setAttributes }) => {

    return (
        <InspectorControls>
            <PanelBody title={__('Ticket Header', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.allTicketsHeaderBgColor}
                              onChange={(v) => setAttributes({ allTicketsHeaderBgColor: v })}
                />
            </PanelBody>
            <PanelBody title={__('All', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.filterButtonAllBgColor}
                              onChange={(v) => setAttributes({ filterButtonAllBgColor: v })}
                />
                <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.filterButtonAllTextColor}
                              onChange={(v) => setAttributes({ filterButtonAllTextColor: v })}
                />
            </PanelBody>

            <PanelBody title={__('Open', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.filterButtonOpenBgColor}
                              onChange={(v) => setAttributes({ filterButtonOpenBgColor: v })}
                />
                <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.filterButtonOpenTextColor}
                              onChange={(v) => setAttributes({ filterButtonOpenTextColor: v })}
                />
            </PanelBody>

            <PanelBody title={__('Closed', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.filterButtonClosedBgColor}
                              onChange={(v) => setAttributes({ filterButtonClosedBgColor: v })}
                />
                <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.filterButtonClosedTextColor}
                              onChange={(v) => setAttributes({ filterButtonClosedTextColor: v })}
                />
            </PanelBody>

            <PanelBody title={__('Logout', 'fluent-support')} initialOpen={ false }>
                <ToggleControl
                    label="Show Logout"
                    checked={ attributes.allTicketsLogoutButtonVisibility }
                    onChange={(v) => setAttributes({ allTicketsLogoutButtonVisibility: v })}
                />
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.allTicketsLogoutButtonBgColor}
                              onChange={(v) => setAttributes({ allTicketsLogoutButtonBgColor: v })}
                />
                <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.allTicketsLogoutButtonTextColor}
                              onChange={(v) => setAttributes({ allTicketsLogoutButtonTextColor: v })}
                />
            </PanelBody>

            <PanelBody title={__('Create a New Ticket', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.buttonCreateTicketBgColor}
                              onChange={(v) => setAttributes({ buttonCreateTicketBgColor: v })}
                />
                <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.buttonCreateTicketTextColor}
                              onChange={(v) => setAttributes({ buttonCreateTicketTextColor: v })}
                />
            </PanelBody>

            <PanelBody title={__('Tickets Table', 'fluent-support')} initialOpen={ false }>
                <PanelBody title={__('Header', 'fluent-support')} initialOpen={ false }>
                    <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.allTicketsTableHeaderBgColor}
                                    onChange={(v) => setAttributes({ allTicketsTableHeaderBgColor: v })}
                    />
                    <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.allTicketsTableHeaderTextColor}
                                    onChange={(v) => setAttributes({ allTicketsTableHeaderTextColor: v })}
                    />
                    <p><strong>{__('Text Align', 'fluent-support')}</strong></p>
                    <select value={attributes.allTicketsTableHeaderTextAlign}
                            onChange={(v) => setAttributes({ allTicketsTableHeaderTextAlign: v.target.value })}
                    >
                        <option value="left">{__('Left', 'fluent-support')}</option>
                        <option value="center">{__('Center', 'fluent-support')}</option>
                        <option value="right">{__('Right', 'fluent-support')}</option>
                    </select>
                </PanelBody>
            </PanelBody>

            <PanelBody title={__('Ticket Page Footer', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.allTicketsFooterBgColor}
                              onChange={(v) => setAttributes({ allTicketsFooterBgColor: v })}
                />
            </PanelBody>

            <PanelBody title={__('Create Ticket Page Header', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketHeaderBgColor}
                              onChange={(v) => setAttributes({ createTicketHeaderBgColor: v })}
                />

                <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketHeaderTextBgColor}
                              onChange={(v) => setAttributes({ createTicketHeaderTextBgColor: v })}
                />

                <p><strong>{__('View All Button Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketViewAllButtonTextColor}
                              onChange={(v) => setAttributes({ createTicketViewAllButtonTextColor: v })}
                />

                <p><strong>{__('View All Button Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketViewAllButtonBgColor}
                              onChange={(v) => setAttributes({ createTicketViewAllButtonBgColor: v })}
                />
            </PanelBody>

            <PanelBody title={__('Create Ticket Page Body', 'fluent-support')} initialOpen={ false }>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketBodyBgColor}
                              onChange={(v) => setAttributes({ createTicketBodyBgColor: v })}
                />

                <p><strong>{__('Form Input Header Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketInputHeaderTextColor}
                              onChange={(v) => setAttributes({ createTicketInputHeaderTextColor: v })}
                />

                <p><strong>{__('Tip Message Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketTipMessageTextColor}
                              onChange={(v) => setAttributes({ createTicketTipMessageTextColor: v })}
                />

                <p><strong>{__('Upload Button Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketUploadButtonBgColor}
                              onChange={(v) => setAttributes({ createTicketUploadButtonBgColor: v })}
                />
                <p><strong>{__('Upload Button Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketUploadButtonTextColor}
                              onChange={(v) => setAttributes({ createTicketUploadButtonTextColor: v })}
                />

                <p><strong>{__('Create Button Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketCreateButtonBgColor}
                              onChange={(v) => setAttributes({ createTicketCreateButtonBgColor: v })}
                />
                <p><strong>{__('Create Button Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.createTicketCreateButtonTextColor}
                              onChange={(v) => setAttributes({ createTicketCreateButtonTextColor: v })}
                />

            </PanelBody>

        </InspectorControls>
    );
};

export default Inspector;
