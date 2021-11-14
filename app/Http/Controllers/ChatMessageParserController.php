<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\Framework\Request\Request;
use FluentSupportPro\App\Services\Integrations\Slack\SlackNotification;

class ChatMessageParserController extends Controller
{
    public function handleTelegramWebhook(Request $request, $token)
    {
        if (!defined('FLUENTSUPPORTPRO')) {
            return $this->sendSuccess([
                'message' => 'Telegram Integration requires pro version of Fluent Support',
                'status'  => false
            ]);
        }

        // Validate Token
        if (\FluentSupportPro\App\Services\Integrations\Telegram\TelegramHelper::getWebhookToken() != $token) {
            return $this->sendSuccess([
                'message' => 'Bot Token could not be verified',
                'status'  => false
            ]);
        }

        $payload = $request->all();
        $responseData = \FluentSupportPro\App\Services\Integrations\Telegram\TelegramHelper::parseTelegramBotPayload($payload);

        if (is_wp_error($responseData)) {
            do_action('fluent_support/telegram_payload_error', $responseData, $payload);
            return $this->sendSuccess([
                'message' => $responseData->get_error_message(),
                'type'    => $responseData->get_error_code(),
                'status'  => false
            ]);
        }

        $data = [
            'conversation_type' => 'response',
            'content'           => $responseData['response_text'],
            'source'            => 'telegram'
        ];

        $ticket = Ticket::find($responseData['ticket_id']);

        if (!$ticket) {
            return $this->sendSuccess([
                'message' => __('No ticket found', 'fluent-support'),
                'status'  => false
            ]);
        }

        $agent = Agent::find($responseData['agent_id']);

        if (!$agent) {
            return $this->sendSuccess([
                'message' => __('No Agent found', 'fluent-support'),
                'status'  => false
            ]);
        }

        (new ResponseService)->createResponse($data, $agent, $ticket);

        return $this->sendSuccess([
            'message' => __('Response has been successfully recorded', 'fluent-support'),
            'status'  => true,
            'data'    => $data
        ]);

    }

    public function handleSlackEvent(Request $request, $token)
    {
        if (!defined('FLUENTSUPPORTPRO')) {
            return $this->sendSuccess([
                'message' => 'Slack Integration requires pro version of Fluent Support',
                'status'  => false
            ]);
        }

        // Validate Token
        if (\FluentSupportPro\App\Services\Integrations\Slack\SlackHelper::getWebhookToken() != $token) {
            return $this->sendSuccess([
                'message' => 'Bot Token could not be verified',
                'status'  => false
            ]);
        }

        if ($request->get('type') == 'url_verification') {
            return $request->get('challenge');
        }

        $event = $request->get('event');

        $result = (new SlackNotification())->processSlackEvent($event);

        return $this->sendSuccess([
            'message' => 'received',
            'result' => $result
        ]);
    }

}
