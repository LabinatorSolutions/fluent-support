import { __ } from '@wordpress/i18n';
import { ColorPalette } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
export default function ViewTicketInspectorControls({ attributes, setAttributes}) {
    return (
        <div>
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
    )
}
