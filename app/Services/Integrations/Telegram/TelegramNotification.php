<?php

namespace FluentSupport\App\Services\Integrations\Telegram;

use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Integrations\NotificationIntegrationBase;
use FluentSupport\Framework\Support\Arr;

class TelegramNotification extends NotificationIntegrationBase
{
    public $key = 'telegram_settings';

    public function __construct()
    {
        $this->registerActions();
    }

    public function registerActions()
    {
        add_action('fluent_support/ticket_created', function ($ticket, $customer) {
            if ($this->isNotificationActivated('ticket_created')) {
                $this->sendNotification([
                    'ticket' => $ticket,
                    'person' => $customer
                ], 'ticket_created');
            }
        }, 20, 2);

        add_action('fluent_support/ticket_closed', function ($ticket, $person) {
            if ($this->isNotificationActivated('ticket_closed')) {
                $this->sendNotification([
                    'ticket' => $ticket,
                    'person' => $person
                ], 'ticket_closed');
            }
        }, 20, 2);

        add_action('fluent_support/response_added_by_customer', function ($response, $ticket, $customer) {
            if ($this->isNotificationActivated('response_added_by_customer')) {
                $this->sendNotification([
                    'ticket'   => $ticket,
                    'response' => $response,
                    'person'   => $customer
                ], 'response_added_by_customer');
            }
        }, 20, 3);

        add_action('fluent_support/response_added_by_agent', function ($response, $ticket, $customer) {
            if ($this->isNotificationActivated('response_added_by_agent')) {
                $this->sendNotification([
                    'ticket'   => $ticket,
                    'response' => $response,
                    'person'   => $customer
                ], 'response_added_by_customer');
            }
        }, 20, 3);

    }

    public function getSettings($withFields = false)
    {
        $data = [
            'settings' => TelegramHelper::getSettings()
        ];

        if ($withFields) {
            $data['fields'] = $this->getFields();
        }

        return $data;
    }

    public function getFields()
    {
        return [
            'title'       => 'Telegram Notification Settings',
            'fields'      => [
                'bot_token'            => [
                    'type'        => 'input-text',
                    'data_type'   => 'password',
                    'label'       => 'Bot Token',
                    'placeholder' => 'Bot Token',
                    'help'        => 'Enter your Telegram Bot Token'
                ],
                'chat_id'              => [
                    'type'        => 'input-text',
                    'data_type'   => 'text',
                    'placeholder' => 'Chat ID',
                    'label'       => 'Default Channel/Group Chat ID',
                    'help'        => 'Enter your Telegram API channel user ID, You can also use message id. Please check documentation for more details.'
                ],
                'notification_events'  => [
                    'type'    => 'checkbox-group',
                    'label'   => 'Notification Events',
                    'options' => [
                        'ticket_created'             => 'Ticket Created',
                        'ticket_closed'              => 'Ticket Closed',
                        'response_added_by_agent'    => 'Replied by Agent',
                        'response_added_by_customer' => 'Replied By Customer'
                    ]
                ],
                'test_message'         => [
                    'placeholder' => 'Test Message to send right now',
                    'type'        => 'input-text',
                    'data_type'   => 'textarea',
                    'label'       => 'Test Message (Optional)',
                    'help'        => 'Enter message to send now as test'
                ],
                'status'               => [
                    'type'           => 'inline-checkbox',
                    'true_label'     => 'yes',
                    'false_label'    => 'no',
                    'label'          => '',
                    'checkbox_label' => 'Enable Telegram Notifications'
                ],
                'reply_from_telegram'  => [
                    'type'           => 'inline-checkbox',
                    'true_label'     => 'yes',
                    'false_label'    => 'no',
                    'label'          => '',
                    'checkbox_label' => 'Enable Reply From Telegram (Agent can directly reply from telegram)',
                    'dependency'     => [
                        'depends_on' => 'status',
                        'operator'   => '=',
                        'value'      => 'yes'
                    ]
                ],
                'reply_from_html'      => [
                    'type'          => 'html-viewer',
                    'wrapper_class' => 'fs_highlight',
                    'html'          => 'Your support agents can easily reply from telegram by replying to telegram. </br>Please make sure support agent has telegram id set to the profile. <a href="https://fluentsupport.com/docs/telegram-integration" target="_blank">Learn More about this feature</a>',
                    'dependency'    => [
                        'depends_on' => 'reply_from_telegram',
                        'operator'   => '=',
                        'value'      => 'yes'
                    ]
                ],
                'reply_webhook_statys' => [
                    'type'       => 'html-viewer',
                    'html'       => 'Webhook is currently activated',
                    'dependency' => [
                        'depends_on' => 'webhook_activated',
                        'operator'   => '=',
                        'value'      => 'yes'
                    ]
                ]
            ],
            'button_text' => 'Save Telegram Settings'
        ];
    }

