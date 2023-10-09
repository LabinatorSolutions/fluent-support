const {__} = wp.i18n;
const {PanelBody} = wp.components;
import EnhancedColorPicker from "../../../utils/EnhancedColorPicker";
import EnhanceMultiRangeControl from "../../../utils/EnhanceMultiRangeControl";
import icons from "../../../utils/icons";
export default function ButtonCreateAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            {/*<p><strong>{__('Border Width', 'fluent-support')}</strong></p>*/}
            {/*<RangeControl*/}
            {/*    value={attributes.createTicketCreateButtonBorderWidth}*/}
            {/*    onChange={(v) => setAttributes({createTicketCreateButtonBorderWidth: v})}*/}
            {/*    min={1}*/}
            {/*    max={5}*/}
            {/*/>*/}

            {/*<p><strong>{__('Border Radius', 'fluent-support')}</strong></p>*/}
            {/*<RangeControl*/}
            {/*    value={attributes.createTicketCreateButtonBorderRadius}*/}
            {/*    onChange={(v) => setAttributes({createTicketCreateButtonBorderRadius: v})}*/}
            {/*    min={0}*/}
            {/*    max={15}*/}
            {/*/>*/}

            <EnhancedColorPicker
                attributes={attributes}
                setAttributes={setAttributes}
                props={{
                    title: __('Border Color', 'fluent-support'),
                    attributeName: 'createTicketCreateButtonBorderColor',
                }}
            />

            <EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{
                title: __('Border Width(px)', 'fluent-support'),
                parentAttribute: 'createTicketCreateButtonBorderWidth',
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
                parentAttribute: 'createTicketCreateButtonBorderRadius',
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
    );
}
