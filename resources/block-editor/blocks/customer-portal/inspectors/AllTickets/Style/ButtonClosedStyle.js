const { __ } = wp.i18n;
const { PanelBody } = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ButtonClosedStyle( { attributes, setAttributes} ) {
    return (
        <PanelBody title={__('Closed', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'filterButtonClosedBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'filterButtonClosedTextColor',
                }}
            />
        </PanelBody>
    );
}
