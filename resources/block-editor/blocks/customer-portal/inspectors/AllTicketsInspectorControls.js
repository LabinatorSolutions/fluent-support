import { __ } from '@wordpress/i18n';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { ColorPalette } from '@wordpress/block-editor';
export default function AllTicketsInspectorControls({ attributes, setAttributes}) {
    return (
        <div>
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
        );
}
