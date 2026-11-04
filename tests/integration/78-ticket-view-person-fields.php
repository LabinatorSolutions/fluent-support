<?php
/**
 * GET /tickets/{id} and the reply, edit and draft-approve endpoints serialize the
 * people in the thread. The agent ticket view only shows their name, photo, type and
 * title, plus a customer's email on the "From" line. Agents' email, IP and notes
 * need fst_sensitive_data elsewhere, so they must not leave the server here either.
 * Viewer users aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $viewerUserId = 0;
    $marker = FsFactory::marker();

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    try {
        $login = FsTest::uniq('fs-person-viewer');
        $viewerUserId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($viewerUserId)) {
            throw new RuntimeException('Could not create viewer user: ' . $viewerUserId->get_error_message());
        }

        $viewer = FsFactory::agent();
        $viewer->user_id = $viewerUserId;
        $viewer->ip_address = '10.77.0.3';
        $viewer->save();
        PermissionManager::attachPermissions($viewerUserId, ['fst_manage_other_tickets', 'fst_approve_draft_reply']);

        $otherAgent = FsFactory::agent([
            'ip_address' => '10.77.0.1',
            'note'       => 'Agent note ' . $marker,
            'title'      => 'Billing lead',
        ]);
        $customer = FsFactory::customer(['ip_address' => '10.77.0.2']);

        // Assigned to, opened by and closed by the other agent, who also replied.
        $ticket = FsFactory::ticket([
            'customer_id' => $customer->id,
            'agent_id'    => $otherAgent->id,
            'created_by'  => $otherAgent->id,
            'status'      => 'closed',
            'closed_by'   => $otherAgent->id,
        ]);
        FsFactory::conversation($ticket, ['person_id' => $otherAgent->id]);
        FsFactory::conversation($ticket, ['person_id' => $customer->id]);

        $fetch = function () use ($runAs, $viewerUserId, $ticket) {
            $runAs($viewerUserId);
            $result = FsTest::rest('GET', '/tickets/' . $ticket->id);
            FsTest::assertHealthy($result, 'GET /tickets/{id}');

            return json_decode(wp_json_encode($result['data']), true);
        };

        $responseBy = function ($payload, $personId) {
            foreach ((array) ($payload['responses'] ?? []) as $response) {
                if ((int) ($response['person']['id'] ?? 0) === (int) $personId) {
                    return $response;
                }
            }
            return null;
        };

        FsTest::case('other_agents_email_ip_and_note_are_not_in_the_ticket_view', function () use ($fetch, $otherAgent, $marker) {
            $json = wp_json_encode($fetch());

            FsTest::assert(strpos($json, $otherAgent->email) === false, 'the other agent\'s email must not be sent');
            FsTest::assert(strpos($json, '10.77.0.1') === false, 'the other agent\'s IP must not be sent');
            FsTest::assert(strpos($json, 'Agent note ' . $marker) === false, 'the other agent\'s note must not be sent');
        });

        FsTest::case('agent_reply_keeps_the_fields_the_thread_shows', function () use ($fetch, $responseBy, $otherAgent) {
            $person = $responseBy($fetch(), $otherAgent->id)['person'] ?? [];

            FsTest::assertSame($otherAgent->full_name, $person['full_name'] ?? null, 'full_name');
            FsTest::assertSame('agent', $person['person_type'] ?? null, 'person_type');
            FsTest::assertSame('Billing lead', $person['title'] ?? null, 'title');
            FsTest::assert(!empty($person['photo']), 'photo is sent');
        });

        FsTest::case('customer_reply_keeps_the_email_for_the_from_line', function () use ($fetch, $responseBy, $customer) {
            $person = $responseBy($fetch(), $customer->id)['person'] ?? [];

            FsTest::assertSame($customer->email, $person['email'] ?? null, 'the customer email on the From line');
            FsTest::assert(!array_key_exists('ip_address', $person), 'the reply person carries no IP');
        });

        FsTest::case('assigned_and_closing_agent_carry_display_fields_only', function () use ($fetch, $otherAgent) {
            $ticketPayload = $fetch()['ticket'] ?? [];

            FsTest::assertSame((int) $otherAgent->id, (int) ($ticketPayload['agent']['id'] ?? 0), 'ticket.agent is sent');
            FsTest::assertSame($otherAgent->full_name, $ticketPayload['closed_by_person']['full_name'] ?? null,
                'closed_by_person keeps the name');
            FsTest::assertSame($otherAgent->full_name, $ticketPayload['created_by_agent']['full_name'] ?? null,
                'created_by_agent keeps the name');
            FsTest::assert(!array_key_exists('created_by_person', $ticketPayload),
                'the creating agent row is not serialized');
        });

        FsTest::case('the_tickets_own_customer_record_is_unchanged', function () use ($fetch, $customer) {
            $customerPayload = $fetch()['ticket']['customer'] ?? [];

            // The sidebar shows the customer's details; this suite only narrows agents and reply authors.
            FsTest::assertSame($customer->email, $customerPayload['email'] ?? null, 'ticket.customer.email');
            FsTest::assertSame('10.77.0.2', $customerPayload['ip_address'] ?? null, 'ticket.customer.ip_address');
        });

        $assertNoAgentPrivateData = function ($result, $label) use ($otherAgent, $viewer, $marker) {
            $json = wp_json_encode($result['data']);

            FsTest::assert(strpos($json, $otherAgent->email) === false, $label . ': the other agent\'s email must not be sent');
            FsTest::assert(strpos($json, '10.77.0.1') === false, $label . ': the other agent\'s IP must not be sent');
            FsTest::assert(strpos($json, 'Agent note ' . $marker) === false, $label . ': the other agent\'s note must not be sent');
            FsTest::assert(strpos($json, $viewer->email) === false, $label . ': the acting agent\'s email must not be sent');
            FsTest::assert(strpos($json, '10.77.0.3') === false, $label . ': the acting agent\'s IP must not be sent');
        };

        FsTest::case('editing_another_agents_reply_returns_display_fields_only', function () use (
            $runAs, $viewerUserId, $ticket, $otherAgent, $marker, $assertNoAgentPrivateData
        ) {
            $reply = FsFactory::conversation($ticket, ['person_id' => $otherAgent->id]);

            $runAs($viewerUserId);
            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/responses/' . $reply->id, [
                'content' => '<p>Edited ' . esc_html($marker) . '</p>',
            ]);
            FsTest::assertHealthy($result, 'PUT /tickets/{id}/responses/{response_id}');

            $assertNoAgentPrivateData($result, 'edit');
            $person = json_decode(wp_json_encode($result['data']), true)['response']['person'] ?? [];
            FsTest::assertSame($otherAgent->full_name, $person['full_name'] ?? null, 'the edited reply keeps its author name');
        });

        FsTest::case('approving_another_agents_draft_returns_display_fields_only', function () use (
            $runAs, $viewerUserId, $ticket, $otherAgent, $marker, $assertNoAgentPrivateData
        ) {
            $draft = FsFactory::conversation($ticket, [
                'person_id'         => $otherAgent->id,
                'conversation_type' => 'draft_response',
            ]);

            $runAs($viewerUserId);
            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/approve_draft_response/' . $draft->id, [
                'content' => '<p>Approved ' . esc_html($marker) . '</p>',
            ]);
            FsTest::assertHealthy($result, 'PUT /tickets/{id}/approve_draft_response/{response_id}');

            $assertNoAgentPrivateData($result, 'approve draft');
        });

        FsTest::case('agent_reply_returns_display_fields_only', function () use (
            $runAs, $viewerUserId, $ticket, $viewer, $marker, $assertNoAgentPrivateData
        ) {
            $runAs($viewerUserId);
            $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
                'content'           => '<p>Agent reply ' . esc_html($marker) . '</p>',
                'conversation_type' => 'response',
            ]);
            FsTest::assertHealthy($result, 'POST /tickets/{id}/responses');

            $assertNoAgentPrivateData($result, 'reply');
            $person = json_decode(wp_json_encode($result['data']), true)['response']['person'] ?? [];
            FsTest::assertSame((int) $viewer->id, (int) ($person['id'] ?? 0), 'the new reply keeps its author id');
            FsTest::assertSame($viewer->full_name, $person['full_name'] ?? null, 'the new reply keeps its author name');
        });

        // The "Closed Ticket" bar reads ticket.resolved_at and ticket.closed_by_person straight
        // from these responses; without them it showed "This ticket was closed on null."
        FsTest::case('reply_and_close_returns_when_and_by_whom_with_display_fields_only', function () use (
            $runAs, $viewerUserId, $customer, $otherAgent, $viewer, $marker, $assertNoAgentPrivateData
        ) {
            $openTicket = FsFactory::ticket([
                'customer_id' => $customer->id,
                'agent_id'    => $otherAgent->id,
                'status'      => 'active',
            ]);

            $runAs($viewerUserId);
            $result = FsTest::rest('POST', '/tickets/' . $openTicket->id . '/responses', [
                'content'           => '<p>Closing reply ' . esc_html($marker) . '</p>',
                'conversation_type' => 'response',
                'close_ticket'      => 'yes',
            ]);
            FsTest::assertHealthy($result, 'POST /tickets/{id}/responses with close_ticket');

            $assertNoAgentPrivateData($result, 'reply and close');
            $ticketPayload = json_decode(wp_json_encode($result['data']), true)['ticket'] ?? [];
            FsTest::assertSame('closed', $ticketPayload['status'] ?? null, 'the ticket is closed');
            FsTest::assert(!empty($ticketPayload['resolved_at']), 'resolved_at is sent');
            FsTest::assertSame($viewer->full_name, $ticketPayload['closed_by_person']['full_name'] ?? null,
                'closed_by_person names the agent who closed it');
        });

        FsTest::case('close_returns_when_and_by_whom_with_display_fields_only', function () use (
            $runAs, $viewerUserId, $customer, $otherAgent, $viewer, $assertNoAgentPrivateData
        ) {
            $openTicket = FsFactory::ticket([
                'customer_id' => $customer->id,
                'agent_id'    => $otherAgent->id,
                'status'      => 'active',
            ]);

            $runAs($viewerUserId);
            $result = FsTest::rest('POST', '/tickets/' . $openTicket->id . '/close', [
                'close_ticket_silently' => 'yes',
            ]);
            FsTest::assertHealthy($result, 'POST /tickets/{id}/close');

            $assertNoAgentPrivateData($result, 'close');
            $ticketPayload = json_decode(wp_json_encode($result['data']), true)['ticket'] ?? [];
            FsTest::assertSame('closed', $ticketPayload['status'] ?? null, 'the ticket is closed');
            FsTest::assert(!empty($ticketPayload['resolved_at']), 'resolved_at is sent');
            FsTest::assertSame($viewer->full_name, $ticketPayload['closed_by_person']['full_name'] ?? null,
                'closed_by_person names the agent who closed it');
        });
    } finally {
        $runAs($adminId);
        if ($viewerUserId && !is_wp_error($viewerUserId)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($viewerUserId);
        }
    }
};
