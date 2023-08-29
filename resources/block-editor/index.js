const { registerBlockType } = wp.blocks;
//import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import Save from './save';

registerBlockType( 'fluent-support/customer-portal', {
    title: 'Fs Customer Portal',
    description: 'Customer Portal Editor Block',
    textdomain: 'fluent-support',
    category: 'design',
    icon: 'smiley',
    /**
     * @see ./edit.js
     */
    edit: Edit,
    /**
     * @see ./save.js
     */
    save: Save,
} );
