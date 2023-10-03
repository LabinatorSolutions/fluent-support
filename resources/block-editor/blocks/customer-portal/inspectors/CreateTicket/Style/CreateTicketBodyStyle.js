const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function CreateTicketsBodyStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Form Style', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketBodyBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ createTicketBodyBgColor: v })}
            />

            <p><strong>{__('Input Label Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketInputLabelTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ createTicketInputLabelTextColor: v })}
            />

            <p><strong>{__('Hint Message Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketHintMessageTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ createTicketHintMessageTextColor: v })}
            />
        </PanelBody>
    )
}
