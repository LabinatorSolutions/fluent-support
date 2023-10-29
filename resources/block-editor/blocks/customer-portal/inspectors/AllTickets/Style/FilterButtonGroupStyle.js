const { __ } = wp.i18n;
const { PanelBody } = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function FilterButtonGroupStyle({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('When Button is Active', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'filterButtonActiveBgColor',
                }}
            />
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'filterButtonActiveTextColor',
                }}
            />
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Border Color', 'fluent-support'),
                    attributeName: 'filterButtonActiveBorderColor',
                }}
            />
        </PanelBody>
    );
}
