const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function ButtonClickToUploadStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Click to upload', 'fluent-support')}>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketUploadButtonBgColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ createTicketUploadButtonBgColor: v })}
            />
            <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketUploadButtonTextColor}
                          colors={[
                              { name: 'green', color: '#67C23A' },
                              { name: 'black', color: '#000000' },
                              { name: 'gray', color: '#777777' },
                              { name: 'red', color: '#ff0000' },
                          ]}
                          onChange={(v) => setAttributes({ createTicketUploadButtonTextColor: v })}
            />
        </PanelBody>
    )
}
