const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function CreateTicketHeaderAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <p><strong>{__('Top Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.createTicketHeaderRadiusTopLeft }
                onChange={(v) => setAttributes({ createTicketHeaderRadiusTopLeft: v })}
                min={ 0 }
                max={ 50 }
            />

            <p><strong>{__('Top Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.createTicketHeaderRadiusTopRight }
                onChange={(v) => setAttributes({ createTicketHeaderRadiusTopRight: v })}
                min={ 0 }
                max={ 50 }
            />
            <p><strong>{__('Bottom Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.createTicketHeaderRadiusBottomLeft }
                onChange={(v) => setAttributes({ createTicketHeaderRadiusBottomLeft: v })}
                min={ 0 }
                max={ 50 }
            />

            <p><strong>{__('Bottom Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.createTicketHeaderRadiusBottomRight }
                onChange={(v) => setAttributes({ createTicketHeaderRadiusBottomRight: v })}
                min={ 0 }
                max={ 50 }
            />
        </PanelBody>
    )
}
