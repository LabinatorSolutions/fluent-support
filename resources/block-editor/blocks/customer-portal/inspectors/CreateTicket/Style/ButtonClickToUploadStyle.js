const {__} = wp.i18n;
const {PanelBody} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ButtonClickToUploadStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Click to upload', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'createTicketUploadButtonBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'createTicketUploadButtonTextColor',
                }}
            />
        </PanelBody>
    )
}
