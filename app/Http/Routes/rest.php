<?php

/**
 * @var $router FluentSupport\Framework\Rest\Rest
 */

//$router->get('/reports', 'ReportController@index');
//$router->get('/dashboard', 'DashboardController@index');
//$router->get('/chart/data', 'DashboardController@getChartData');
//

$router->prefix('tickets')->group(function ($router) {
    $router->get('/', 'TicketController@index');
    $router->get('/{ticket_id}', 'TicketController@getTicket')->int('ticket_id');

    $router->get('/{ticket_id}/widgets', 'TicketController@getTicketWidgets')->int('ticket_id');
    $router->post('/{ticket_id}/responses', 'TicketController@createResponse')->int('ticket_id');

    $router->post('/{ticket_id}/customer-responses', 'ConversationController@createCustomerReply')
        ->int('ticket_id');

    $router->put('/{ticket_id}/property', 'TicketController@updateTicketProperty')->int('ticket_id');

    $router->post('/{ticket_id}/close', 'TicketController@closeTicket')->int('ticket_id');
    $router->post('/{ticket_id}/re-open', 'TicketController@reOpenTicket')->int('ticket_id');

});

$router->prefix('customer-portal')->group(function ($router) {

    $router->get('public_options', 'CustomerPortalController@getPublicOptions');

    $router->get('tickets', 'CustomerPortalController@getTickets');
    $router->post('tickets', 'CustomerPortalController@createTicket');

    $router->get('tickets/{ticket_id}', 'CustomerPortalController@getTicket')->int('ticket_id');
    $router->post('tickets/{ticket_id}/responses', 'CustomerPortalController@createResponse')->int('ticket_id');

    $router->post('/tickets/{ticket_id}/close', 'CustomerPortalController@closeTicket')->int('ticket_id');
    $router->post('/tickets/{ticket_id}/re-open', 'CustomerPortalController@reOpenTicket')->int('ticket_id');

    $router->post('ticket_file_upload', 'CustomerPortalController@uploadTicketFiles');

});

$router->prefix('products')->group(function ($router) {
    $router->get('/', 'ProductController@index');
    $router->post('/', 'ProductController@create');
    $router->get('/{product_id}', 'ProductController@get');
    $router->post('/{product_id}', 'ProductController@create');
    $router->put('/{product_id}', 'ProductController@update');
    $router->delete('/{product_id}', 'ProductController@delete');
});

$router->get('me', 'TicketController@me');

$router->post('ticket_file_upload', 'UploaderController@uploadTicketFiles');

//$router->prefix('accounts')->name('accounts.')->group(function($router) {
//
//    $router->get('/', 'AccountController@index')->name('index');
//
//    $router->get('/{id}', 'AccountController@find')->int('id');
//
//    $router->post('/', 'AccountController@save');
//
//    $router->post('/{id}', 'AccountController@save')->int('id');
//
//    $router->delete('/{id}', 'AccountController@delete')->int('id');
//
//    $router->prefix('ledgers')->group(function($router) {
//
//        $router->get('/', 'LedgerController@index');
//
//        $router->get('/{id}', 'LedgerController@find')->int('id');
//
//        $router->post('/', 'LedgerController@save');
//
//        $router->post('/{id}', 'LedgerController@save')->int('id');
//
//        $router->delete('/{id}', 'LedgerController@delete')->int('id');
//
//        $router->prefix('entries')->group(function($router) {
//
//            $router->get('/', 'EntryController@index');
//
//            $router->get('/account/{id}', 'EntryController@entriesByAccount');
//
//            $router->get('/{id}', 'EntryController@find');
//
//            $router->post('/', 'EntryController@save');
//
//            $router->post('/{id}', 'EntryController@save');
//
//            $router->delete('/{id}', 'EntryController@delete');
//        });
//    });
//});
