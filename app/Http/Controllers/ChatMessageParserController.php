<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\Framework\Request\Request;
use FluentSupportPro\App\Services\Integrations\Slack\SlackNotification;

/**
 *  ChatMessageParserController class is responsible for getting information from integrated 3rd party request and response
 * @package FluentSupport\App\Http\Controllers
 *
 * @version 1.0.0
 */

class ChatMessageParserController extends Controller
{
    /**
     * handleTelegramWebhook will get the content from telegram, check the data validity and create response in a ticket
     * @param Request $request
     * @param $token
     * @return mixed
     */
    public function handleTelegramWebhook(Request $request, $token)
    {
        if (!defined('FLUENTSUPPORTPRO')) {
            return $this->sendSuccess([
                'message' => __('Telegram Integration requires pro version of Fluent Support', 'fluent-support'),
                'status'  => false
            ]);
        }

        // Validate Token
        if (\FluentSupportPro\App\Services\Integrations\Telegram\TelegramHelper::getWebhookToken() != $token) {
            return $this->sendSuccess([
                'message' => __('Bot Token could not be verified', 'fluent-support'),
                'status'  => false
            ]);
        }

        $payload = $request->all();
        $responseData = \FluentSupportPro\App\Services\Integrations\Telegram\TelegramHelper::parseTelegramBotPayload($payload);

        if (is_wp_error($responseData)) {
            /*
             * Action on telegram payload error when replying ticket from telegram
             *
             * @since v1.0.0
             * @param object $responseData
             * @param object $payload
             */
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

    /**
     * handleSlackEvent will get content from slack,check the data validity and create response in a ticket
     * @param Request $request
     * @param $token
     * @return array|\ArrayAccess|mixed
     */
    public function handleSlackEvent(Request $request, $token)
    {
        if (!defined('FLUENTSUPPORTPRO')) {
            return $this->sendSuccess([
                'message' => __('Slack Integration requires pro version of Fluent Support', 'fluent-support'),
                'status'  => false
            ]);
        }

        // Validate Token
        if (\FluentSupportPro\App\Services\Integrations\Slack\SlackHelper::getWebhookToken() != $token) {
            return $this->sendSuccess([
                'message' => __('Bot Token could not be verified', 'fluent-support'),
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
