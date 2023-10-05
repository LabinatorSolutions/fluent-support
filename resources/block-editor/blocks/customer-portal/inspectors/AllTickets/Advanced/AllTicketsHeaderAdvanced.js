const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function AllTicketsHeaderAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <p><strong>{__('Top Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allTicketsHeaderRadiusTopLeft }
                onChange={(v) => setAttributes({ allTicketsHeaderRadiusTopLeft: v })}
                min={ 0 }
                max={ 50 }
            />

            <p><strong>{__('Top Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allTicketsHeaderRadiusTopRight }
                onChange={(v) => setAttributes({ allTicketsHeaderRadiusTopRight: v })}
                min={ 0 }
                max={ 50 }
            />
            <p><strong>{__('Bottom Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allTicketsHeaderRadiusBottomLeft }
                onChange={(v) => setAttributes({ allTicketsHeaderRadiusBottomLeft: v })}
                min={ 0 }
                max={ 50 }
            />

            <p><strong>{__('Bottom Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allTicketsHeaderRadiusBottomRight }
                onChange={(v) => setAttributes({ allTicketsHeaderRadiusBottomRight: v })}
                min={ 0 }
                max={ 50 }
            />
        </PanelBody>
    )
}
