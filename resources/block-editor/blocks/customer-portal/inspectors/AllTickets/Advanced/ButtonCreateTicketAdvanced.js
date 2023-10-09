import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
import EnhanceMultiRangeControl from "../../../utils/EnhanceMultiRangeControl";
import icons from "../../../utils/icons";

const { __ } = wp.i18n;
const { PanelBody } = wp.components;
export default function ButtonCreateTicketAdvanced({ attributes, setAttributes}) {
    return (
        <PanelBody title={__('Create Ticket', 'fluent-support')}>
            {/*<EnhancedColorPicker*/}
            {/*    attributes={attributes}*/}
            {/*    setAttributes={setAttributes}*/}
            {/*    props={{*/}
            {/*        title: __('Border Color', 'fluent-support'),*/}
            {/*        attributeName: 'allTicketsCreateTicketButtonBorderColor',*/}
            {/*    }}*/}
            {/*/>*/}

            {/*<EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{*/}
            {/*    title: __('Border Width(px)', 'fluent-support'),*/}
            {/*    parentAttribute: 'allTicketsCreateTicketButtonBorderWidth',*/}
            {/*    TopAttribute: 'Top',*/}
            {/*    RightAttribute: 'Right',*/}
            {/*    BottomAttribute: 'Bottom',*/}
            {/*    LeftAttribute: 'Left',*/}
            {/*    icons: {*/}
            {/*        all: icons.SelectAll,*/}
            {/*        top: icons.BorderTop,*/}
            {/*        right: icons.BorderRight,*/}
            {/*        bottom: icons.BorderBottom,*/}
            {/*        left: icons.BorderLeft,*/}
            {/*    },*/}
            {/*    min: 0,*/}
            {/*    max: 15,*/}
            {/*    inc: 1*/}
            {/*}}/>*/}

            {/*<EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{*/}
            {/*    title: __('Border Radius (px)', 'fluent-support'),*/}
            {/*    parentAttribute: 'allTicketsCreateTicketButtonBorderRadius',*/}
            {/*    TopAttribute: 'TopLeft',*/}
            {/*    RightAttribute: 'TopRight',*/}
            {/*    BottomAttribute: 'BottomRight',*/}
            {/*    LeftAttribute: 'BottomLeft',*/}
            {/*    icons: {*/}
            {/*        all: icons.SelectAll,*/}
            {/*        top: icons.BorderRadiusTopLeft,*/}
            {/*        right: icons.BorderRadiusTopRight,*/}
            {/*        bottom: icons.BorderRadiusBottomRight,*/}
            {/*        left: icons.BorderRadiusBottomLeft,*/}
            {/*    },*/}
            {/*    min: 0,*/}
            {/*    max: 50,*/}
            {/*    inc: 1*/}
            {/*}}/>*/}
        </PanelBody>
    );
}
