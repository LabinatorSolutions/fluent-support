<?php

namespace FluentSupport\App\Services\Blocks;

class BlockAttributes
{
    /**
     * @param array An array of attributes used for styling.
     */
    public static function CustomerPortalAttributes()
    {
        return [
            'title' => [
                'type' => 'string',
                'default' => 'All Tickets',
            ],
            'perPage' => [
                'type' => 'string',
                'default' => '10',
            ],
            'selectedFilter' => [
                'type' => 'string',
                'default' => 'all',
            ],
            'selectedProduct' => [
                'type' => 'string',
                'default' => 'all',
            ],
            'searchPlaceholder' => [
                'type' => 'string',
                'default' => 'Search...',
            ],
            'showPagination' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'showFilters' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'noTicketsMessage' => [
                'type' => 'string',
                'default' => 'No tickets found',
            ],
            'loadingMessage' => [
                'type' => 'string',
                'default' => 'Loading tickets...',
            ],
            'createButtonText' => [
                'type' => 'string',
                'default' => 'Create a New Ticket',
            ],
            'allTicketsHeaderBackgroundColor' => [
                'type' => 'string',
                'default' => '#FFFFFF',
            ],
            'createTicketButtonBorderRadius' => [
                'type' => 'number',
                'default' => 16,
            ],
            'buttonBorderRadius' => [
                'type' => 'number',
                'default' => 8,
            ],
            'createTicketButtonBackgroundColor' => [
                'type' => 'string',
                'default' => 'rgba(14, 18, 27, 1)',
            ],
            'createTicketButtonTextColor' => [
                'type' => 'string',
                'default' => 'rgba(255, 255, 255, 1)',
            ],
            'filterBorderRadius' => [
                'type' => 'number',
                'default' => 8,
            ],
            'allTicketsHeaderTextColor' => [
                'type' => 'string',
                'default' => 'rgba(14, 18, 27, 1)',
            ],
            'allTicketsPrimaryTextColor' => [
                'type' => 'string',
                'default' => '#000000',
            ],
            'allTicketsDescriptionColor' => [
                'type' => 'string',
                'default' => 'rgba(82, 88, 102, 1)',
            ],
            'allTicketsTableHeaderBorderRadius' => [
                'type' => 'number',
                'default' => 8,
            ],
            'allTicketsFooterTextColor' => [
                'type' => 'string',
                'default' => 'rgba(82, 88, 102, 1)',
            ],
            'allTicketsFooterButtonBorderRadius' => [
                'type' => 'number',
                'default' => 8,
            ],
            'allTicketsLogoutButtonVisibility' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'businessBoxId' => [
                'type' => 'number',
                'default' => '',
            ],
        ];
    }
}
