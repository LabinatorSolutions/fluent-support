//const { useBlockProps } = wp.blocks;
const { TextControl } = wp.components;

export default function Edit( { attributes, setAttributes } ) {
    return (
        <div className={ attributes.className }>
            <input className="rounded-xs w-full border-neutral-200 p-2.5 pl-8 text-sm leading-none focus:shadow-none focus:border-primary-500 outline-none" type="text" name="wildcard" placeholder="Product Name"
                   value=""/>
        </div>
    );
}
