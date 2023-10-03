const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function RibbonThreadFollowerStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Thread Follower', 'fluent-support')}>
            <p><strong>{__('Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.viewTicketThreadFollowerBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ viewTicketThreadFollowerBgColor: v })}
            />

            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.viewTicketThreadFollowerTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ viewTicketThreadFollowerTextColor: v })}
            />
        </PanelBody>
    )
}
