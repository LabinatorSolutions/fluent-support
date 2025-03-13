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

export const ViewTicketInspectorControls = ({ attributes, setAttributes }) => {
    return (
        <InspectorControls>
            <GeneralInspectorSettings
                attributes={attributes}
                setAttributes={setAttributes}
            />
            <PanelBody title={__('Page wise Style Settings')} initialOpen={true}>
                {/* Add any ViewTicketInspectorControls specific controls here */}
            </PanelBody>
        </InspectorControls>
    );
};

export default ViewTicketInspectorControls;
