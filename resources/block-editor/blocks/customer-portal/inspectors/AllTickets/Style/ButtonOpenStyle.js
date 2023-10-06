const { __ } = wp.i18n;
const { PanelBody } = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ButtonOpenStyle( { attributes, setAttributes} ) {
    return (
        <PanelBody title={__('Open', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'filterButtonOpenBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'filterButtonOpenTextColor',
                }}
            />
        </PanelBody>
    );
}
