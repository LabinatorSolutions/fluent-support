//const { useBlockProps } = wp.blocks;
const { TextControl } = wp.components;

export default function Edit( { attributes, setAttributes } ) {
    return (
        <TextControl
            label="Message"
            value={ attributes.message }
            onChange={ ( val ) => setAttributes( { message: val } ) }
        />
    );
}
