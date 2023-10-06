const {__} = wp.i18n;
const { PanelBody } = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function AllTicketsHeaderStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'allTicketsHeaderBgColor',
                }}
            />
        </PanelBody>
    )
}
