const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
import icons from "../../../utils/icons";
import EnhanceMultiRangeControl from "../../../utils/EnhanceMultiRangeControl";
export default function ButtonCloseTicketAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Close Ticket', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.closeTicketButtonBorderWidth }
                onChange={(v) => setAttributes({ closeTicketButtonBorderWidth: v })}
                min={ 1 }
                max={ 5 }
            />

            <EnhanceMultiRangeControl attributes={attributes} setAttributes={setAttributes} props={{
                title: __('Border Radius', 'fluent-support'),
                parentAttribute: 'closeTicketButtonBorderRadius',
                TopAttribute: 'TopLeft',//closeTicketButtonBorderRadiusTopLeft
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
                max: 15,
                inc: 1
            }}/>
        </PanelBody>
    )
}
