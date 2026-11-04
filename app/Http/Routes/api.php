<?php

defined('ABSPATH') or die;

/**
 * @var $router FluentSupport\Framework\Http\Router
 */

$router->prefix('mailboxes')->withPolicy('AdminSettingsPolicy')->group(function ($router) {
    $router->get('/', 'MailBoxController@index');
    $router->post('/', 'MailBoxController@save');
    $router->get('/{id}', 'MailBoxController@get')->int('id');
    $router->put('/{id}', 'MailBoxController@update')->int('id');
    $router->delete('/{id}', 'MailBoxController@delete')->int('id');
    $router->put('/{id}/move_tickets', 'MailBoxController@moveTickets')->int('id');

    $router->get('/{id}/tickets', 'MailBoxController@getTickets')->int('id');

    $router->get('/{id}/email_settings', 'MailBoxController@getEmailSettings')->int('id');
    $router->get('/{id}/email_configs', 'MailBoxController@getEmailsSetups');
    $router->put('/{id}/email_settings', 'MailBoxController@saveEmailSettings')->int('id');
    $router->put('/{id}/set_default', 'MailBoxController@setAsDefault')->int('id');
});

$router->prefix('tickets')->withPolicy('AgentTicketPolicy')->group(function ($router) {

    $router->get('/', 'TicketController@index');
    $router->post('/', 'TicketController@createTicket');
    $router->get('/sidebar-counts', 'TicketController@getSidebarCounts');
    $router->get('/label-search', 'TicketController@fetchLabelSearch');
    $router->post('/label-search', 'TicketController@storeOrUpdateLabelSearch');
    $router->delete('/{label_search_id}/label-search', 'TicketController@deleteLabelSearch')->int('label_search_id');

    $router->get('/{ticket_id}/mentionable-agents', 'TicketController@getMentionableAgents')->int('ticket_id');
    $router->get('/{ticket_id}', 'TicketController@getTicket')->int('ticket_id');

    $router->get('/{ticket_id}/widgets', 'TicketController@getTicketWidgets')->int('ticket_id');
    $router->post('/{ticket_id}/responses', 'ResponseController@createResponse')->int('ticket_id');

    $router->post('/{ticket_id}/draft', 'ResponseController@createOrUpdatDraft')->int('ticket_id');
    $router->get('/{ticket_id}/draft', 'ResponseController@getDraft')->int('ticket_id');
    $router->delete('/{draft_id}/draft', 'ResponseController@deleteDraft')->int('draft_id');

    $router->get('/{ticket_id}/live_activity', 'TicketController@getLiveActivity')->int('ticket_id');
    $router->delete('/{ticket_id}/live_activity', 'TicketController@removeLiveActivity')->int('ticket_id');

    $router->put('/{ticket_id}/responses/{response_id}', 'ResponseController@updateResponse')
        ->int('ticket_id')
        ->int('response_id');

    $router->put('/{ticket_id}/approve_draft_response/{response_id}', 'ResponseController@approveDraftResponse')
        ->int('ticket_id')
        ->int('response_id');

    $router->delete('/{ticket_id}/responses/{response_id}', 'ResponseController@deleteResponse')
        ->int('ticket_id')
        ->int('response_id');

    $router->put('/{ticket_id}/property', 'TicketController@updateTicketProperty')->int('ticket_id');
    $router->put('/{ticket_id}/content', 'TicketController@updateTicketContent')->int('ticket_id');

    $router->post('/{ticket_id}/tags', 'TicketController@addTag')->int('ticket_id');
    $router->delete('/{ticket_id}/tags/{tag_id}', 'TicketController@detachTag')->int('ticket_id')->int('tag_id');

    $router->post('/{ticket_id}/close', 'TicketController@closeTicket')->int('ticket_id');
    $router->delete('/{ticket_id}/delete', 'TicketController@deleteTicket')->int('ticket_id');
    $router->post('/{ticket_id}/re-open', 'TicketController@reOpenTicket')->int('ticket_id');
    $router->put('/{ticket_id}/change-customer', 'TicketController@changeTicketCustomer')->int('ticket_id');
    $router->get('/{ticket_id}/custom-data', 'TicketController@getTicketCustomData')->int('ticket_id');

    $router->get('fluent-booking/event-types', 'TicketController@getFluentBookingEventTypes');
    $router->get('/{ticket_id}/fluent-booking/availability', 'TicketController@getFluentBookingAvailability')->int('ticket_id');
    $router->post('/{ticket_id}/fluent-booking/booking-link', 'TicketController@createFluentBookingLink')->int('ticket_id');
    $router->get('/{ticket_id}/fluent-booking/meetings', 'TicketController@getFluentBookingMeetings')->int('ticket_id');

    $router->post('bulk-actions', 'TicketController@doBulkActions'); // close_tickets, delete_tickets, reopen_tickets, assign_agent, assign_agent_group, assign_tags, remove_tags, change_priority, change_product
    $router->post('bulk-reply', 'ResponseController@doBulkReplies');

    $router->post('sync-fluentcrm-tags', 'TicketController@syncFluentCrmTags');
    $router->post('sync-fluentcrm-lists', 'TicketController@syncFluentCrmLists');

    $router->get('fluent-boards/boards', 'FluentBoardsController@getBoards');
    $router->get('fluent-boards/stages/{board_id}', 'FluentBoardsController@getStages')->int('board_id');
    $router->post('fluent-boards/stages', 'FluentBoardsController@createTask');


});

