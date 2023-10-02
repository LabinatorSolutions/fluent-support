const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonViewAllStyle({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('View All', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketViewAllButtonBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ createTicketViewAllButtonBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketViewAllButtonTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ createTicketViewAllButtonTextColor: v })}
            />
        </PanelBody>
    );
}
