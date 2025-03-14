import React from 'react';
const { Fragment } = wp.element;
const { InspectorControls, PanelColorSettings } = wp.blockEditor;
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

export const GeneralInspectorSettings = ({ attributes, setAttributes }) => {
    return (
        <Fragment>
            <PanelBody title={__('General Style Settings')} initialOpen={true}>
                <RangeControl
                    label={__('Border Radius')}
                    value={attributes.containerBorderRadius || 0}
                    onChange={value => setAttributes({ containerBorderRadius: value })}
                    min={0}
                    max={20}
                />

                <PanelColorSettings
                    title={__('Primary Button Style')}
                    initialOpen={true}
                    colorSettings={[
                        {
                            value: attributes.primaryButtonTextColor,
                            onChange: (color) => setAttributes({ primaryButtonTextColor: color }),
                            label: __('Text Color'),
                        },
                        {
                            value: attributes.primaryButtonBgColor,
                            onChange: (color) => setAttributes({ primaryButtonBgColor: color }),
                            label: __('Background Color'),
                        },
                    ]}
                />

                <PanelColorSettings
                    title={__('Secondary Button Style')}
                    initialOpen={true}
                    colorSettings={[
                        {
                            value: attributes.secondaryButtonTextColor,
                            onChange: (color) => setAttributes({ secondaryButtonTextColor: color }),
                            label: __('Text Color'),
                        },
                        {
                            value: attributes.secondaryButtonBgColor,
                            onChange: (color) => setAttributes({ secondaryButtonBgColor: color }),
                            label: __('Background Color'),
                        },
                    ]}
                />
            </PanelBody>
        </Fragment>
    );
};
