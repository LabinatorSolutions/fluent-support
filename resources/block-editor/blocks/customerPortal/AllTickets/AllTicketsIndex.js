const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;

import Edit from './edit';
import icons from '../../utils/icons';


registerBlockType('fluent-support/all-tickets', {
    title: __('Fluent Support All Tickets'),
    description: __('All Tickets Page'),
    category: 'design',
    icon: icons.fluentSupport,
    keywords: [__('fluent'), __('fluent support'), __('support'), __('tickets')],
    supports: {
        align: ['wide', 'full'],
        html: true
    },
    edit: Edit,
    save: () => null
});

