<?php
/**
 * MCP reply and thread behaviour: markdown line breaks, reply-to-closed reopens,
 * draft replies, thread Markdown/since/truncation, integration widget data and
 * attachment URL signing.
 *
 * The draft-only WP user isn't factory-tracked; its finally removes it.
 */

use FluentSupport\App\Hooks\Handlers\ExternalPages;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\ManagementTools;
use FluentSupport\App\Modules\MCP\Tools\ResponseTools;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    FsTest::interceptMail();

    $reply = function (array $params) {
        $result = ResponseTools::replyToTicket($params);
        if (is_wp_error($result)) {
            FsTest::fail('reply-to-ticket returned an error: ' . $result->get_error_message());
            return null;
        }
        return json_decode(wp_json_encode($result), true);
    };

    $getTicket = function (array $params) {
        $result = TicketTools::getTicket($params);
        if (is_wp_error($result)) {
            FsTest::fail('get-ticket returned an error: ' . $result->get_error_message());
            return null;
        }
        return json_decode(wp_json_encode($result['data']), true);
    };

    FsTest::case('mcp_markdown_single_newline_is_a_line_break', function () use ($reply) {
        $ticket = FsFactory::ticket();

        $result = $reply(['ticket_id' => $ticket->id, 'content' => "Thanks,\nJewel\n\nNext paragraph"]);
        if (!$result) {
            return;
        }

        $stored = (string) Conversation::find($result['data']['response']['id'])->content;

        FsTest::assert(preg_match('#Thanks,<br\s*/?>\s*Jewel#', $stored) === 1,
            'single newline must become <br />, got: ' . $stored);
        FsTest::assert(strpos($stored, '<p>Next paragraph') !== false,
            'blank line must still start a new paragraph, got: ' . $stored);
    });

    FsTest::case('mcp_reply_to_closed_ticket_reopens_it', function () use ($reply) {
        $ticket = FsFactory::ticket(['status' => 'closed', 'resolved_at' => current_time('mysql')]);

        $reopenHooks = 0;
        $counter = function () use (&$reopenHooks) {
            $reopenHooks++;
        };
        add_action('fluent_support/ticket_reopen', $counter);

        try {
            $result = $reply(['ticket_id' => $ticket->id, 'content' => 'Follow-up answer']);
        } finally {
            remove_action('fluent_support/ticket_reopen', $counter);
        }

        if (!$result) {
            return;
        }

        FsTest::assertSame(true, $result['data']['reopened'], 'payload reopened');
        FsTest::assertSame('active', $result['data']['ticket_status'], 'payload ticket_status');
        FsTest::assertSame('active', Ticket::find($ticket->id)->status, 'stored status');
        FsTest::assertSame(1, $reopenHooks, 'ticket_reopen fired once after commit');
    });

    FsTest::case('mcp_reply_with_close_on_closed_ticket_keeps_it_closed', function () use ($reply) {
        $ticket = FsFactory::ticket(['status' => 'closed', 'resolved_at' => current_time('mysql')]);

        $result = $reply(['ticket_id' => $ticket->id, 'content' => 'One more thing', 'close_ticket' => true]);
        if (!$result) {
            return;
        }

        FsTest::assertSame(false, $result['data']['reopened'], 'payload reopened');
        FsTest::assertSame('closed', Ticket::find($ticket->id)->status, 'stored status');
        FsTest::assertSame(1, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->count(), 'reply stored');
    });

    FsTest::case('mcp_draft_reply_is_saved_unsent_and_changes_nothing', function () use ($reply) {
        $ticket = FsFactory::ticket(['status' => 'closed', 'agent_id' => null]);
        FsTest::interceptMail();

        $result = $reply(['ticket_id' => $ticket->id, 'content' => 'Proposed answer', 'draft' => true]);
        if (!$result) {
            return;
        }

        $fresh = Ticket::find($ticket->id);
        FsTest::assertSame('draft_response', Conversation::find($result['data']['response']['id'])->conversation_type, 'stored type');
        FsTest::assertSame('closed', $fresh->status, 'status unchanged');
        FsTest::assert(empty($fresh->agent_id), 'draft must not take ownership');
        FsTest::assertSame(0, (int) $fresh->response_count, 'response_count unchanged');
        FsTest::assertSame([], FsTest::sentMails(), 'no mail sent for a draft');
    });

    FsTest::case('mcp_draft_reply_rejects_close_and_assign', function () {
        $ticket = FsFactory::ticket();

        $result = ResponseTools::replyToTicket(['ticket_id' => $ticket->id, 'content' => 'x', 'draft' => true, 'close_ticket' => true]);

        FsTest::assert(is_wp_error($result) && $result->get_error_code() === 'invalid_param',
            'draft + close_ticket must be invalid_param');
        FsTest::assertSame(0, Conversation::where('ticket_id', $ticket->id)->count(), 'nothing stored');
    });

    FsTest::case('mcp_draft_only_agent_always_saves_a_draft', function () use ($reply) {
        $adminId = get_current_user_id();
        $login = FsTest::uniq('fs-mcp-drafter');
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            FsTest::fail('Could not create drafter user: ' . $userId->get_error_message());
            return;
        }

        $switchTo = function ($id) {
            wp_set_current_user($id);
            PermissionManager::currentUserPermissions(false);
            AgentTicketAccess::resetCache();
        };

        try {
            $drafter = FsFactory::agent();
            $drafter->user_id = $userId;
            $drafter->save();
            PermissionManager::attachPermissions($userId, ['fst_draft_reply']);

            $ticket = FsFactory::ticket();
            $switchTo($userId);

            $result = $reply(['ticket_id' => $ticket->id, 'content' => 'Drafted by a draft-only agent']);
            if (!$result) {
                return;
            }

            FsTest::assertSame(true, $result['data']['draft_forced'], 'payload draft_forced');
            FsTest::assertSame('draft_response', Conversation::find($result['data']['response']['id'])->conversation_type, 'stored type');
            FsTest::assert(strpos($result['summary'], 'only have the Draft Reply permission') !== false, 'summary says why it is a draft, got: ' . $result['summary']);

            // The context call tells a draft-only agent up front.
            ManagementTools::invalidateSupportContextCache();
            $you = json_decode(wp_json_encode(ManagementTools::getSupportContext([])['data']['you']), true);
            FsTest::assertSame('draft_only', $you['replies'] ?? null, 'context replies mode');
            FsTest::assert(strpos($you['replies_note'] ?? '', 'Draft Reply permission') !== false, 'context explains draft-only');
        } finally {
            $switchTo($adminId);
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($userId);
        }
    });

    FsTest::case('mcp_retried_reply_is_not_sent_twice', function () use ($reply) {
        $ticket = FsFactory::ticket();
        FsTest::interceptMail();

        $first = $reply(['ticket_id' => $ticket->id, 'content' => 'Your domain is verified now.']);
        $retry = $reply(['ticket_id' => $ticket->id, 'content' => 'Your domain is verified now.']);
        $other = $reply(['ticket_id' => $ticket->id, 'content' => 'One more note.']);

        if (!$first || !$retry || !$other) {
            return;
        }

        FsTest::assertSame(true, $retry['data']['duplicate'] ?? null, 'retry flagged duplicate');
        FsTest::assertSame($first['data']['response']['id'], $retry['data']['response']['id'], 'retry returns the first reply');
        FsTest::assert(empty($other['data']['duplicate']), 'different content is a new reply');
        FsTest::assertSame(2, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->count(), 'two replies stored, not three');
        FsTest::assertSame(2, count(FsTest::sentMails()), 'the customer gets two emails, not three');
    });

    FsTest::case('mcp_reply_side_effects_fire_only_after_the_reply_and_its_request_hash_are_committed', function () use ($reply) {
        // If the email left before the hash row existed, a failure between the
        // two would roll the reply back and a retry would email the customer again.
        $ticket = FsFactory::ticket();
        FsTest::interceptMail();

        $seen = [];
        $onReply = function ($response) use (&$seen) {
            $seen['hash_when_emailed'] = Conversation::find($response->id)->getSettingsValue('mcp_request_hash');
        };
        $onClose = function () use (&$seen) {
            $seen['closed'] = ($seen['closed'] ?? 0) + 1;
        };
        add_action('fluent_support/response_added_by_agent', $onReply, 5);
        add_action('fluent_support/ticket_closed', $onClose, 5);

        try {
            $result = $reply(['ticket_id' => $ticket->id, 'content' => 'All set, closing this.', 'close_ticket' => true]);
        } finally {
            remove_action('fluent_support/response_added_by_agent', $onReply, 5);
            remove_action('fluent_support/ticket_closed', $onClose, 5);
        }
        if (!$result) {
            return;
        }

        FsTest::assert(!empty($seen['hash_when_emailed']), 'the request hash must already be stored when the reply hook (customer email) fires');
        FsTest::assertSame(1, $seen['closed'] ?? 0, 'ticket_closed fires once');
        FsTest::assertSame('closed', Ticket::find($ticket->id)->status, 'ticket closed');
        FsTest::assertSame(1, count(FsTest::sentMails()), 'one customer email');
    });

    FsTest::case('mcp_bulk_reply_replies_to_each_ticket', function () {
        $customer = FsFactory::customer();
        $open = FsFactory::ticket(['customer_id' => $customer->id]);
        $closed = FsFactory::ticket(['customer_id' => $customer->id, 'status' => 'closed']);
        FsTest::interceptMail();

        $result = ManagementTools::bulkAction([
            'action'     => 'reply',
            'ticket_ids' => [$open->id, $closed->id, 999999999],
            'content'    => 'Hi {{customer.first_name}}, we shipped a fix.',
        ]);

        if (is_wp_error($result)) {
            FsTest::fail('bulk reply returned an error: ' . $result->get_error_message());
            return;
        }

        $byId = array_column($result['data']['results'], null, 'id');
        FsTest::assertSame('replied', $byId[$open->id]['status'] ?? null, 'open ticket');
        FsTest::assertSame('replied', $byId[$closed->id]['status'] ?? null, 'closed ticket');
        FsTest::assertSame(true, $byId[$closed->id]['reopened'] ?? null, 'closed ticket reopened');
        FsTest::assertSame('not_found', $byId[999999999]['status'] ?? null, 'missing ticket');
        FsTest::assertSame(2, $result['meta']['processed'] ?? null, 'processed count');

        $stored = (string) Conversation::where('ticket_id', $open->id)->where('conversation_type', 'response')->value('content');
        FsTest::assert(strpos($stored, 'Hi ' . $customer->first_name) !== false,
            'placeholder must be filled per customer, got: ' . $stored);
    });

    FsTest::case('mcp_context_says_what_the_reply_email_adds', function () {
        $agent = \FluentSupport\App\Modules\MCP\Helpers\MCPHelper::resolveAgent();
        $emailBox = FsFactory::mailbox(['box_type' => 'email']);
        $webBox = FsFactory::mailbox(['box_type' => 'web']);
        $previous = [$agent->getMeta('agent_signature_enabled', ''), $agent->getMeta('agent_signature', '')];

        $context = function () use ($agent) {
            \FluentSupport\App\Modules\MCP\Tools\ManagementTools::invalidateSupportContextCache();
            return json_decode(wp_json_encode(\FluentSupport\App\Modules\MCP\Tools\ManagementTools::getSupportContext(['include' => ['mailboxes']])['data']), true);
        };
        $box = function ($data, $id) {
            foreach ($data['mailboxes'] as $mailbox) {
                if ((int) $mailbox['id'] === (int) $id) {
                    return $mailbox;
                }
            }
            return null;
        };

        try {
            $agent->updateMeta('agent_signature_enabled', 'yes');
            $agent->updateMeta('agent_signature', '<p>Jewel, Support Lead</p>');
            $data = $context();

            FsTest::assertSame(true, $data['you']['signature']['enabled'], 'signature enabled');
            FsTest::assert(strpos($data['you']['signature']['text'], 'Support Lead') !== false, 'signature text');

            $emailReply = $box($data, $emailBox->id)['emails']['reply'] ?? [];
            FsTest::assertSame(true, $emailReply['includes_reply'] ?? null, 'email inbox: reply text is in the email');
            FsTest::assertSame(true, $emailReply['adds_signature'] ?? null, 'email inbox: default template adds {{agent.signature}}');
            FsTest::assert(strpos($emailReply['template'] ?? '', '[YOUR SIGNATURE]') !== false, 'email inbox: template marks where the signature goes');
            FsTest::assert(!array_key_exists('note', $emailReply), 'context mailboxes carry no repeated note');

            $webReply = $box($data, $webBox->id)['emails']['reply'] ?? [];
            FsTest::assertSame(false, $webReply['includes_reply'] ?? null, 'web inbox: email only links to the portal');
            FsTest::assert(stripos($webReply['note'] ?? '', 'portal') !== false, 'note says the reply is read in the portal');

            // get-ticket carries the same facts for the ticket's own inbox.
            $ticket = FsFactory::ticket(['mailbox_id' => $emailBox->id]);
            $ticketData = json_decode(wp_json_encode(TicketTools::getTicket(['ticket_id' => $ticket->id, 'with_integrations' => false, 'with_responses' => false])['data']), true);
            FsTest::assert(strpos($ticketData['reply_email']['template'] ?? '', '[YOUR MESSAGE]') !== false, 'get-ticket reply_email shows the template');
            FsTest::assertSame(true, $ticketData['reply_email']['adds_signature'] ?? null, 'get-ticket signature');

            $agent->updateMeta('agent_signature_enabled', 'no');
            $off = $context();
            FsTest::assertSame(false, $off['you']['signature']['enabled'], 'signature off');
            FsTest::assert(strpos($off['you']['signature']['note'] ?? '', 'no signature') !== false, 'says there is no signature');
            FsTest::assertSame(false, $box($off, $emailBox->id)['emails']['reply']['adds_signature'] ?? null, 'no signature, so none added');
        } finally {
            $agent->updateMeta('agent_signature_enabled', $previous[0]);
            $agent->updateMeta('agent_signature', $previous[1]);
            \FluentSupport\App\Modules\MCP\Tools\ManagementTools::invalidateSupportContextCache();
        }
    });

    FsTest::case('mcp_drafts_and_notes_are_gated_to_their_author', function () {
        $adminId = get_current_user_id();
        $users = [];

        // A WP user with these permissions and a linked agent.
        $makeAgent = function (array $permissions) use (&$users) {
            $login = FsTest::uniq('fs-mcp-row');
            $userId = wp_insert_user([
                'user_login' => $login,
                'user_pass'  => wp_generate_password(24, true, true),
                'user_email' => $login . '@example.test',
                'role'       => 'subscriber',
            ]);
            $users[] = $userId;
            $agent = FsFactory::agent();
            $agent->user_id = $userId;
            $agent->save();
            PermissionManager::attachPermissions($userId, $permissions);
            return $agent;
        };
        $as = function ($agent) {
            wp_set_current_user($agent ? $agent->user_id : get_current_user_id());
            PermissionManager::currentUserPermissions(false);
            AgentTicketAccess::resetCache();
        };
        $code = function ($result) {
            return is_wp_error($result) ? json_decode($result->get_error_message(), true)['error']['code'] : 'ok';
        };

        try {
            $author = $makeAgent(['fst_manage_other_tickets', 'fst_delete_tickets']);
            $noDelete = $makeAgent(['fst_manage_other_tickets']);
            $other = $makeAgent(['fst_manage_other_tickets']);
            $approver = $makeAgent(['fst_manage_other_tickets', 'fst_approve_draft_reply']);
            $drafter = $makeAgent(['fst_draft_reply']);
            $ticket = FsFactory::ticket();
            FsTest::interceptMail();

            $draft = FsFactory::conversation($ticket, ['person_id' => $author->id, 'conversation_type' => 'draft_response', 'content' => '<p>Draft ' . FsFactory::marker() . '</p>']);
            $note = FsFactory::conversation($ticket, ['person_id' => $author->id, 'conversation_type' => 'note', 'content' => '<p>Note ' . FsFactory::marker() . '</p>']);
            $mcpNote = FsFactory::conversation($ticket, ['person_id' => $author->id, 'conversation_type' => 'internal_info', 'source' => 'mcp', 'content' => '<p>MCP note ' . FsFactory::marker() . '</p>']);
            $system = FsFactory::conversation($ticket, ['person_id' => $author->id, 'conversation_type' => 'internal_info', 'source' => 'web', 'content' => '<p>Ticket has been closed ' . FsFactory::marker() . '</p>']);

            // Someone else's rows are off limits.
            $as($other);
            FsTest::assertSame('forbidden', $code(ResponseTools::updateResponse(['ticket_id' => $ticket->id, 'response_id' => $draft->id, 'content' => 'Hijack'])), 'other agent cannot update');
            FsTest::assertSame('forbidden', $code(ResponseTools::deleteResponse(['ticket_id' => $ticket->id, 'response_id' => $draft->id])), 'other agent cannot delete the draft');
            FsTest::assertSame('forbidden', $code(ResponseTools::deleteResponse(['ticket_id' => $ticket->id, 'response_id' => $note->id])), 'other agent cannot delete the note');
            FsTest::assertSame('forbidden', $code(ResponseTools::publishDraftReply(['ticket_id' => $ticket->id, 'response_id' => $draft->id])), 'other manager cannot publish');

            // The author can.
            $as($author);
            FsTest::assertSame('ok', $code(ResponseTools::updateResponse(['ticket_id' => $ticket->id, 'response_id' => $draft->id, 'content' => 'Better answer ' . FsFactory::marker()])), 'author updates');
            FsTest::assert(strpos((string) Conversation::find($draft->id)->content, 'Better answer') !== false, 'content changed');
            FsTest::assertSame('ok', $code(ResponseTools::deleteResponse(['ticket_id' => $ticket->id, 'response_id' => $note->id])), 'author deletes a note');
            FsTest::assertSame('ok', $code(ResponseTools::deleteResponse(['ticket_id' => $ticket->id, 'response_id' => $mcpNote->id])), 'author deletes an MCP note');
            FsTest::assertSame('not_found', $code(ResponseTools::deleteResponse(['ticket_id' => $ticket->id, 'response_id' => $system->id])), 'system lines are not notes');

            // Draft-only agents never publish, not even their own.
            $own = FsFactory::conversation($ticket, ['person_id' => $drafter->id, 'conversation_type' => 'draft_response', 'content' => '<p>Mine ' . FsFactory::marker() . '</p>']);
            $as($drafter);
            FsTest::assertSame('ok', $code(ResponseTools::updateResponse(['ticket_id' => $ticket->id, 'response_id' => $own->id, 'content' => 'Mine, edited ' . FsFactory::marker()])), 'draft-only agent edits own draft');
            FsTest::assertSame('forbidden', $code(ResponseTools::publishDraftReply(['ticket_id' => $ticket->id, 'response_id' => $own->id])), 'draft-only agent cannot publish');

            // An approver publishes anyone's draft; it becomes a sent reply.
            $as($approver);
            $before = (int) Ticket::find($ticket->id)->response_count;
            FsTest::assertSame('ok', $code(ResponseTools::publishDraftReply(['ticket_id' => $ticket->id, 'response_id' => $own->id])), 'approver publishes');
            // Publishing gives the reply a new id, so it sorts after everything written before it
            FsTest::assertSame(null, Conversation::find($own->id), 'the draft row is gone');
            $own = Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->latest('id')->first();
            FsTest::assert(strpos((string) $own->content, 'Mine, edited') !== false, 'now a response');
            FsTest::assertSame($before + 1, (int) Ticket::find($ticket->id)->response_count, 'counted as a reply');

            // A sent reply is neither editable nor deletable here.
            FsTest::assertSame('not_editable', $code(ResponseTools::updateResponse(['ticket_id' => $ticket->id, 'response_id' => $own->id, 'content' => 'x ' . FsFactory::marker()])), 'sent reply: not editable');
            FsTest::assertSame('not_editable', $code(ResponseTools::deleteResponse(['ticket_id' => $ticket->id, 'response_id' => $own->id])), 'sent reply: not deletable');

            // A note is editable by its author through the same tool.
            $as($author);
            $editableNote = FsFactory::conversation($ticket, ['person_id' => $author->id, 'conversation_type' => 'note', 'content' => '<p>Old note ' . FsFactory::marker() . '</p>']);
            FsTest::assertSame('ok', $code(ResponseTools::updateResponse(['ticket_id' => $ticket->id, 'response_id' => $editableNote->id, 'content' => 'New note ' . FsFactory::marker()])), 'author edits a note');

            // Without the delete permission: own notes stay, own drafts can be discarded.
            $as($noDelete);
            $keptNote = FsFactory::conversation($ticket, ['person_id' => $noDelete->id, 'conversation_type' => 'note', 'content' => '<p>Kept ' . FsFactory::marker() . '</p>']);
            $discard = FsFactory::conversation($ticket, ['person_id' => $noDelete->id, 'conversation_type' => 'draft_response', 'content' => '<p>Discard ' . FsFactory::marker() . '</p>']);
            FsTest::assertSame('forbidden', $code(ResponseTools::deleteResponse(['ticket_id' => $ticket->id, 'response_id' => $keptNote->id])), 'note delete needs the delete permission');
            FsTest::assertSame('ok', $code(ResponseTools::deleteResponse(['ticket_id' => $ticket->id, 'response_id' => $discard->id])), 'own draft can be discarded without it');

            // Edited text gets its placeholders filled.
            $as($author);
            $greeting = FsFactory::conversation($ticket, ['person_id' => $author->id, 'conversation_type' => 'draft_response', 'content' => '<p>Greeting ' . FsFactory::marker() . '</p>']);
            ResponseTools::updateResponse(['ticket_id' => $ticket->id, 'response_id' => $greeting->id, 'content' => 'Hi {{customer.first_name}} ' . FsFactory::marker()]);
            $stored = (string) Conversation::find($greeting->id)->content;
            FsTest::assert(strpos($stored, '{{') === false && strpos($stored, (string) Ticket::find($ticket->id)->customer->first_name) !== false, 'smartcodes filled on update, got: ' . $stored);

            // The author publishes and deletes their own.
            $as($author);
            FsTest::assertSame('ok', $code(ResponseTools::publishDraftReply(['ticket_id' => $ticket->id, 'response_id' => $draft->id])), 'author with manage publishes own');
            $second = FsFactory::conversation($ticket, ['person_id' => $author->id, 'conversation_type' => 'draft_response', 'content' => '<p>Second ' . FsFactory::marker() . '</p>']);
            FsTest::assertSame('ok', $code(ResponseTools::deleteResponse(['ticket_id' => $ticket->id, 'response_id' => $second->id])), 'author deletes own draft');
            FsTest::assert(!Conversation::find($second->id), 'draft gone');
        } finally {
            wp_set_current_user($adminId);
            PermissionManager::currentUserPermissions(false);
            AgentTicketAccess::resetCache();
            require_once ABSPATH . 'wp-admin/includes/user.php';
            foreach ($users as $userId) {
                wp_delete_user($userId);
            }
        }
    });

    FsTest::case('agent_signature_naming_itself_renders_once', function () {
        $agent = FsFactory::agent();
        $agent->updateMeta('agent_signature_enabled', 'yes');
        $agent->updateMeta('agent_signature', '<p>Sig ##agent.signature## {{agent.signature|}} {{agent.signature}}</p>');

        $out = apply_filters('fluent_support/parse_smartcode_data', '{{agent.signature}}', ['agent' => $agent]);

        FsTest::assertSame(1, substr_count($out, 'Sig'), 'renders once, no recursion: ' . $out);
    });

    FsTest::case('mcp_thread_keeps_structure_and_supports_since_and_truncation', function () use ($getTicket) {
        // Fixture rows must carry the marker or cleanup refuses to delete them.
        $marker = FsFactory::marker();
        $ticket = FsFactory::ticket();
        $first = FsFactory::conversation($ticket, ['content' => '<p>Error ' . $marker . ':</p><pre>line one' . "\n" . '  line two</pre><ul><li>a</li><li>b</li></ul>']);
        $long = $marker . ' ' . str_repeat('x', 300);
        $second = FsFactory::conversation($ticket, ['content' => '<p>' . $long . '</p>']);

        $all = $getTicket(['ticket_id' => $ticket->id, 'with_integrations' => false]);
        if (!$all) {
            return;
        }

        $content = $all['responses'][0]['content'];
        FsTest::assert(strpos($content, "```\nline one\n  line two\n```") !== false,
            'pre block must keep its lines and indent, got: ' . $content);
        FsTest::assert(strpos($content, "- a\n- b") !== false, 'list must stay a list, got: ' . $content);
        FsTest::assertSame((int) $second->id, (int) $all['responses_meta']['latest_response_id'], 'latest_response_id');

        $since = $getTicket(['ticket_id' => $ticket->id, 'with_integrations' => false, 'since_response_id' => $first->id, 'max_chars_per_response' => 50]);
        if (!$since) {
            return;
        }

        FsTest::assertSame([(int) $second->id], array_map('intval', array_column($since['responses'], 'id')), 'since_response_id ids');
        FsTest::assertSame(true, $since['responses'][0]['truncated'] ?? null, 'truncated flag');
        FsTest::assertSame(mb_strlen($long), $since['responses'][0]['full_length'] ?? null, 'full_length');

        $one = $getTicket(['ticket_id' => $ticket->id, 'with_integrations' => false, 'response_id' => $second->id]);
        FsTest::assertSame($long, $one['responses'][0]['content'] ?? null, 'response_id returns the full body');
    });

    FsTest::case('mcp_thread_keeps_content_after_an_unbalanced_closing_tag', function () use ($getTicket) {
        // kses keeps a stray </div> for any allowed element and nothing balances tags
        // before storage, so piped email HTML can carry one. The converter must not
        // let it end the document early.
        $marker = FsFactory::marker();
        $ticket = FsFactory::ticket();
        FsFactory::conversation($ticket, ['content' => '<p>first ' . $marker . '</p></div><p>second ' . $marker . '</p>']);

        $all = $getTicket(['ticket_id' => $ticket->id, 'with_integrations' => false]);
        if (!$all) {
            return;
        }

        $content = $all['responses'][0]['content'];
        FsTest::assert(strpos($content, 'first ' . $marker) !== false, 'text before the stray </div> must be present, got: ' . $content);
        FsTest::assert(strpos($content, 'second ' . $marker) !== false, 'text after the stray </div> must be present, got: ' . $content);
    });

    FsTest::case('mcp_thread_div_inside_cell_item_link_and_heading_stays_inline', function () use ($getTicket) {
        // Email templates wrap cell, button and heading text in <div>s. The soft
        // break a <div> emits must fold into the inline context, not split it.
        $marker = FsFactory::marker();
        $ticket = FsFactory::ticket();
        FsFactory::conversation($ticket, ['content' =>
            '<p>' . $marker . '</p>'
            . '<table><tr><th><div>Item</div></th><th><div>Qty</div></th></tr><tr><td>Widget</td><td>2</td></tr></table>'
            . '<ul><li><div>one</div><div>two</div></li><li>three</li></ul>'
            . '<h3><div>Heading</div></h3>'
            . '<p><a href="https://example.com/x"><div>Open ticket</div></a></p>'
        ]);

        $all = $getTicket(['ticket_id' => $ticket->id, 'with_integrations' => false]);
        if (!$all) {
            return;
        }

        $content = $all['responses'][0]['content'];
        FsTest::assert(strpos($content, "| Item | Qty |\n| --- | --- |\n| Widget | 2 |") !== false, 'table row must stay on one line, got: ' . $content);
        FsTest::assert(strpos($content, "- one\n  two\n- three") !== false, 'list item lines must stay under the marker, got: ' . $content);
        FsTest::assert(strpos($content, '### Heading') !== false, 'heading text must stay on the heading line, got: ' . $content);
        FsTest::assert(strpos($content, '[Open ticket](https://example.com/x)') !== false, 'link text must stay inside the brackets, got: ' . $content);
    });

    FsTest::case('mcp_get_ticket_returns_the_whole_conversation_of_a_long_ticket', function () use ($getTicket) {
        $ticket = FsFactory::ticket();
        $ids = [];
        for ($i = 0; $i < 60; $i++) {
            $ids[] = (int) FsFactory::conversation($ticket, ['content' => '<p>Message ' . $i . ' ' . esc_html(FsFactory::marker()) . '</p>'])->id;
        }

        $data = $getTicket(['ticket_id' => $ticket->id, 'with_integrations' => false]);
        if (!$data) {
            return;
        }

        FsTest::assertSame($ids, array_map('intval', array_column($data['responses'], 'id')), 'all 60 messages, oldest first');
        FsTest::assertSame(false, $data['responses_meta']['has_more'], 'nothing is left for another page');
    });

    FsTest::case('mcp_widgets_use_mcp_data_markdown_and_key_scope', function () use ($getTicket) {
        $ticket = FsFactory::ticket();

        $contexts = [];
        $widgets = function ($widgets, $customer, $context = null) use (&$contexts) {
            $contexts[] = $context;
            $widgets['fs_test_structured'] = [
                'title'     => 'Structured',
                'body_html' => '<p>ignored</p>',
                'mcp'       => [
                    'plan'   => '<b>paid</b>',
                    'teams'  => [['id' => 4, 'verified' => true]],
                    'obj'    => new stdClass(),
                    // orders[] => items[] => {..} must fit; one level deeper is dropped.
                    'orders' => [['id' => 1, 'items' => [['name' => 'A', 'qty' => 2, 'deep' => ['x' => 1]]]]],
                ],
            ];
            $widgets['FsTest.Mixed'] = ['title' => 'Mixed', 'mcp' => ['ok' => true]];
            $widgets['fs_test_html'] = [
                'title'     => 'Html',
                'body_html' => '<table><tr><th>Domain</th><th>Status</th></tr><tr><td>acme.com</td><td>verified</td></tr></table>',
            ];
            return $widgets;
        };
        add_filter('fluent_support/customer_extra_widgets', $widgets, 9999, 3);

        try {
            $all = $getTicket(['ticket_id' => $ticket->id, 'with_responses' => false]);
            $scoped = $getTicket(['ticket_id' => $ticket->id, 'with_responses' => false, 'with_integrations' => ['fs_test_html']]);
            $mixed = $getTicket(['ticket_id' => $ticket->id, 'with_responses' => false, 'with_integrations' => ['FsTest.Mixed']]);
        } finally {
            remove_filter('fluent_support/customer_extra_widgets', $widgets, 9999, 3);
        }

        if (!$all || !$scoped) {
            return;
        }

        FsTest::assertSame([
            'plan'   => 'paid',
            'teams'  => [['id' => 4, 'verified' => true]],
            'orders' => [['id' => 1, 'items' => [['name' => 'A', 'qty' => 2]]]],
        ],
            $all['integrations']['fs_test_structured']['data'] ?? null, 'mcp data, sanitized');
        FsTest::assert(strpos($all['integrations']['fs_test_html']['data']['summary'] ?? '', "| acme.com | verified |") !== false,
            'HTML widget must come back as a Markdown table');
        FsTest::assertSame(['fs_test_html'], array_keys($scoped['integrations'] ?? []), 'with_integrations key scope');
        FsTest::assertSame(['FsTest.Mixed'], array_keys($mixed['integrations'] ?? []), 'mixed-case keys match as given');
        // Integrations get the hint so they can skip HTML and unwanted widgets.
        FsTest::assertSame(['format' => 'mcp', 'keys' => null], $contexts[0] ?? null, 'context for a full fetch');
        FsTest::assertSame(['format' => 'mcp', 'keys' => ['fs_test_html']], $contexts[1] ?? null, 'context for a scoped fetch');
    });

    FsTest::case('attachment_signed_url_outlives_the_hour_boundary', function () {
        $ticket = FsFactory::ticket();
        $attachment = Attachment::create([
            'ticket_id' => $ticket->id,
            'file_type' => 'text/plain',
            'title'     => 'fixture.txt',
            'driver'    => 'local',
            'status'    => 'active',
        ]);

        parse_str((string) wp_parse_url($attachment->secureUrl, PHP_URL_QUERY), $query);

        FsTest::assert(isset($query['expires']) && (int) $query['expires'] >= time() + HOUR_IN_SECONDS,
            'signed URL must stay valid for at least one TTL, got expires=' . ($query['expires'] ?? 'none'));

        $validate = new ReflectionMethod(ExternalPages::class, 'validateAttachmentSignature');
        $validate->setAccessible(true);
        $check = function (array $request) use ($validate, $attachment) {
            $_REQUEST = $request;
            return $validate->invoke(new ExternalPages(), $attachment);
        };

        try {
            FsTest::assertSame(true, $check(['secure_sign' => $query['secure_sign'], 'expires' => $query['expires']]), 'fresh URL');

            $past = (string) (time() - 10);
            FsTest::assertSame(false, $check(['secure_sign' => Attachment::signature($attachment->id, $past), 'expires' => $past]), 'expired URL');

            FsTest::assertSame(false, $check(['secure_sign' => $query['secure_sign'], 'expires' => (string) ((int) $query['expires'] + 3600)]), 'tampered expires');

            $legacy = Attachment::legacySignature($attachment->id, gmdate('YmdH'));
            FsTest::assertSame(true, $check(['secure_sign' => $legacy]), 'legacy current-hour URL');
            // An old hourly signature must not pass as an expiring one (2026100614 read as 2034).
            FsTest::assertSame(false, $check(['secure_sign' => $legacy, 'expires' => gmdate('YmdH')]), 'legacy signature replayed with expires');
        } finally {
            $_REQUEST = [];
        }
    });
};
