const {__} = wp.i18n;
const { PanelBody, RangeControl} = wp.components;
import icons from "../../../utils/icons";
import EnhanceMultiRangeControl from "../../../utils/EnhanceMultiRangeControl";
export default function RibbonThreadStarterAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Thread Starter', 'fluent-support')}>
            <RangeControl
                label={__('Ribbon Tail Width', 'fluent-support')}
                value={ attributes.viewTicketThreadStarterTailWidth }
                onChange={(v) => setAttributes({ viewTicketThreadStarterTailWidth: v })}
                min={ 1 }
                max={ 10 }
            />

            <EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{
                title: __('Padding(px)', 'fluent-support'),
                parentAttribute: 'viewTicketThreadStarterPadding',
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
        </PanelBody>
    )
}
