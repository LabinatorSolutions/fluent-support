const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ViewTicketHeaderAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <p><strong>{__('Top Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketHeaderRadiusTopLeft }
                onChange={(v) => setAttributes({ viewTicketHeaderRadiusTopLeft: v })}
                min={ 0 }
                max={ 50 }
            />

            <p><strong>{__('Top Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketHeaderRadiusTopRight }
                onChange={(v) => setAttributes({ viewTicketHeaderRadiusTopRight: v })}
                min={ 0 }
                max={ 50 }
            />
            <p><strong>{__('Bottom Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketHeaderRadiusBottomRight }
                onChange={(v) => setAttributes({ viewTicketHeaderRadiusBottomRight: v })}
                min={ 0 }
                max={ 50 }
            />

            <p><strong>{__('Bottom Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketHeaderRadiusBottomLeft }
                onChange={(v) => setAttributes({ viewTicketHeaderRadiusBottomLeft: v })}
                min={ 0 }
                max={ 50 }
            />
        </PanelBody>
    )
}
