<?php
/**
 * fluent_support/agent_feedback_received fires when a customer rates an
 * agent's reply or changes the rating, and not when they remove it by
 * clicking the same rating again. Each rating fires at most once per reply.
 */

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Modules\CustomerPortal\PortalService;

return function () {

    FsTest::case('feedback hook fires on a new or changed rating, not on removal', function () {
        $agent = FsFactory::agent();
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id]);
        $reply = FsFactory::conversation($ticket, ['person_id' => $agent->id, 'conversation_type' => 'response']);

        $fired = [];
        $listener = function ($rating, $conversation, $hookTicket, $hookCustomer) use (&$fired) {
            $fired[] = [$rating, (int) $conversation->id, (int) $hookTicket->id, (int) $hookCustomer->id];
        };
        add_action('fluent_support/agent_feedback_received', $listener, 10, 4);

        try {
            PortalService::recordRating('dislike', $reply->id, $ticket->id);
            PortalService::recordRating('like', $reply->id, $ticket->id);
            PortalService::recordRating('like', $reply->id, $ticket->id);
            // Clicking back and forth: each rating already fired once.
            PortalService::recordRating('like', $reply->id, $ticket->id);
            PortalService::recordRating('dislike', $reply->id, $ticket->id);
            PortalService::recordRating('like', $reply->id, $ticket->id);
            $ratingAfterClicks = Meta::where('object_type', 'conversation_meta')->where('object_id', $reply->id)->where('key', 'agent_feedback_ratings')->value('value');
        } finally {
            remove_action('fluent_support/agent_feedback_received', $listener, 10);
            Meta::where('object_type', 'conversation_meta')->where('object_id', $reply->id)->delete();
        }

        $expected = [
            ['dislike', (int) $reply->id, (int) $ticket->id, (int) $customer->id],
            ['like', (int) $reply->id, (int) $ticket->id, (int) $customer->id],
        ];
        FsTest::assertSame($expected, $fired, 'new rating, then change; the removal fires nothing');
        FsTest::assertSame('like', $ratingAfterClicks, 'the rating itself still follows the clicks');
    });
};
