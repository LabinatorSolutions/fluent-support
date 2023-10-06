const {__} = wp.i18n;
const {PanelBody} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ViewTicketButtonAllStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'allButtonBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'allButtonTextColor',
                }}
            />
        </PanelBody>
    )
}
