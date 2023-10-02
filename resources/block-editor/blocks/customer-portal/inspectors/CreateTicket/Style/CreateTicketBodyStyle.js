const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
export default function CreateTicketsBodyStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Page Body', 'fluent-support')} initialOpen={ false }>
            <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
            <ColorPalette value={attributes.createTicketBodyBgColor}
                          onChange={(v) => setAttributes({ createTicketBodyBgColor: v })}
            />
        </PanelBody>
    )
}
