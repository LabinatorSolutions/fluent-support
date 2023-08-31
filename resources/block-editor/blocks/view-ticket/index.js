const { registerBlockType } = wp.blocks;
import Edit from './edit';

registerBlockType( 'fluent-support/view-ticket', {
    title: 'View Ticket',
    description: 'Fluent Support View Ticket Block',
    textdomain: 'fluent-support',
    category: 'fluent-support',
    icon: 'smiley',
    /**
     * @see ./edit.js
     */
    edit: Edit,
    save: function (props) {
        return null;
    },
} );
