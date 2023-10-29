const {__} = wp.i18n;
const {PanelBody} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ThreadDateColorPickerStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Thread Person', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Color', 'fluent-support'),
                    attributeName: 'threadItemDateColor',
                }}
            />
        </PanelBody>
    )
}
