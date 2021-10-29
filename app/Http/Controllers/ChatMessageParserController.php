<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Ticket;
use FluentSupportPro\App\Services\Integrations\Telegram\TelegramHelper;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\Framework\Request\Request;

class ChatMessageParserController extends Controller
{
    public function handleTelegramWebhook(Request $request, $token)
    {
        // Validate Token
        if(TelegramHelper::getWebhookToken() != $token) {
            return $this->sendSuccess([
                'message' => __('Bot Token could not be verified', 'fluent-support'),
                'status' => false
            ]);
        }

        $payload = $request->all();
        $responseData = TelegramHelper::parseTelegramBotPayload($payload);

        if(is_wp_error($responseData)) {
            do_action('fluent_support_telegram_payload_error', $responseData, $payload);
            return $this->sendSuccess([
                'message' => $responseData->get_error_message(),
                'type' => $responseData->get_error_code(),
                'status' => false
            ]);
        }

        $data = [
            'conversation_type' => 'response',
            'content' => $responseData['response_text'],
            'source' => 'telegram'
        ];

        $ticket = Ticket::find($responseData['ticket_id']);

        if(!$ticket) {
            return $this->sendSuccess([
                'message' => __('No ticket found', 'fluent-support'),
                'status' => false
            ]);
        }

        $agent = Agent::find($responseData['agent_id']);

        if(!$agent) {
            return $this->sendSuccess([
                'message' => __('No Agent found', 'fluent-support'),
                'status' => false
            ]);
        }

        (new ResponseService)->createResponse($data, $agent, $ticket);

        return $this->sendSuccess([
            'message' => __('Response has been successfully recorded', 'fluent-support'),
            'status' => true,
            'data' => $data
        ]);

    }
}
