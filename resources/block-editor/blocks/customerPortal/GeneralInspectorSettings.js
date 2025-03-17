const { Fragment, useState, useEffect } = wp.element;
const { InspectorControls, PanelColorSettings } = wp.blockEditor;
const {
    PanelBody,
    PanelRow,
    RangeControl,
    SelectControl
} = wp.components;
const { __ } = wp.i18n;
const { apiFetch } = wp;

import './editor.scss';

const restInfo = window.fluent_support_vars.rest;
const basePath = restInfo.namespace + '/' + restInfo.version + '/';

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

    // Make sure selectedMailbox is properly initialized
    const selectedMailbox = attributes.selectedMailbox || '';

    return (
        <Fragment>
            <PanelBody title={__('General Style Settings')} initialOpen={true}>
                <PanelRow>
                    <div className="fs_block_inspector_widget">
                        {isLoading ? (
                            <p>{__('Loading mailboxes...')}</p>
                        ) : (
                            <div>
                                <h3 className="label">{__('Select Mailbox')}</h3>
                                <select
                                    id="mailboxSelect"
                                    value={attributes.selectedMailbox}
                                    onChange={(event) => setAttributes({selectedMailbox: event.target.value})}
                                >
                                    <option value="">{__('Select a Mailbox')}</option>
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
                    label={__('Border Radius')}
                    value={attributes.containerBorderRadius || 0}
                    onChange={(value) => setAttributes({containerBorderRadius: value})}
                    min={0}
                    max={20}
                />

                <PanelColorSettings
                    title={__('Primary Button Style')}
                    className="fs_panel_color_settings"
                    initialOpen={true}
                    colorSettings={[
                        {
                            value: attributes.primaryButtonTextColor,
                            onChange: (color) => setAttributes({primaryButtonTextColor: color}),
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
                    className={'fs_panel_color_settings'}
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
