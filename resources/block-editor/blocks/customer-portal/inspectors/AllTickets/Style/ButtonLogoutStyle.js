const { __ } = wp.i18n;
const { PanelBody, ColorPalette } = wp.components;
export default function ButtonLogoutStyle({ attributes, setAttributes}) {
    return (
    <PanelBody title={__('Logout', 'fluent-support')}>

        <p><strong>{__('Background Color', 'fluent-support')}</strong></p>
        <ColorPalette value={attributes.allTicketsLogoutButtonBgColor}
                      colors={[
                          { name: 'green', color: '#67C23A' },
                          { name: 'black', color: '#000000' },
                          { name: 'gray', color: '#777777' },
                          { name: 'red', color: '#ff0000' },
                      ]}
                      onChange={(v) => setAttributes({ allTicketsLogoutButtonBgColor: v })}
        />
        <p><strong>{__('Text Color', 'fluent-support')}</strong></p>
        <ColorPalette value={attributes.allTicketsLogoutButtonTextColor}
                      colors={[
                          { name: 'green', color: '#67C23A' },
                          { name: 'black', color: '#000000' },
                          { name: 'gray', color: '#777777' },
                          { name: 'red', color: '#ff0000' },
                      ]}
                      onChange={(v) => setAttributes({ allTicketsLogoutButtonTextColor: v })}
        />
    </PanelBody>
    );
}
