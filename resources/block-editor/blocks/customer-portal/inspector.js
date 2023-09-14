const { __ } = wp.i18n;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, Button, ToggleControl, ColorPalette } = wp.components;
const Inspector = ({inspectorProps}) => {
    const { attributes, setAttributes, showTickets, createTicket, viewTicket, mailboxes} = inspectorProps;

    const allTickets = {
        display: showTickets === true ? '': 'none',
    }

    const ticketForm = {
        display: createTicket === true ? '': 'none',
    }

    const ticketPage = {
        display: viewTicket === true ? '': 'none',
    }

    return (
        <InspectorControls>
            <div style={allTickets}>
                <PanelBody title={__('Page Header', 'fluent-support')} initialOpen={ false }>
                    <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.allTicketsHeaderBgColor}
                                  onChange={(v) => setAttributes({ allTicketsHeaderBgColor: v })}
                    />
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
                </PanelBody>
                <PanelBody title={__('Page Body', 'fluent-support')} initialOpen={ false }>
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
                <PanelBody title={__('Page Footer', 'fluent-support')} initialOpen={ false }>
                    <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.allTicketsFooterBgColor}
                                  onChange={(v) => setAttributes({ allTicketsFooterBgColor: v })}
                    />
                </PanelBody>
            </div>
            <div style={ticketForm}>
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
            <div style={ticketPage}>
                <PanelBody title={__('Page Header', 'fluent-support')} initialOpen={ false }>
                    <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.viewTicketHeaderStyleBgColor}
                                  onChange={(v) => setAttributes({ viewTicketHeaderStyleBgColor: v })}
                    />

                    <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.viewTicketHeaderStyleTextColor}
                                  onChange={(v) => setAttributes({ viewTicketHeaderStyleTextColor: v })}
                    />

                    <p><strong>{__('Ticket Id Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.viewTicketHeaderIdTextColor}
                                  onChange={(v) => setAttributes({ viewTicketHeaderIdTextColor: v })}
                    />
                    <PanelBody title={__('Refresh', 'fluent-support')} initialOpen={ false }>
                        <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.refreshButtonBgColor}
                                      onChange={(v) => setAttributes({ refreshButtonBgColor: v })}
                        />

                        <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.refreshButtonTextColor}
                                      onChange={(v) => setAttributes({ refreshButtonTextColor: v })}
                        />
                    </PanelBody>
                    <PanelBody title={__('All', 'fluent-support')} initialOpen={ false }>
                        <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.allButtonBgColor}
                                      onChange={(v) => setAttributes({ allButtonBgColor: v })}
                        />

                        <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.allButtonTextColor}
                                      onChange={(v) => setAttributes({ allButtonTextColor: v })}
                        />
                    </PanelBody>
                    <PanelBody title={__('Close Ticket', 'fluent-support')} initialOpen={ false }>
                        <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.closeTicketButtonBgColor}
                                      onChange={(v) => setAttributes({ closeTicketButtonBgColor: v })}
                        />

                        <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.closeTicketButtonTextColor}
                                      onChange={(v) => setAttributes({ closeTicketButtonTextColor: v })}
                        />
                    </PanelBody>
                </PanelBody>
                <PanelBody title={__('Page Body', 'fluent-support')} initialOpen={ false }>
                    <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                    <ColorPalette value={attributes.viewTicketPageBodyBgColor}
                                  onChange={(v) => setAttributes({ viewTicketPageBodyBgColor: v })}
                    />
                    <PanelBody title={__('Support Staff ', 'fluent-support')} initialOpen={ false }>
                        <p><strong>{__(' Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.ribbonSupportStaffBgColor}
                                      onChange={(v) => setAttributes({ ribbonSupportStaffBgColor: v })}
                        />
                        <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.ribbonSupportStaffTextColor}
                                      onChange={(v) => setAttributes({ ribbonSupportStaffTextColor: v })}
                        />
                    </PanelBody>
                    <PanelBody title={__('Thread Starter', 'fluent-support')} initialOpen={ false }>
                        <p><strong>{__('Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.viewTicketThreadStarterBgColor}
                                      onChange={(v) => setAttributes({ viewTicketThreadStarterBgColor: v })}
                        />
                        <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.viewTicketThreadStarterTextColor}
                                      onChange={(v) => setAttributes({ viewTicketThreadStarterTextColor: v })}
                        />
                    </PanelBody>
                    <PanelBody title={__('Thread Follower', 'fluent-support')} initialOpen={ false }>
                        <p><strong>{__('Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.viewTicketThreadFollowerBgColor}
                                      onChange={(v) => setAttributes({ viewTicketThreadFollowerBgColor: v })}
                        />

                        <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                        <ColorPalette value={attributes.viewTicketThreadFollowerTextColor}
                                      onChange={(v) => setAttributes({ viewTicketThreadFollowerTextColor: v })}
                        />
                    </PanelBody>
            </PanelBody>
            </div>
            <PanelBody title={__('Default Business Inbox', 'fluent-support')} initialOpen={ false }>
                <select value={attributes.businessBoxId}
                        onChange={(v) => setAttributes({ businessBoxId: v.target.value })}
                >
                    <option value="">{__('Select a mailbox', 'fluent-support')}</option>
                    {mailboxes.map((mailbox) => {
                        return (
                            <option value={mailbox.id} key={mailbox.id}>{mailbox.name +' ('+ mailbox.box_type +')' }</option>
                        );
                    })}
                </select>
            </PanelBody>
        </InspectorControls>
    );
};

export default Inspector;
