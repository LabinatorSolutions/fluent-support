const {__} = wp.i18n;
const {PanelBody} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function RibbonThreadFollowerStyle({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Thread Follower', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Background Color', 'fluent-support'),
                    attributeName: 'viewTicketThreadFollowerBgColor',
                }}
            />

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Text Color', 'fluent-support'),
                    attributeName: 'viewTicketThreadFollowerTextColor',
                }}
            />
        </PanelBody>
    )
}
