const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ButtonAllAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.filterButtonAllBorderWidth}
                onChange={(v) => setAttributes({filterButtonAllBorderWidth: v})}
                min={1}
                max={5}
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.filterButtonAllBorderRadius}
                onChange={(v) => setAttributes({filterButtonAllBorderRadius: v})}
                min={0}
                max={15}
            />
        </PanelBody>
    );
}
