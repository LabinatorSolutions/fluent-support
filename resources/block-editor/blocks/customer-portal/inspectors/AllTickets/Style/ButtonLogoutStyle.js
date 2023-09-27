const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonLogoutStyle({ attributes, setAttributes}) {
    return (
    <PanelBody title={__('Logout', 'fluent-support')}>

        <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
        <ColorPalette value={attributes.allTicketsLogoutButtonBgColor}
                      onChange={(v) => setAttributes({ allTicketsLogoutButtonBgColor: v })}
        />
        <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
        <ColorPalette value={attributes.allTicketsLogoutButtonTextColor}
                      onChange={(v) => setAttributes({ allTicketsLogoutButtonTextColor: v })}
        />
    </PanelBody>
    );
}
