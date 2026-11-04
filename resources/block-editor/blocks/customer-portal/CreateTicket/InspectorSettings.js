import React from 'react';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { GeneralInspectorSettings } from '../GeneralInspectorSettings';

const CreateTicketInspectorControls = ({ attributes, setAttributes }) => {
    return (
        <InspectorControls>
            <PanelBody title={__('Custom Page Style', 'fluent-support')} initialOpen={true}>
                <RangeControl
                    label={__('Input Field Border Radius', 'fluent-support')}
                    value={attributes.ticketInputBorderRadius || 0}
                    onChange={value => setAttributes({ticketInputBorderRadius: value})}
                    min={0}
                    max={20}
                />
            </PanelBody>
            <GeneralInspectorSettings
                attributes={attributes}
                setAttributes={setAttributes}
            />
        </InspectorControls>
    );
};

export default CreateTicketInspectorControls;
