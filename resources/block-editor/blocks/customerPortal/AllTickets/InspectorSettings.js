/*eslint-disable*/
const { InspectorControls } = wp.blockEditor;
const { __ } = wp.i18n;
const {
    PanelBody,
    PanelRow,
    TextControl,
    SelectControl,
    CheckboxControl,
    ColorPalette,
    RangeControl
} = wp.components;

// Assuming you have a global variable for ticket categories/products
const productsVar = window.tickets_block?.products || {};
const products = Object.values(productsVar);

const InspectorSettings = props => {
    const {
        attributes: {
            title,
            productIds,
            period,
            perPage,
            showFilter,
            showPagination,
            noTicketsMessage,
            backgroundColor,
            textColor,
            borderRadius,
            loadingMessage,
            createButtonText
        }, setAttributes
    } = props;


    return (
        <InspectorControls>
            <PanelBody title={__("Style Settings")} initialOpen={false}>
                <PanelRow>
                    <div className="tickets_block_style_settings">
                        <div className="tickets_block_inspector_widget">
                            <label className="components-base-control__label">
                                {__('Background Color')}
                            </label>
                            <ColorPalette
                                value={backgroundColor}
                                onChange={(value) => setAttributes({ backgroundColor: value })}
                            />

                            <label className="components-base-control__label">
                                {__('Text Color')}
                            </label>
                            <ColorPalette
                                value={textColor}
                                onChange={(value) => setAttributes({ textColor: value })}
                            />

                            <RangeControl
                                label={__('Border Radius')}
                                value={borderRadius}
                                onChange={(value) => setAttributes({ borderRadius: value })}
                                min={0}
                                max={20}
                            />


                        </div>
                    </div>
                </PanelRow>
            </PanelBody>
        </InspectorControls>
    );
};

export default InspectorSettings;
