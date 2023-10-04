const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ViewTicketButtonAllAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allButtonBorderWidth }
                onChange={(v) => setAttributes({ allButtonBorderWidth: v })}
                min={ 1 }
                max={ 5 }
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.allButtonBorderRadius }
                onChange={(v) => setAttributes({ allButtonBorderRadius: v })}
                min={ 0 }
                max={ 15 }
            />
        </PanelBody>
    )
}
