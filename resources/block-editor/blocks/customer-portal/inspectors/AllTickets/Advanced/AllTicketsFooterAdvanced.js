const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function AllTicketsFooterAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <p><strong>{__('Top Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allTicketsFooterRadiusTopLeft }
                onChange={(v) => setAttributes({ allTicketsFooterRadiusTopLeft: v })}
                min={ 0 }
                max={ 50 }
            />

            <p><strong>{__('Top Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allTicketsFooterRadiusTopRight }
                onChange={(v) => setAttributes({ allTicketsFooterRadiusTopRight: v })}
                min={ 0 }
                max={ 50 }
            />
            <p><strong>{__('Bottom Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allTicketsFooterRadiusBottomLeft }
                onChange={(v) => setAttributes({ allTicketsFooterRadiusBottomLeft: v })}
                min={ 0 }
                max={ 50 }
            />

            <p><strong>{__('Bottom Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allTicketsFooterRadiusBottomRight }
                onChange={(v) => setAttributes({ allTicketsFooterRadiusBottomRight: v })}
                min={ 0 }
                max={ 50 }
            />
        </PanelBody>
    )
}
