import React from 'react';
const { InspectorControls } = wp.blockEditor;
const { PanelBody, RangeControl } = wp.components;
const { __ } = wp.i18n;

import { GeneralInspectorSettings, generateStyles as generalGenerateStyles } from '../GeneralInspectorSettings';

export const generateStyles = (attributes) => {

    const generalStyles = generalGenerateStyles(attributes);

    return {
        ...generalStyles,
        inputFieldStyle: {
            borderRadius: `${attributes.ticketInputBorderRadius || 0}px`,
        }
    };
};

const CreateTicketInspectorControls = ({ attributes, setAttributes }) => {
    return (
        <InspectorControls>
            <GeneralInspectorSettings
                attributes={attributes}
                setAttributes={setAttributes}
            />
            <PanelBody title={__('Page wise Style Settings')} initialOpen={true}>
                <RangeControl
                    label={__('Input Field Border Radius')}
                    value={attributes.ticketInputBorderRadius || 0}
                    onChange={value => setAttributes({ticketInputBorderRadius: value})}
                    min={0}
                    max={20}
                />
            </PanelBody>
        </InspectorControls>
    );
};

export default CreateTicketInspectorControls;
