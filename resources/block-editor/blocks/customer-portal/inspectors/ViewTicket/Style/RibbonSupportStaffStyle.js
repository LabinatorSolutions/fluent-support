const {__} = wp.i18n;
const {PanelBody} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function RibbonSupportStaffStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Support Staff ', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'ribbonSupportStaffBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'ribbonSupportStaffTextColor',
                }}
            />
        </PanelBody>
    )
}
