const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;
import Edit from './edit';
import attributes from './attributes';
import metadata from './block.json';
import icons from '../../icons';
registerBlockType( metadata.name, {
    title: __('Customer Portal'),
    description: __('Add the Customer Portal from where user will submit tickets and manage their tickets.'),
    textdomain: 'fluent-support',
    category: 'design',
    icon: icons.fluentSupport,
    attributes,
    /**
     * @see ./edit.js
     */
    edit: Edit,
    save: function (props) {
        return null;
    },
} );
