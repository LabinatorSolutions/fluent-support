const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function CreateTicketsBodyStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Form Style', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'createTicketBodyBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Input Label Color', 'fluent-support'),
                    attributeName: 'createTicketInputLabelTextColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Hint Message Text Color', 'fluent-support'),
                    attributeName: 'createTicketHintMessageTextColor',
                }}
            />
        </PanelBody>
    )
}
