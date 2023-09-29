const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function AllTicketsTableHeaderStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Page Body', 'fluent-support')} initialOpen={false}>
            <PanelBody title={__('Header', 'fluent-support')} initialOpen={false}>
                <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.allTicketsTableHeaderBgColor}
                              colors={[
                                  { name: 'green', color: '#67C23A' },
                                  { name: 'black', color: '#000000' },
                                  { name: 'gray', color: '#777777' },
                                  { name: 'red', color: '#ff0000' },
                              ]}
                              onChange={(v) => setAttributes({allTicketsTableHeaderBgColor: v})}
                />
                <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
                <ColorPalette value={attributes.allTicketsTableHeaderTextColor}
                              colors={[
                                  { name: 'green', color: '#67C23A' },
                                  { name: 'black', color: '#000000' },
                                  { name: 'gray', color: '#777777' },
                                  { name: 'red', color: '#ff0000' },
                              ]}
                              onChange={(v) => setAttributes({allTicketsTableHeaderTextColor: v})}
                />
                <p><strong>{__('Text Align', 'fluent-support')}</strong></p>
                <select value={attributes.allTicketsTableHeaderTextAlign}
                        onChange={(v) => setAttributes({allTicketsTableHeaderTextAlign: v.target.value})}
                >
                    <option value="left">{__('Left', 'fluent-support')}</option>
                    <option value="center">{__('Center', 'fluent-support')}</option>
                    <option value="right">{__('Right', 'fluent-support')}</option>
                </select>
            </PanelBody>
        </PanelBody>
    )
}