    public function saveSettings($settings)
    {
        $prevSettings = TelegramHelper::getSettings();

        if (empty($settings['chat_id']) || empty($settings['bot_token'])) {
            $settings['status'] = 'no';
            $this->maybeRemoveWebhook($prevSettings);
            $settings['webhook_activated'] = 'no';
            return $this->save($settings);
        }

        if ($prevSettings['bot_token'] != $settings['bot_token'] || $settings['status'] == 'no' || $settings['reply_from_telegram'] == 'no') { // bot token changed
            $this->maybeRemoveWebhook($prevSettings);
            $settings['webhook_activated'] = 'no';
        }


        $apiSettings = $settings;

        // Verify API key now
        try {
            $api = $this->getApiClient($settings);

            $apiStatus = $api->getMe();

            if (is_wp_error($apiStatus)) {
                throw new \Exception($apiStatus->get_error_message());
            }

            $message = Arr::get($settings, 'test_message', '');
            if ($message) {
                $api->setChatId($apiSettings['chat_id']);
                $result = $api->sendMessage($message);
                if (is_wp_error($result)) {
                    $responseMessage = 'Your api key is right but, the message could not be sent to the provided chat id. Error: ' . $result->get_error_message();
                    throw new \Exception($responseMessage);
                }
            }

            if ($settings['webhook_activated'] == 'no' && $settings['bot_token'] && $settings['reply_from_telegram'] == 'yes') {
                $response = $this->maybeSetWebhook($settings);
                if (is_wp_error($response)) {
                    $apiSettings['webhook_activated'] = 'no';
                    throw new \Exception($response->get_error_message());
                } else {
                    $apiSettings['webhook_activated'] = 'yes';
                }
            }

            $apiSettings['test_message'] = '';

            return $this->save($apiSettings);

        } catch (\Exception $exception) {
            $apiSettings['status'] = 'no';
            $settings['webhook_activated'] = 'no';
            $this->save($apiSettings);
            return new \WP_Error($exception->getMessage());
        }
    }

    protected function getApiClient($settings = false, $ticket = false)
    {
        if (!$settings) {
            $settings = $this->get();
        }

        if ($ticket) {
            $agent = $ticket->agent;
            if ($agent && $chatId = $agent->getMeta('telegram_chat_id')) {
                $settings['chat_id'] = $chatId;
            }
        }

        return new TelegramApi(
            $settings['bot_token'],
            $settings['chat_id']
        );
    }

    public function sendNotification($data, $type = false)
    {
        $message = '';

        if ($type == 'ticket_created') {
            $message = $this->ticketCreatedMessage($data['ticket'], $data['person']);
        } else if ($type == 'response_added_by_customer') {
            $message = $this->replyMessage($data['ticket'], $data['response']);
        } else if ($type == 'ticket_closed') {
            $message = $this->ticketClosed($data['ticket'], $data['person']);
        }

        if ($message) {
            return $this->getApiClient(false, $data['ticket'])->sendMessage($message, 'html');
        }
    }

    public function isNotificationActivated($type)
    {
        $settings = $this->get();
        if (!$settings || $settings['status'] != 'yes') {
            return false;
        }
        $events = Arr::get($settings, 'notification_events', []);

        return in_array($type, $events);
    }

    private function ticketCreatedMessage($ticket, $person)
    {
        $message = '<b>New Ticket Submitted by ' . $person->full_name . ' (#' . $ticket->id . ')</b>' . "\n";
        $message .= '<code>' . esc_html(\mb_substr($ticket->title, 0, 30)) . '</code>' . "\n";
        $message .= $this->clearText($ticket->content) . "\n";
        if ($ticket->product) {
            $message .= '<i>#' . preg_replace('~[^\pL\d]+~u', '_', $ticket->product->title) . '</i>' . "\n";
        }
        $message .= '<u><a href="' . $this->getTicketEditLink($ticket) . '">View Ticket</a></u>';
        return $message;
    }

    private function replyMessage($ticket, $response)
    {
        $message = '<b>Reply: ' . esc_html(\mb_substr($ticket->title, 0, 30)) . ' #' . $ticket->id . '</b>' . "\n";
        $message .= $this->clearText($response->content) . "\n";
        $message .= '<u><a href="' . $this->getTicketEditLink($ticket) . '">View Reply</a></u>';
        return $message;
    }

    private function ticketClosed($ticket, $person)
    {
        $message = '<b>Ticket  Closed by ' . $person->full_name . '</b>' . "\n";;
        $message .= '<pre>' . $ticket->title . '</pre> ';
        $message .= ' <a href="' . $this->getTicketEditLink($ticket) . '">(#' . $ticket->id . ')</a>';
        return $message;
    }

    private function clearText($html)
    {
        return preg_replace("/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags($html))));
    }

    private function getTicketEditLink($ticket)
    {
        $adminUrl = Helper::getPortalAdminBaseUrl();
        return $adminUrl . 'tickets/' . $ticket->id . '/view';
    }

    protected function maybeRemoveWebhook($settings)
    {
        if (Arr::get($settings, 'webhook_activated') == 'yes' && Arr::get($settings, 'bot_token')) {
            return (new TelegramApi())->deleteBotWebhook(Arr::get($settings, 'bot_token'));
        }
    }

    protected function maybeSetWebhook($settings)
    {
        if (Arr::get($settings, 'webhook_activated') != 'yes' && Arr::get($settings, 'bot_token')) {
            return (new TelegramApi())->setBotWebhook(Arr::get($settings, 'bot_token'));
        }

        return false;
    }
}
