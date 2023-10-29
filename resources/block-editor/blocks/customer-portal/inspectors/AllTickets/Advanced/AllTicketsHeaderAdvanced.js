import icons from "@/block-editor/blocks/customer-portal/utils/icons";
import EnhanceMultiRangeControl from "@/block-editor/blocks/customer-portal/utils/EnhanceMultiRangeControl";

const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function AllTicketsHeaderAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{
                title: __('Border Radius (px)', 'fluent-support'),
                parentAttribute: 'allTicketsHeaderRadius',
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
