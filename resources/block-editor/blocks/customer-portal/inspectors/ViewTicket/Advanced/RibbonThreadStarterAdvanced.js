const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function RibbonThreadStarterAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Thread Starter', 'fluent-support')}>
            <p><strong>{__('Left Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketThreadStarterBorderWidth }
                onChange={(v) => setAttributes({ viewTicketThreadStarterBorderWidth: v })}
                min={ 1 }
                max={ 5 }
            />

            <p><strong>{__('Padding Top Bottom', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketThreadStarterPaddingBottom }
                onChange={(v) => setAttributes({ viewTicketThreadStarterPaddingBottom: v })}
                min={ 0 }
                max={ 15 }
            />

            <p><strong>{__('Padding Left Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketThreadStarterPaddingLeft }
                onChange={(v) => setAttributes({ viewTicketThreadStarterPaddingLeft: v })}
                min={ 0 }
                max={ 15 }
            />
        </PanelBody>
    )
}
