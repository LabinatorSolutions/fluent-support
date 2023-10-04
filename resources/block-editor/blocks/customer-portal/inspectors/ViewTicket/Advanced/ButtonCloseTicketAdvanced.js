const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ButtonCloseTicketAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Close Ticket Adv', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.closeTicketButtonBorderWidth }
                onChange={(v) => setAttributes({ closeTicketButtonBorderWidth: v })}
                min={ 1 }
                max={ 5 }
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.closeTicketButtonBorderRadius }
                onChange={(v) => setAttributes({ closeTicketButtonBorderRadius: v })}
                min={ 0 }
                max={ 15 }
            />
        </PanelBody>
    )
}
