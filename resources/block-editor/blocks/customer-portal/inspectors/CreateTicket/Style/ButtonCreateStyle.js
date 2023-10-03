const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonCreateStyle({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('Create', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketCreateButtonBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ createTicketCreateButtonBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketCreateButtonTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ createTicketCreateButtonTextColor: v })}
            />
        </PanelBody>

    );
}
