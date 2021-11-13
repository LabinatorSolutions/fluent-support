<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupportPro\App\Services\Integrations\Slack\SlackHelper;
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
            do_action('fluent_support/telegram_payload_error', $responseData, $payload);
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

    public function handleSlackEvent(Request $request)
    {
        $slackSettings = SlackHelper::getSettings();

        if($request->get('type') == 'url_verification') {
            return $request->get('challenge');
        }

        if ($slackSettings['reply_from_slack'] == 'yes'){

            $eventSubType = $request->get('event.subtype');
            $channelId = $request->get('event.channel');

            if($slackSettings['channel_id'] == $channelId &&  $eventSubType== 'message_changed') {
                $user = $request->get('event.message.user');
                $message = $request->get('event.message');
                $ticketId = explode('#', $message['root']['attachments'][0]['title']);
                $ticket = Ticket::find(intval($ticketId[1]));

                $userSlackObject = Meta::where('key','slack_user_id')->where('value', $user)->first();
                $agent = Agent::where('id', $userSlackObject->object_id)->first();

                $data = [
                    'person_id' => $agent->user_id,
                    'ticket_id' => $ticket->id,
                    'conversation_type' => 'response',
                    'content' => $message['text'],
                    'source' => 'slack'
                ];

                return (new ResponseService)->createResponse($data, $agent, $ticket);
            }
        }
    }

}
