import { Fragment, useState, useEffect } from '@wordpress/element';
import { InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import {
    PanelBody,
    PanelRow,
    RangeControl,
    Tooltip,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import './editor.scss';

const restInfo = window.fluent_support_vars.rest;
const basePath = restInfo.namespace + '/' + restInfo.version + '/';

export const GeneralInspectorSettings = ({ attributes, setAttributes }) => {
    const [mailboxes, setMailboxes] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        setIsLoading(true);
        apiFetch({
            path: basePath + 'mailboxes',
        }).then((res) => {
            setMailboxes(res.mailboxes || []);
            setIsLoading(false);
        }).catch(error => {
            console.error('Error fetching mailboxes:', error);
            setIsLoading(false);
        });
    }, []);

    const selectedMailbox = attributes.selectedMailbox || '';

    return (
        <Fragment>
            <PanelBody title={__('General Style Settings', 'fluent-support')} initialOpen={true}>
                <PanelRow>
                    <div className="fs_block_inspector_widget">
                        {isLoading ? (
                            <p>{__('Loading mailboxes...', 'fluent-support')}</p>
                        ) : (
                            <div>
                                <h3 className="label">{__('Select Mailbox', 'fluent-support')}</h3>
                                <select
                                    id="mailboxSelect"
                                    value={attributes.selectedMailbox}
                                    onChange={(event) => setAttributes({selectedMailbox: event.target.value})}
                                >
                                    <option value="">{__('Default Mailbox', 'fluent-support')}</option>
                                    {mailboxes.map(mailbox => (
                                        <option key={mailbox.id} value={mailbox.id}>
                                            {mailbox.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>
                </PanelRow>
                <RangeControl
                    label={__('Border Radius', 'fluent-support')}
                    value={attributes.containerBorderRadius || 0}
                    onChange={(value) => setAttributes({containerBorderRadius: value})}
                    min={1}
                    max={20}
                />

                <PanelColorSettings
                    title={
                        <div className="fs_panel_color_settings">
                            {__('Primary Button Style', 'fluent-support')}
                            <Tooltip text={__('Customize the "Create Ticket" and "Reply" buttons.', 'fluent-support')}>
                                <span className="dashicons dashicons-info" style={{ marginLeft: '5px', cursor: 'help' }}></span>
                            </Tooltip>
                        </div>
                    }
                    className="fs_panel_color_settings"
                    initialOpen={true}
                    colorSettings={[
                        {
                            value: attributes.primaryButtonTextColor,
                            onChange: (color) => setAttributes({primaryButtonTextColor: color}),
                            label: __('Text Color', 'fluent-support'),
                        },
                        {
                            value: attributes.primaryButtonBgColor,
                            onChange: (color) => setAttributes({ primaryButtonBgColor: color }),
                            label: __('Background Color', 'fluent-support'),
                        },
                    ]}
                />

                <PanelColorSettings
                    title={
                        <div className="fs_panel_color_settings">
                            {__('Secondary Button Style', 'fluent-support')}
                            <Tooltip text={__('Customize the "Browse File," "Refresh," and "Close" buttons.', 'fluent-support')}>
                                <span className="dashicons dashicons-info"
                                      style={{marginLeft: '5px', cursor: 'help'}}></span>
                            </Tooltip>
                        </div>
                    }
                    initialOpen={true}
                    className={'fs_panel_color_settings'}
                    colorSettings={[
                        {
                            value: attributes.secondaryButtonTextColor,
                            onChange: (color) => setAttributes({secondaryButtonTextColor: color}),
                            label: __('Text Color', 'fluent-support'),
                        },
                        {
                            value: attributes.secondaryButtonBgColor,
                            onChange: (color) => setAttributes({ secondaryButtonBgColor: color }),
                            label: __('Background Color', 'fluent-support'),
                        },
                    ]}
                />
            </PanelBody>
        </Fragment>
    );
};
