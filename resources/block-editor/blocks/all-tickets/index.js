const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;
import Edit from './edit';
import attributes from './attributes';
import metadata from './block.json';

//import 'bootstrap/dist/css/bootstrap.min.css';
registerBlockType( metadata.name, {
    title: __('All Tickets'),
    description: __('Customer Portal Editor Block'),
    textdomain: 'fluent-support',
    category: 'fluent-support',
    icon: 'smiley',
    attributes,
    /**
     * @see ./edit.js
     */
    edit: Edit,
    save: function (props) {
        return null;
    },
} );
