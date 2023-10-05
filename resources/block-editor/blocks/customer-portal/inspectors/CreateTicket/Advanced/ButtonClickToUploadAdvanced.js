const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function ButtonClickToUploadAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('All', 'fluent-support')}>
            <p><strong>{__('Border Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.createTicketUploadButtonBorderWidth}
                onChange={(v) => setAttributes({createTicketUploadButtonBorderWidth: v})}
                min={1}
                max={5}
            />

            <p><strong>{__('Border Radius', 'fluent-support')}</strong></p>
            <RangeControl
                value={attributes.createTicketUploadButtonBorderRadius}
                onChange={(v) => setAttributes({createTicketUploadButtonBorderRadius: v})}
                min={0}
                max={15}
            />
        </PanelBody>
    );
}
