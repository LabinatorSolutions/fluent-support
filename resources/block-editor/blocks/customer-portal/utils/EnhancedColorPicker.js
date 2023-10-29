const { __ } = wp.i18n;
const { Fragment } = wp.element;
const { Dropdown, ColorPicker, Button } = wp.components;
import { getColor, colors } from './Helper';

export default function EnhancedColorPicker({attributes, setAttributes, props}) {
    const { title, attributeName } = props;
    // Function to handle color change
    const handleColorChange = (value) => {
        // Check if the color has an alpha of 1 (fully opaque)
        // If it's fully opaque, use the hex value, otherwise use the RGB value
        setAttributes({ [attributeName]: value.hex ?? JSON.stringify(value.rgb) });
    };

    // Function to handle button click
    const handleButtonClick = (color) => {
        setAttributes({ [attributeName]: color });
    };

    // Function to clear the color
    const clearColor = () => {
        setAttributes({ [attributeName]: '' });
    };

    return (
        <Fragment>
            <div className={'fst-blocks-color-group-field'}>
                <div className={'fst-block-color-field'}>
                    <span>{title}</span>
                    <span className={'fst-block-color-preview-box'}>
                        <Dropdown
                            className="fst-components-color-palette__custom-color"
                            contentClassName="fst-components-color-palette__picker"
                            popoverProps={ { placement: 'bottom-start' } }
                            renderToggle={ ( { isOpen, onToggle } ) => (
                                <Button
                                    aria-expanded={ isOpen }
                                    onClick={ onToggle }
                                    variant="primary"
                                    style={{ background: getColor(attributes[attributeName]) }}
                                ></Button>
                            ) }
                            renderContent={() => (
                                <div>
                                    <div className="fst-blocks-classic-color-label">
                                        <ColorPicker
                                            color={
                                                attributes[attributeName] && attributes[attributeName][0] !== '#'
                                                    ? JSON.parse(attributes[attributeName])
                                                    : attributes[attributeName]
                                            }
                                            onChangeComplete={(value) => handleColorChange(value)}
                                        />
                                    </div>
                                    <div className={'fst-custom-color-palette'}>
                                        {colors.map((c, ind) => (
                                            <button
                                                key={ind}
                                                style={{ backgroundColor: c.color }}
                                                onClick={() => handleButtonClick(c.color)}
                                            ></button>
                                        ))}
                                        <button className={'fst-btn-clear'} onClick={clearColor}></button>
                                    </div>
                                </div>
                            )}
                        />
                    </span>
                </div>
            </div>
        </Fragment>
    );
}
