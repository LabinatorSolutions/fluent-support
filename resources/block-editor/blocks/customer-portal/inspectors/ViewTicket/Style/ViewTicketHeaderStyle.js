const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ViewTicketHeaderStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'viewTicketHeaderStyleBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'viewTicketHeaderStyleTextColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Ticket Id Color', 'fluent-support'),
                    attributeName: 'viewTicketHeaderIdTextColor',
                }}
            />
        </PanelBody>
    )
}
