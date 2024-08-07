<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\AIActivityLogs;
use FluentSupport\App\Services\Helper;

/**
 * Class AIActivityLogger
 *
 * This class handles logging AI activity hooks for FluentSupport.
 */
class AIActivityLogger
{
    /**
     * Registers all action hooks related to AI activities.
     */
    public function init()
    {
        add_action('fluent_support/gpt_3.5_activity', function ($ticketID, $prompt, $usedTokens) {
            $settings = Helper::getOption('_ai_activity_settings', []);
            if (isset($settings['disable_logs']) && $settings['disable_logs'] === 'yes') {
                return;
            }
            $logData = [
                'agent_id' => get_current_user_id(),
                'ticket_id' => $ticketID,
                'model_name' => 'gpt-3.5-turbo-0125',
                'tokens' => intval($usedTokens),
                'prompt' => sanitize_text_field($prompt),
            ];

            AIActivityLogs::create($logData);
        }, 20, 3);
    }

}
