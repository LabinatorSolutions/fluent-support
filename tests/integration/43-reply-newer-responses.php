<?php
/**
 * An agent reply sent from the admin carries the newest conversation id the
 * agent had loaded. If the customer or another agent replied after that, the
 * server holds the reply (409 fs_newer_responses) until the agent sends again
 * with force_send. Callers that send no id (MCP, API, bulk reply) are never held.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Services\Helper;

return function () {

    $agent = Helper::getAgentByUserId();

    $reply = function ($ticket, array $params) {
        return FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', array_merge([
            'content'           => '<p>Agent reply ' . FsFactory::marker() . '</p>',
            'conversation_type' => 'response',
        ], $params));
    };

    $agentReplies = function ($ticket) use ($agent) {
        return Conversation::where('ticket_id', $ticket->id)
            ->where('conversation_type', 'response')
            ->where('person_id', $agent->id)
            ->count();
    };

    // A thread the agent loaded (up to $seen), then a reply by someone else.
    $threadWithNewerReply = function ($fromPersonId = null) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'status' => 'active']);
        $seen = FsFactory::conversation($ticket, ['person_id' => $customer->id]);
        $newer = FsFactory::conversation($ticket, ['person_id' => $fromPersonId ?: $customer->id]);
        return [$ticket, $seen, $newer];
    };

    FsTest::case('reply is held when the customer replied after the last loaded conversation', function () use ($reply, $agentReplies, $threadWithNewerReply) {
        [$ticket, $seen, $newer] = $threadWithNewerReply();

        $result = $reply($ticket, ['last_conversation_id' => $seen->id]);

        FsTest::assertSame(409, $result['status'], 'status');
        FsTest::assertSame('fs_newer_responses', $result['data']['code'] ?? null, 'error code');
        FsTest::assertSame([(int) $newer->id], array_column($result['data']['new_responses'] ?? [], 'id'), 'the newer reply is listed');
        FsTest::assertSame(0, $agentReplies($ticket), 'nothing was sent');
    });

    FsTest::case('reply is held when another agent replied after the last loaded conversation', function () use ($reply, $agentReplies, $threadWithNewerReply) {
        $otherAgent = FsFactory::agent();
        [$ticket, $seen] = $threadWithNewerReply($otherAgent->id);

        $result = $reply($ticket, ['last_conversation_id' => $seen->id]);

        FsTest::assertSame(409, $result['status'], 'status');
        FsTest::assertSame(0, $agentReplies($ticket), 'nothing was sent');
    });

    FsTest::case('force_send sends the reply despite newer replies', function () use ($reply, $agentReplies, $threadWithNewerReply) {
        [$ticket, $seen] = $threadWithNewerReply();

        $result = $reply($ticket, ['last_conversation_id' => $seen->id, 'force_send' => 'yes']);

        FsTest::assertHealthy($result, 'forced reply');
        FsTest::assertSame(1, $agentReplies($ticket), 'reply sent');
    });

    FsTest::case('reply goes through when nothing newer arrived', function () use ($reply, $agentReplies, $threadWithNewerReply) {
        [$ticket, , $newer] = $threadWithNewerReply();

        $result = $reply($ticket, ['last_conversation_id' => $newer->id]);

        FsTest::assertHealthy($result, 'up-to-date reply');
        FsTest::assertSame(1, $agentReplies($ticket), 'reply sent');
    });

    FsTest::case('the agent\'s own newer reply does not hold the next one', function () use ($reply, $agentReplies, $agent) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'status' => 'active']);
        $seen = FsFactory::conversation($ticket, ['person_id' => $customer->id]);
        FsFactory::conversation($ticket, ['person_id' => $agent->id]);

        $result = $reply($ticket, ['last_conversation_id' => $seen->id]);

        FsTest::assertHealthy($result, 'reply after own reply');
        FsTest::assertSame(2, $agentReplies($ticket), 'reply sent');
    });

    FsTest::case('callers that send no last_conversation_id and internal notes are never held', function () use ($reply, $threadWithNewerReply) {
        [$ticket, $seen] = $threadWithNewerReply();

        FsTest::assertHealthy($reply($ticket, []), 'reply without last_conversation_id');
        FsTest::assertHealthy($reply($ticket, ['conversation_type' => 'note', 'last_conversation_id' => $seen->id]), 'note');
    });

    FsTest::case('first reply is held when the customer wrote again on a ticket the agent loaded with no replies', function () use ($reply, $agentReplies) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'status' => 'new']);
        // The agent loaded the ticket with no conversations, so the reply box sends 0.
        FsFactory::conversation($ticket, ['person_id' => $customer->id]);

        $result = $reply($ticket, ['last_conversation_id' => 0]);
        FsTest::assertSame(409, $result['status'], 'held');
        FsTest::assertSame(0, $agentReplies($ticket), 'not sent');
    });
};
