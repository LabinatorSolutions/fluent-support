<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\AIActivityLogs;

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
            $logData = [
                'agent_id' => get_current_user_id(),
                'ticket_id' => $ticketID,
                'model_name' => 'gpt-3.5-turbo-0125',
                'used_tokens' => intval($usedTokens),
                'prompt' => sanitize_text_field($prompt),
            ];

            AIActivityLogs::create($logData);
        }, 20, 3);
    }

}
