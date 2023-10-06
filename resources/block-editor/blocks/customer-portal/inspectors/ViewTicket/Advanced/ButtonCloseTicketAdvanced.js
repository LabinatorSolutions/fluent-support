const {__} = wp.i18n;
const { PanelBody } = wp.components;
import icons from "../../../utils/icons";
import EnhanceMultiRangeControl from "../../../utils/EnhanceMultiRangeControl";
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
export default function ButtonCloseTicketAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Close Ticket', 'fluent-support')}>
            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Border Color', 'fluent-support'),
                    attributeName: 'closeTicketButtonBorderColor',
                }}
            />
            <EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{
                title: __('Border Width(px)', 'fluent-support'),
                parentAttribute: 'closeTicketButtonBorderWidth',
                TopAttribute: 'Top',
                RightAttribute: 'Right',
                BottomAttribute: 'Bottom',
                LeftAttribute: 'Left',
                icons: {
                    all: icons.SelectAll,
                    top: icons.BorderTop,
                    right: icons.BorderRight,
                    bottom: icons.BorderBottom,
                    left: icons.BorderLeft,
                },
                min: 0,
                max: 15,
                inc: 1
            }}/>

            <EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{
                title: __('Border Radius (px)', 'fluent-support'),
                parentAttribute: 'closeTicketButtonBorderRadius',
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
