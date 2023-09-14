const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;
import Edit from './edit';
import icons from '../../icons';
registerBlockType( 'fluent-support/customer-portal', {
    apiVersion: 2,
    version: "0.1.0",
    title: __('Customer Portal'),
    description: __('Add the Customer Portal from where user will submit tickets and manage their tickets.'),
    textdomain: 'fluent-support',
    category: 'design',
    icon: icons.fluentSupport,
    /**
     * @see ./attributes.js
     */
    /**
     * @see ./edit.js
     */
    edit: function (props) {
        const { attributes, setAttributes } = props;
        return (
            <Edit
                attributes={attributes}
                setAttributes={setAttributes}
            />
        );
    },
    save: function (props) {
        return null;
    },
} );
