<?php
/**
 * GET /tickets/{id} takes a `with` list of relations. The access check covers the
 * requested ticket only, so a nested relation such as customer.tickets must not
 * pull in the customer's other tickets past the agent's visibility and mailbox scope.
 * Viewer users aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $viewerUserId = 0;

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    try {
        $login = FsTest::uniq('fs-with-viewer');
        $viewerUserId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($viewerUserId)) {
            throw new RuntimeException('Could not create viewer user: ' . $viewerUserId->get_error_message());
        }

        // Manage Own Tickets, barred from $restrictedBox.
        $viewer = FsFactory::agent();
        $viewer->user_id = $viewerUserId;
        $viewer->save();
        PermissionManager::attachPermissions($viewerUserId, ['fst_manage_own_tickets']);

        $box = FsFactory::mailbox();
        $restrictedBox = FsFactory::mailbox();
        $viewer->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        $otherAgent = FsFactory::agent();
        $customer = FsFactory::customer();

        $ownTicket = FsFactory::ticket([
            'customer_id' => $customer->id,
            'agent_id'    => $viewer->id,
            'mailbox_id'  => $box->id,
            'status'      => 'active',
        ]);

        // Same customer, another agent's ticket in a mailbox the viewer is barred from.
        $hiddenNeedle = FsTest::uniq('with-hidden');
        $hiddenTicket = FsFactory::ticket([
            'customer_id' => $customer->id,
            'agent_id'    => $otherAgent->id,
            'mailbox_id'  => $restrictedBox->id,
            'status'      => 'active',
            'title'       => 'Hidden ' . $hiddenNeedle,
        ]);
        FsFactory::conversation($hiddenTicket, [
            'person_id'         => $otherAgent->id,
            'conversation_type' => 'note',
            'content'           => '<p>Hidden note ' . $hiddenNeedle . ' ' . esc_html(FsFactory::marker()) . '</p>',
        ]);

        FsTest::case('viewer_cannot_open_the_hidden_ticket_directly', function () use ($runAs, $viewerUserId, $hiddenTicket) {
            $runAs($viewerUserId);
            $result = FsTest::rest('GET', '/tickets/' . $hiddenTicket->id);

            FsTest::assert($result['status'] === 403,
                'fixture: the hidden ticket must be out of the viewer\'s scope, got ' . $result['status']);
        });

        foreach (['customer.tickets', 'customer.tickets.responses', 'agent.tickets.responses'] as $with) {
            FsTest::case('nested_with_' . str_replace('.', '_', $with) . '_does_not_reveal_out_of_scope_tickets', function () use (
                $runAs, $viewerUserId, $ownTicket, $hiddenNeedle, $with
            ) {
                $runAs($viewerUserId);
                $result = FsTest::rest('GET', '/tickets/' . $ownTicket->id, ['with' => [$with]]);
                FsTest::assertHealthy($result, 'GET /tickets/{id}?with[]=' . $with);

                FsTest::assert(strpos(wp_json_encode($result['data']), $hiddenNeedle) === false,
                    'with[]=' . $with . ' must not return the customer\'s out-of-scope ticket or its note');
            });
        }

        FsTest::case('allowed_with_relation_still_loads', function () use ($runAs, $viewerUserId, $ownTicket, $customer) {
            $runAs($viewerUserId);
            $result = FsTest::rest('GET', '/tickets/' . $ownTicket->id, ['with' => ['customer']]);
            FsTest::assertHealthy($result, 'GET /tickets/{id}?with[]=customer');

            $payload = json_decode(wp_json_encode($result['data']['ticket'] ?? []), true);
            FsTest::assertSame((int) $customer->id, (int) ($payload['customer']['id'] ?? 0),
                'a listed relation is still loaded');
        });
    } finally {
        $runAs($adminId);
        if ($viewerUserId && !is_wp_error($viewerUserId)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($viewerUserId);
        }
    }
};
