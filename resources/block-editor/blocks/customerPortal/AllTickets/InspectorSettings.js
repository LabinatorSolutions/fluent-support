const { InspectorControls, ColorPalette } = wp.blockEditor;
const {
    PanelBody,
    RangeControl
} = wp.components;
const { __ } = wp.i18n;

export const generateStyles = (attributes) => {
    return {
        blockStyles: {
            borderRadius: `${attributes.containerBorderRadius || 0}px`,
        },
        primaryButtonStyles: {
            backgroundColor: attributes.primaryButtonBgColor || '#007cba',
            color: attributes.primaryButtonTextColor || '#ffffff',
        },
        secondaryButtonStyles: {
            backgroundColor: attributes.secondaryButtonBgColor || '#007cba',
            color: attributes.secondaryButtonTextColor || '#ffffff',
        },
    };
};

const TicketsInspectorControls = ({ attributes, setAttributes }) => {
    return (
        <InspectorControls>
            <PanelBody title={__('General Style Settings')} initialOpen={true}>
                <RangeControl
                    label={__('Border Radius')}
                    value={attributes.containerBorderRadius || 0}
                    onChange={value => setAttributes({ containerBorderRadius: value })}
                    min={0}
                    max={20}
                />
                <PanelBody title={__('Primary Button Style')}>
                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Primary Button Text Color')}
                        </label>
                        <ColorPalette
                            value={attributes.primaryButtonTextColor}
                            onChange={value => setAttributes({ primaryButtonTextColor: value })}
                        />
                    </div>
                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Primary Button Background Color')}
                        </label>
                        <ColorPalette
                            value={attributes.primaryButtonBgColor}
                            onChange={value => setAttributes({ primaryButtonBgColor: value })}
                        />
                    </div>
                </PanelBody>
                <PanelBody title={__('Secondary Button Style')}>
                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Secondary Button Text Color')}
                        </label>
                        <ColorPalette
                            value={attributes.secondaryButtonTextColor}
                            onChange={value => setAttributes({ secondaryButtonTextColor: value })}
                        />
                    </div>
                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Secondary Button Background Color')}
                        </label>
                        <ColorPalette
                            value={attributes.secondaryButtonBgColor}
                            onChange={value => setAttributes({ secondaryButtonBgColor: value })}
                        />
                    </div>
                </PanelBody>
            </PanelBody>
        </InspectorControls>
    );
};

export default TicketsInspectorControls;
