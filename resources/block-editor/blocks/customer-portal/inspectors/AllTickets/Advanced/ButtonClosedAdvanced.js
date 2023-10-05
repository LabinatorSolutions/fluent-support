const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ButtonClosedAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Closed', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.filterButtonClosedBorderWidth}
                onChange={(v) => setAttributes({filterButtonClosedBorderWidth: v})}
                min={1}
                max={5}
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.filterButtonClosedBorderRadius}
                onChange={(v) => setAttributes({filterButtonClosedBorderRadius: v})}
                min={0}
                max={15}
            />
        </PanelBody>
    );
}
