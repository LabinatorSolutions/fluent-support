const {__} = wp.i18n;
const { PanelBody } = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function AllTicketsTableHeaderStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Body', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'allTicketsTableHeaderBgColor',
                }}
            />
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'allTicketsTableHeaderTextColor',
                }}
            />
        </PanelBody>
    )
}
