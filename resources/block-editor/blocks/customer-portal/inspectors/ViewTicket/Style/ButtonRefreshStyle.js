const {__} = wp.i18n;
const {PanelBody, ColorPalette} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ButtonRefreshStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Refresh', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'refreshButtonBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'refreshButtonTextColor',
                }}
            />
        </PanelBody>
    )
}
