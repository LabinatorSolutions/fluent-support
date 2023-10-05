const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ButtonLogoutAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Logout', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.filterButtonLogoutBorderWidth}
                onChange={(v) => setAttributes({filterButtonLogoutBorderWidth: v})}
                min={1}
                max={5}
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.filterButtonLogoutBorderRadius}
                onChange={(v) => setAttributes({filterButtonLogoutBorderRadius: v})}
                min={0}
                max={15}
            />
        </PanelBody>
    );
}
