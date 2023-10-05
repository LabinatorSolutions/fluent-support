const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ButtonOpenAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Open', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.filterButtonOpenBorderWidth}
                onChange={(v) => setAttributes({filterButtonOpenBorderWidth: v})}
                min={1}
                max={5}
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.filterButtonOpenBorderRadius}
                onChange={(v) => setAttributes({filterButtonOpenBorderRadius: v})}
                min={0}
                max={15}
            />
        </PanelBody>
    );
}
