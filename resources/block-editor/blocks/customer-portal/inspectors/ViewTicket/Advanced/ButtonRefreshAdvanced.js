const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ButtonRefreshAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Refresh', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.refreshButtonBorderWidth }
                onChange={(v) => setAttributes({ refreshButtonBorderWidth: v })}
                min={ 1 }
                max={ 5 }
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.refreshButtonBorderRadius }
                onChange={(v) => setAttributes({ refreshButtonBorderRadius: v })}
                min={ 0 }
                max={ 15 }
            />
        </PanelBody>
    )
}
