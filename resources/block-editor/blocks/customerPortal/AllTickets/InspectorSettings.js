import React from 'react';
const { InspectorControls } = wp.blockEditor;
const { PanelBody } = wp.components;
const { __ } = wp.i18n;

import { GeneralInspectorSettings, generateStyles as generalGenerateStyles } from '../GeneralInspectorSettings';

export const generateStyles = (attributes) => {

    const generalStyles = generalGenerateStyles(attributes);

    return {
        ...generalStyles,
    };
};

const TicketsInspectorControls = ({ attributes, setAttributes }) => {
    return (
        <InspectorControls>
            <GeneralInspectorSettings
                attributes={attributes}
                setAttributes={setAttributes}
            />
            {/* Add any TicketsInspectorControls specific panels here */}
        </InspectorControls>
    );
};

export default TicketsInspectorControls;
