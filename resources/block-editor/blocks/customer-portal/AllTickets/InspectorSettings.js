import React from 'react';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { GeneralInspectorSettings } from '../GeneralInspectorSettings';

const TicketsInspectorControls = ({ attributes, setAttributes }) => {
    return (
        <InspectorControls>
            <PanelBody title={__('Custom Page Style', 'fluent-support')} initialOpen={true}>
                <PanelRow>
                    <div className="fs_block_inspector_widget">
                        <h3 className="label">{__('Log Out Button', 'fluent-support')}</h3>
                        <select
                            value={attributes.showLogoutButton ? 'true' : 'false'} // Use 'true' or 'false' for display
                            onChange={(event) => setAttributes({ showLogoutButton: event.target.value === 'true' })} // Convert to boolean
                        >
                            <option value="true">{__('Show', 'fluent-support')}</option>
                            <option value="false">{__('Hide', 'fluent-support')}</option>
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

export default TicketsInspectorControls;
