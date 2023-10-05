const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function RibbonSupportStaffAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Support Staff', 'fluent-support')}>
            <p><strong>{__('Ribbon Tail Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.ribbonSupportStaffTailWidth }
                onChange={(v) => setAttributes({ ribbonSupportStaffTailWidth: v })}
                min={ 1 }
                max={ 10 }
            />

            <p><strong>{__('Padding Top', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.ribbonSupportStaffPaddingTop }
                onChange={(v) => setAttributes({ ribbonSupportStaffPaddingTop: v })}
                min={ 0 }
                max={ 15 }
            />

            <p><strong>{__('Padding Bottom', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.ribbonSupportStaffPaddingBottom }
                onChange={(v) => setAttributes({ ribbonSupportStaffPaddingBottom: v })}
                min={ 0 }
                max={ 15 }
            />
            <p><strong>{__('Padding Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.ribbonSupportStaffPaddingRight }
                onChange={(v) => setAttributes({ ribbonSupportStaffPaddingRight: v })}
                min={ 0 }
                max={ 15 }
            />

            <p><strong>{__('Padding Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.ribbonSupportStaffPaddingLeft }
                onChange={(v) => setAttributes({ ribbonSupportStaffPaddingLeft: v })}
                min={ 0 }
                max={ 15 }
            />
        </PanelBody>
    )
}
