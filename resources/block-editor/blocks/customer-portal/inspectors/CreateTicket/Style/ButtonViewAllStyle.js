const { __ } = wp.i18n;
const { PanelBody } = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ButtonViewAllStyle({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('View All', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'createTicketViewAllButtonBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'createTicketViewAllButtonTextColor',
                }}
            />
        </PanelBody>
    );
}
