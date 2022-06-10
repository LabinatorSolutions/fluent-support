<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Services\ThirdParty\HandleTelegramEvent;
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
     * @param HandleTelegramEvent $handler
     * @param string $token
     * @throws Exception
     * @return mixed
     */
    public function handleTelegramWebhook(Request $request, HandleTelegramEvent $handler, $token)
    {
        try {
            return $this->sendSuccess([
                'message' => __('Response has been successfully recorded', 'fluent-support'),
                'status'  => true,
                'data'    => $handler->handleEvent($request->all(), $token)
            ]);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage(),
                'status'  => false
            ]);
        }
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
