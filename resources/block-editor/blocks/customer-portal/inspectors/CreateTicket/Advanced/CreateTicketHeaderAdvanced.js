const {__} = wp.i18n;
const {PanelBody} = wp.components;
import EnhanceMultiRangeControl from "../../../utils/EnhanceMultiRangeControl";
import icons from "../../../utils/icons";
export default function CreateTicketHeaderAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{
                title: __('Border Radius (px)', 'fluent-support'),
                parentAttribute: 'createTicketHeaderRadius',
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
