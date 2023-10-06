const {__} = wp.i18n;
const {PanelBody} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ViewTicketBodyStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Page Body', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'viewTicketPageBodyBgColor',
                }}
            />
        </PanelBody>
    )
}
