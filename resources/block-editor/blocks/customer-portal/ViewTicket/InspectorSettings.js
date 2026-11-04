import React from 'react';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, PanelRow } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { GeneralInspectorSettings } from '../GeneralInspectorSettings';

export const ViewTicketInspectorControls = ({ attributes, setAttributes }) => {
    return (
        <InspectorControls>
            <PanelBody title={__('Custom Page Style', 'fluent-support')} initialOpen={true}>
                <PanelRow>
                    <div className="fs_block_inspector_widget">
                        <h3 className="label">{__('Avatar Style', 'fluent-support')}</h3>
                        <select
                            value={attributes.avatarBorderRadius}
                            onChange={(event) => setAttributes({avatarBorderRadius: event.target.value})}
                        >
                            <option value="8px">{__('Square', 'fluent-support')}</option>
                            <option value="50%">{__('Rounded', 'fluent-support')}</option>
                        </select>
                    </div>
                </PanelRow>
            </PanelBody>
            <GeneralInspectorSettings
                attributes={attributes}
                setAttributes={setAttributes}
            />
        </InspectorControls>
    );
};

export default ViewTicketInspectorControls;