$router->get('widgets', 'WidgetsController')->withPolicy('AgentTicketPolicy');

// notifications/*: app/Modules/Notifications/routes.php.

$router->prefix('products')->withPolicy('AdminSettingsPolicy')->group(function ($router) {
    $router->get('/', 'ProductController@index');
    $router->post('/', 'ProductController@create');
    $router->get('/{product_id}', 'ProductController@get')->int('product_id');
    $router->post('/{product_id}', 'ProductController@create')->int('product_id');
    $router->put('/{product_id}', 'ProductController@update')->int('product_id');
    $router->delete('/{product_id}', 'ProductController@delete')->int('product_id');
});

$router->prefix('settings')->withPolicy('AdminSettingsPolicy')->group(function ($router) {
    $router->get('/', 'SettingsController@getSettings');
    $router->post('/', 'SettingsController@saveSettings');
    $router->get('/integration-settings', 'SettingsController@getIntegrationSettings');
    $router->get('/integration', 'IntegrationController@getSettings');
    $router->post('/integration', 'IntegrationController@saveSettings');
    $router->get('/pages', 'SettingsController@getPages');
    $router->post('/setup', 'SettingsController@setupPortal');
    $router->post('/setup-installation', 'SettingsController@setupInstallation');
    $router->post('/recaptcha-settings', 'SettingsController@saveReCaptchaSettings');
    $router->get('/recaptcha-settings', 'SettingsController@getReCaptchaSettings');
    $router->get('/integration-statuses', 'SettingsController@integrationStatuses');

    $router->get('/fluentcrm-settings', 'SettingsController@getFluentCRMSettings');
    $router->post('/install-fluentcrm', 'SettingsController@installFluentCRM');

    // Upload Settings
    $router->get('/remote-upload-settings', 'SettingsController@getRemoteUploadSettings');
    $router->post('/update-remote-upload-driver', 'SettingsController@updateRemoteUploadDriver');
    $router->post('/remote-upload-options', 'SettingsController@updateRemoteUploadOptions');
    $router->post('/remote-upload-retry', 'SettingsController@retryRemoteUploads');

    $router->get('/ai-integration', 'SettingsController@getAIProviderSettings');
    $router->post('/ai-integration', 'SettingsController@saveAIProviderSettings');
    $router->get('/settings-menu', 'SettingsController@getSettingsMenu');

    $router->get('/mcp', 'McpSettingsController@getStatus');
    $router->post('/mcp/toggle', 'McpSettingsController@toggle');
    $router->post('/mcp/install-adapter', 'McpSettingsController@installAdapter');
    $router->get('/mcp/config-snippets', 'McpSettingsController@getConfigSnippets');
});

// system-tools/*: app/Modules/SystemTools/routes.php.
// customer-portal/*, me, options/countries and ticket_file_upload: app/Modules/CustomerPortal/routes.php.

// Chat replies (public/telegram_bot_response, slack_response, twilio-response): Pro app/Modules/Chat/routes.php.
$router->prefix('public')->withPolicy('PublicPolicy')->group(function ($router) {
    $router->get('/authorize', 'AuthorizeController@handleHelpScoutAuthorization');
});

$router->prefix('activity-logger')->withPolicy('ActivityLoggerPolicy')->group(function ($router) {
    $router->get('/', 'ActivityLoggerController@getActivities');
    $router->get('/settings', 'ActivityLoggerController@getSettings');
    $router->post('/settings', 'ActivityLoggerController@updateSettings');
});

$router->prefix('ai-activity-logger')->withPolicy('ActivityLoggerPolicy')->group(function ($router) {
    $router->get('/', 'AIActivityLoggerController@getAIActivities');
    $router->post('/settings', 'AIActivityLoggerController@updateSettings');
    $router->get('/settings', 'AIActivityLoggerController@getSettings');
});

// signup, login, two_fa, reset_pass: app/Modules/Auth/routes.php.

// ticket_importer/*: app/Modules/TicketImport/routes.php.

