import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import icons from '../utils/icons';

// Name, attributes, supports and assets live in block.json. The block is
// dynamic: PHP renders it (BlockEditorHandler::renderBlock), so save() is null.
registerBlockType(metadata.name, {
    icon: icons.fluentSupport,
    edit: function (props) {
        const { attributes, setAttributes } = props;
        return (
            <Edit
                attributes={attributes}
                setAttributes={setAttributes}
            />
        );
    },
    save: function () {
        return null;
    },
} );
