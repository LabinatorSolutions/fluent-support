const {__} = wp.i18n;
const { PanelBody } = wp.components;
import icons from "../../../utils/icons";
import EnhanceMultiRangeControl from "../../../utils/EnhanceMultiRangeControl";
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function AllTicketsFooterAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Footer', 'fluent-support')}>
            <EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{
                title: __('Border Radius (px)', 'fluent-support'),
                parentAttribute: 'allTicketsFooterRadius',
                TopAttribute: 'TopLeft',
                RightAttribute: 'TopRight',
                BottomAttribute: 'BottomRight',
                LeftAttribute: 'BottomLeft',
                icons: {
                    all: icons.SelectAll,
                    top: icons.BorderRadiusTopLeft,
                    right: icons.BorderRadiusTopRight,
                    bottom: icons.BorderRadiusBottomRight,
                    left: icons.BorderRadiusBottomLeft,
                },
                min: 0,
                max: 50,
                inc: 1
            }}/>
        </PanelBody>
    )
}
