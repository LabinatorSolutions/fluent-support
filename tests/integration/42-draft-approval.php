<?php
/**
 * Draft replies: a draft-only agent's reply stays hidden until an approver publishes it like a normal reply.
 *
 * The drafter is a real WP user with only `fst_draft_reply`, removed in this file's finally.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;

return function () {

    $marker = FsFactory::marker();
    $adminId = get_current_user_id();

    $mailsTo = function ($email, $offset) {
        $matching = [];
        foreach (array_slice(FsTest::sentMails(), $offset) as $mail) {
            $to = isset($mail['to']) ? (array) $mail['to'] : [];
            if (in_array($email, array_map('strval', $to), true)) {
                $matching[] = $mail;
            }
        }

        return $matching;
    };

    $become = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
    };

    $wpUsers = [];
    $makeWpUser = function ($slug, array $permissions) use ($marker, &$wpUsers) {
        $login = substr($marker . '-' . $slug, 0, 60);
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.invalid',
            'user_pass'  => wp_generate_password(20),
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create WP user: ' . $userId->get_error_message());
        }

        $wpUsers[] = (int) $userId;
        update_user_meta($userId, PermissionManager::META_KEY, $permissions);

        return (int) $userId;
    };

    try {
        $drafterUserId = $makeWpUser('drafter', ['fst_view_tickets', 'fst_draft_reply']);
        $drafter = FsFactory::agent(['user_id' => $drafterUserId, 'email' => $marker . '-drafter@example.invalid']);

        // Saves a draft over REST as the draft-only agent and returns it
        $saveDraft = function (Ticket $ticket) use ($marker, $become, $drafterUserId, $adminId) {
            $become($drafterUserId);
            try {
                $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
                    'content'           => '<p>Draft ' . $marker . '</p>',
                    'conversation_type' => 'draft_response',
                ]);
            } finally {
                $become($adminId);
            }
            FsTest::assertHealthy($result, 'POST /tickets/{id}/responses as a draft-only agent');

            return Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'draft_response')->first();
        };

        FsTest::case('draft_only_agent_saves_a_draft_without_touching_the_ticket', function () use ($saveDraft, $drafter, $mailsTo) {
            $ticket = FsFactory::ticket(['status' => 'new']);
            $customer = Customer::find($ticket->customer_id);
            $before = count(FsTest::sentMails());

            $draft = $saveDraft($ticket);

            if (!$draft) {
                FsTest::fail('the draft-only agent must be able to save a draft');
                return;
            }
            FsTest::assertSame((int) $drafter->id, (int) $draft->person_id, 'the draft belongs to the drafter');

            $fresh = Ticket::find($ticket->id);
            FsTest::assertSame('new', $fresh->status, 'a draft must not change the status');
            FsTest::assertSame(0, (int) $fresh->response_count, 'a draft must not count as a response');
            FsTest::assertSame(0, count($mailsTo($customer->email, $before)), 'a draft must not email the customer');
        });

        FsTest::case('draft_only_agent_cannot_send_a_normal_reply', function () use ($marker, $become, $drafterUserId, $adminId) {
            $ticket = FsFactory::ticket();

            $become($drafterUserId);
            try {
                $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
                    'content'           => '<p>Direct ' . $marker . '</p>',
                    'conversation_type' => 'response',
                ]);
            } finally {
                $become($adminId);
            }

            FsTest::assert($result['status'] >= 400, 'a draft-only agent must not publish directly, got ' . $result['status']);
            FsTest::assertSame(0, Conversation::where('ticket_id', $ticket->id)->count(), 'nothing is stored');
        });

        FsTest::case('draft_is_hidden_from_the_customer_portal', function () use ($saveDraft, $makeWpUser, $become, $adminId, $marker) {
            $customerUserId = $makeWpUser('draft-portal', []);
            $customer = FsFactory::customer(['email' => $marker . '-draft-portal@example.invalid', 'user_id' => $customerUserId]);
            $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'serial_number' => 960000 + wp_rand(1, 30000)]);

            // A sent reply proves the thread is read correctly, so "not listed" can't pass on an empty list
            $sent = FsFactory::conversation($ticket, ['person_id' => $customer->id]);
            $draft = $saveDraft($ticket);

            $become($customerUserId);
            try {
                $result = FsTest::rest('GET', '/customer-portal/tickets/' . Ticket::find($ticket->id)->serial_number);
            } finally {
                $become($adminId);
            }
            FsTest::assertHealthy($result, 'GET /customer-portal/tickets/{serial}');

            $rows = json_decode(wp_json_encode($result['data']['responses'] ?? []), true);
            $ids = array_map('intval', array_column(is_array($rows) ? $rows : [], 'id'));

            FsTest::assert(in_array((int) $sent->id, $ids, true), 'the portal thread lists the sent reply');
            FsTest::assert($draft && !in_array((int) $draft->id, $ids, true), 'the portal thread must not include the draft');
        });

        FsTest::case('approving_a_draft_publishes_it_like_a_normal_reply', function () use ($saveDraft, $marker, $mailsTo) {
            $ticket = FsFactory::ticket(['status' => 'new']);
            $customer = Customer::find($ticket->customer_id);
            $draft = $saveDraft($ticket);
            $before = count(FsTest::sentMails());
            $hooksBefore = did_action('fluent_support/response_added_by_agent');

            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/approve_draft_response/' . $draft->id, [
                'content' => '<p>Approved ' . $marker . '</p>',
            ]);
            FsTest::assertHealthy($result, 'PUT /tickets/{id}/approve_draft_response/{id}');

            // Publishing saves the reply under a new id; the endpoint returns it
            $published = Conversation::find((int) ($result['data']['response']['id'] ?? 0));
            FsTest::assertSame(null, Conversation::find($draft->id), 'the draft row is gone');
            FsTest::assertSame('response', $published ? $published->conversation_type : null, 'the draft becomes a response');
            FsTest::assert(strpos((string) $published->content, 'Approved ' . $marker) !== false, 'the approved text is saved');

            $fresh = Ticket::find($ticket->id);
            FsTest::assertSame('active', $fresh->status, 'a new ticket becomes active');
            FsTest::assertSame(1, (int) $fresh->response_count, 'it counts as one response');
            FsTest::assert(!empty($fresh->waiting_since) && !empty($fresh->last_agent_response), 'the waiting clock is reset');
            FsTest::assertSame($hooksBefore + 1, did_action('fluent_support/response_added_by_agent'), 'the reply hook fires once');
            FsTest::assertSame(1, count($mailsTo($customer->email, $before)), 'the customer is emailed once');
        });

        FsTest::case('approver_editing_someone_elses_draft_publishes_it', function () use ($saveDraft, $marker) {
            $ticket = FsFactory::ticket(['status' => 'new']);
            $draft = $saveDraft($ticket);

            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/responses/' . $draft->id, [
                'content' => '<p>Edited and approved ' . $marker . '</p>',
            ]);
            FsTest::assertHealthy($result, 'PUT /tickets/{id}/responses/{id} by an approver');

            $published = Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->first();
            FsTest::assert($published && strpos((string) $published->content, 'Edited and approved') !== false, 'the draft becomes a response');
            FsTest::assertSame(1, (int) Ticket::find($ticket->id)->response_count, 'it counts as one response');
        });

        // Business rule: AgentTicketPolicy::updateResponse needs manage permission, so a saved draft is out of the drafter's hands
        FsTest::case('draft_only_agent_cannot_change_a_draft_after_saving_it', function () use ($saveDraft, $marker, $become, $drafterUserId, $adminId) {
            $ticket = FsFactory::ticket();
            $draft = $saveDraft($ticket);

            $become($drafterUserId);
            try {
                $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/responses/' . $draft->id, [
                    'content' => '<p>Reworded ' . $marker . '</p>',
                ]);
            } finally {
                $become($adminId);
            }

            FsTest::assert($result['status'] >= 400, 'the edit must be refused, got ' . $result['status']);
            $fresh = Conversation::find($draft->id);
            FsTest::assertSame('draft_response', $fresh->conversation_type, 'the draft stays a draft');
            FsTest::assert(strpos((string) $fresh->content, 'Reworded') === false, 'the draft text is unchanged');
        });

        FsTest::case('agent_without_approve_permission_cannot_approve_a_draft', function () use ($saveDraft, $become, $drafterUserId, $adminId) {
            $ticket = FsFactory::ticket();
            $draft = $saveDraft($ticket);

            $become($drafterUserId);
            try {
                $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/approve_draft_response/' . $draft->id, [
                    'content' => '<p>Self approved</p>',
                ]);
            } finally {
                $become($adminId);
            }

            FsTest::assert($result['status'] >= 400, 'approval must be refused, got ' . $result['status']);
            FsTest::assertSame('draft_response', Conversation::find($draft->id)->conversation_type, 'the draft stays unpublished');
        });
    } finally {
        $become($adminId);

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        foreach ($wpUsers as $userId) {
            wp_delete_user($userId);
        }
    }
};
