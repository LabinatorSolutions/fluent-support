<?php
/**
 * System activity rows can't be edited.
 *
 * `internal_info` rows record what happened ("Ticket has been closed"), so
 * `PUT tickets/{id}/responses/{id}` refuses them. Older MCP versions saved real
 * notes with that type and source 'mcp'; those stay editable, matching
 * ResponseTools::scopeEditable().
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Services\Helper;

return function () {

    $me = Helper::getAgentByUserId();
    if (!$me) {
        FsTest::case('activity edit: admin has an agent row', function () {
            FsTest::skip('the admin user has no agent row');
        });
        return;
    }

    $ticket = FsFactory::ticket(['agent_id' => $me->id]);

    $edit = function ($conversation, $content) use ($ticket) {
        return FsTest::rest('PUT', '/tickets/' . $ticket->id . '/responses/' . $conversation->id, [
            'content' => $content,
        ]);
    };

    FsTest::case('activity edit: a system activity row is refused and keeps its text', function () use ($ticket, $me, $edit) {
        $activity = FsFactory::conversation($ticket, [
            'person_id'         => $me->id,
            'conversation_type' => 'internal_info',
            'content'           => 'Ticket has been closed ' . FsFactory::marker(),
            'source'            => 'web',
        ]);

        $result = $edit($activity, '<p>Rewritten history ' . FsFactory::marker() . '</p>');

        FsTest::assert($result['status'] >= 400, 'edit is rejected, got HTTP ' . $result['status']);
        FsTest::assertSame('Ticket has been closed ' . FsFactory::marker(), Conversation::find($activity->id)->content, 'content unchanged');
    });

    FsTest::case('activity edit: a legacy MCP note saved as internal_info stays editable', function () use ($ticket, $me, $edit) {
        $legacyNote = FsFactory::conversation($ticket, [
            'person_id'         => $me->id,
            'conversation_type' => 'internal_info',
            'content'           => '<p>Old MCP note ' . FsFactory::marker() . '</p>',
            'source'            => 'mcp',
        ]);

        $updated = '<p>Updated MCP note ' . FsFactory::marker() . '</p>';
        $result = $edit($legacyNote, $updated);

        FsTest::assertHealthy($result, 'legacy MCP note edit');
        FsTest::assertSame($updated, Conversation::find($legacyNote->id)->content, 'content updated');
    });

    FsTest::case('activity edit: a note is still editable', function () use ($ticket, $me, $edit) {
        $note = FsFactory::conversation($ticket, [
            'person_id'         => $me->id,
            'conversation_type' => 'note',
            'content'           => '<p>Note ' . FsFactory::marker() . '</p>',
        ]);

        $updated = '<p>Edited note ' . FsFactory::marker() . '</p>';
        $result = $edit($note, $updated);

        FsTest::assertHealthy($result, 'note edit');
        FsTest::assertSame($updated, Conversation::find($note->id)->content, 'content updated');
    });
};
