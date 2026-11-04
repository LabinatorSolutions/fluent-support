<?php
/**
 * Mailbox endpoints (mailboxes/*, AdminSettingsPolicy): the list with ticket
 * counts, one mailbox, create, update, set default, the per-mailbox email
 * templates, moving tickets and deleting a mailbox.
 *
 * - Errors answer 422 (some answered 200 with only a message, which the UI
 *   shows as success).
 * - Moving tickets takes them from the mailbox in the URL only, and refuses a
 *   move that puts a ticket in a mailbox its agent is restricted from.
 * - Deleting a mailbox moves its tickets and the default flag to the fallback
 *   and deletes its email templates.
 */

use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

return function () {
    $marker = FsFactory::marker();
    $adminId = get_current_user_id();
    $userIds = [];
    $createdBoxIds = [];
    $originalDefault = MailBox::where('is_default', 'yes')->value('id');

    $rest = function ($method, $route, array $params = []) {
        $result = FsTest::rest($method, $route, $params);
        $result['data'] = json_decode(wp_json_encode($result['data']), true);
        return $result;
    };
    $metaCount = fn($boxId) => Meta::where('object_type', MailBox::class)->where('object_id', $boxId)->count();
    $restrictedAgent = function (array $boxIds) use (&$userIds) {
        $login = FsTest::uniq('fs-mb');
        $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
        $userIds[] = $userId;
        $agent = FsFactory::agent(['user_id' => $userId]);
        $agent->updateMeta('agent_restrictions', ['businessBoxRestrictions' => 'yes', 'restrictedBusinessBoxes' => $boxIds]);
        return [$agent, $userId];
    };

    try {
        FsTest::case('mailboxes: the list counts each mailbox\'s tickets; one mailbox reads back', function () use ($rest) {
            $box = FsFactory::mailbox();
            FsFactory::ticket(['mailbox_id' => $box->id]);
            FsFactory::ticket(['mailbox_id' => $box->id]);
            $list = $rest('GET', 'mailboxes')['data']['mailboxes'];
            $row = current(array_filter($list, fn($item) => (int) $item['id'] === (int) $box->id));
            FsTest::assertSame(2, (int) ($row['tickets_count'] ?? -1), 'tickets_count');
            FsTest::assert(isset($row['name'], $row['email'], $row['settings'], $row['is_default']), 'row fields');
            $one = $rest('GET', 'mailboxes/' . $box->id)['data']['mailbox'];
            FsTest::assertSame((int) $box->id, (int) $one['id'], 'one mailbox');
            FsTest::assertSame(404, $rest('GET', 'mailboxes/999999999')['status'], 'unknown');
        });

        FsTest::case('mailboxes: create sets the admin email for a web mailbox; update saves the fields', function () use ($rest, $marker, &$createdBoxIds) {
            $web = $rest('POST', 'mailboxes', ['business' => ['name' => 'Web ' . $marker, 'email' => $marker . '-web@example.invalid', 'box_type' => 'web']]);
            FsTest::assertSame('Mailbox has been created successfully', $web['message'], 'created');
            $createdBoxIds[] = $webId = (int) $web['data']['mailbox']['id'];
            FsTest::assertSame($marker . '-web@example.invalid', MailBox::find($webId)->settings['admin_email_address'] ?? null, 'admin email');
            $email = $rest('POST', 'mailboxes', ['business' => ['name' => 'Email ' . $marker, 'email' => $marker . '-email@example.invalid', 'box_type' => 'email']]);
            $createdBoxIds[] = $emailId = (int) $email['data']['mailbox']['id'];
            FsTest::assertSame('', MailBox::find($emailId)->settings['admin_email_address'] ?? null, 'email mailbox has none yet');

            $saved = $rest('PUT', 'mailboxes/' . $webId, ['business' => ['name' => 'Web renamed ' . $marker, 'email' => $marker . '-web2@example.invalid', 'box_type' => 'web', 'email_footer' => '<p>Thanks</p><script>x</script>', 'settings' => ['admin_email_address' => $marker . '-admin@example.invalid']]]);
            FsTest::assertSame('Mailbox has been saved', $saved['message'], 'saved');
            $box = MailBox::find($webId);
            FsTest::assertSame(['Web renamed ' . $marker, '<p>Thanks</p>x'], [$box->name, $box->email_footer], 'name and footer');

            $missing = $rest('PUT', 'mailboxes/' . $emailId, ['business' => ['name' => 'Email ' . $marker, 'email' => $marker . '-email@example.invalid', 'box_type' => 'email', 'mapped_email' => '']]);
            FsTest::assertSame(422, $missing['status'], 'an email mailbox needs its mapped address');
        });

        FsTest::case('mailboxes: one default at a time', function () use ($rest) {
            $one = FsFactory::mailbox();
            $two = FsFactory::mailbox();
            $rest('PUT', 'mailboxes/' . $one->id . '/set_default');
            $rest('PUT', 'mailboxes/' . $two->id . '/set_default');
            FsTest::assertSame([(int) $two->id], array_map('intval', MailBox::where('is_default', 'yes')->pluck('id')->all()), 'only the last');
        });

        FsTest::case('mailboxes: the email templates of a mailbox', function () use ($rest, $metaCount) {
            $box = FsFactory::mailbox();
            $configs = $rest('GET', 'mailboxes/' . $box->id . '/email_configs')['data'];
            FsTest::assert(in_array('ticket_created_email_to_customer', $configs['email_keys'] ?? [], true) && count($configs['email_configs']) === count($configs['email_keys']), 'configs per key');
            $one = $rest('GET', 'mailboxes/' . $box->id . '/email_settings', ['email_type' => 'ticket_created_email_to_customer'])['data']['email_settings'];
            FsTest::assertSame('ticket_created_email_to_customer', $one['key'] ?? null, 'one template');

            $saved = $rest('PUT', 'mailboxes/' . $box->id . '/email_settings', ['email_type' => 'ticket_created_email_to_customer', 'email_settings' => ['key' => 'ticket_created_email_to_customer', 'email_subject' => 'Hi {{ticket.title}}', 'email_body' => '<p>Body</p>', 'status' => 'yes']]);
            FsTest::assertSame('Settings has been updated', $saved['message'], 'saved');
            FsTest::assertSame('Hi {{ticket.title}}', $box->getMeta('_email_ticket_created_email_to_customer')['email_subject'] ?? null, 'stored');

            $unknown = $rest('PUT', 'mailboxes/' . $box->id . '/email_settings', ['email_type' => 'made_up', 'email_settings' => ['email_subject' => 'x', 'email_body' => 'y']]);
            FsTest::assertSame(422, $unknown['status'], 'unknown email type refused');
            FsTest::assertSame(1, $metaCount($box->id), 'no row for it');
        });

        FsTest::case('mailboxes: moving tickets takes them from the mailbox in the URL only', function () use ($rest) {
            $from = FsFactory::mailbox();
            $to = FsFactory::mailbox();
            $elsewhere = FsFactory::mailbox();
            $mine = FsFactory::ticket(['mailbox_id' => $from->id]);
            $other = FsFactory::ticket(['mailbox_id' => $elsewhere->id]);
            $result = $rest('PUT', 'mailboxes/' . $from->id . '/move_tickets', ['new_box_id' => $to->id, 'move_type' => 'Custom', 'ticket_ids' => [$mine->id, $other->id]]);
            FsTest::assertSame(200, $result['status'], 'moved');
            FsTest::assertSame([(int) $to->id, (int) $elsewhere->id], [(int) Ticket::find($mine->id)->mailbox_id, (int) Ticket::find($other->id)->mailbox_id], 'only the source mailbox\'s ticket');
            FsTest::assertSame(422, $rest('PUT', 'mailboxes/' . $to->id . '/move_tickets', ['new_box_id' => $to->id, 'move_type' => 'All'])['status'], 'same mailbox refused');
        });

        FsTest::case('mailboxes: moving all tickets refuses a ticket whose agent may not work in the new mailbox', function () use ($rest, $restrictedAgent) {
            $from = FsFactory::mailbox();
            $to = FsFactory::mailbox();
            list($agent) = $restrictedAgent([$to->id]);
            $ticket = FsFactory::ticket(['mailbox_id' => $from->id, 'agent_id' => $agent->id]);
            FsTest::assertSame(422, $rest('PUT', 'mailboxes/' . $from->id . '/move_tickets', ['new_box_id' => $to->id, 'move_type' => 'All'])['status'], 'refused');
            FsTest::assertSame((int) $from->id, (int) Ticket::find($ticket->id)->mailbox_id, 'not moved');
        });

        FsTest::case('mailboxes: delete moves tickets and the default to the fallback and deletes the templates', function () use ($rest, $metaCount) {
            $box = FsFactory::mailbox();
            $fallback = FsFactory::mailbox();
            $ticket = FsFactory::ticket(['mailbox_id' => $box->id]);
            $rest('PUT', 'mailboxes/' . $box->id . '/set_default');
            $box->saveMeta('_email_ticket_created_email_to_customer', ['email_subject' => 's', 'email_body' => 'b']);

            $same = $rest('DELETE', 'mailboxes/' . $box->id, ['fallback_id' => $box->id]);
            FsTest::assertSame(422, $same['status'], 'fallback must be another mailbox');
            FsTest::assert(MailBox::find($box->id) !== null, 'not deleted');

            $fired = [];
            $listen = function () use (&$fired) {
                $fired[] = current_filter();
            };
            add_action('fluent_support/before_delete_email_box', $listen);
            add_action('fluent_support/mailbox_deleted', $listen);
            try {
                $deleted = $rest('DELETE', 'mailboxes/' . $box->id, ['fallback_id' => $fallback->id]);
            } finally {
                remove_action('fluent_support/before_delete_email_box', $listen);
                remove_action('fluent_support/mailbox_deleted', $listen);
            }
            FsTest::assertSame('Selected Business has been deleted', $deleted['message'], 'deleted');
            FsTest::assertSame(null, MailBox::find($box->id), 'gone');
            FsTest::assertSame((int) $fallback->id, (int) Ticket::find($ticket->id)->mailbox_id, 'ticket moved');
            FsTest::assertSame('yes', MailBox::find($fallback->id)->is_default, 'default moved');
            FsTest::assertSame(0, $metaCount($box->id), 'templates deleted');
            FsTest::assertSame(['fluent_support/before_delete_email_box', 'fluent_support/mailbox_deleted'], $fired, 'hooks');
        });

        FsTest::case('mailboxes: agents without fst_manage_settings are refused', function () use ($rest, $restrictedAgent, $adminId) {
            list(, $userId) = $restrictedAgent([]);
            PermissionManager::attachPermissions($userId, ['fst_view_tickets', 'fst_manage_own_tickets']);
            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);
            try {
                FsTest::assertSame(403, $rest('GET', 'mailboxes')['status'], 'list refused');
                FsTest::assertSame(403, $rest('POST', 'mailboxes', ['business' => ['name' => 'x', 'email' => 'x@example.invalid']])['status'], 'create refused');
            } finally {
                wp_set_current_user($adminId);
                PermissionManager::currentUserPermissions(false);
            }
        });
    } finally {
        if ($originalDefault) {
            MailBox::where('id', '!=', $originalDefault)->update(['is_default' => 'no']);
            MailBox::where('id', $originalDefault)->update(['is_default' => 'yes']);
        }
        foreach ($createdBoxIds as $boxId) {
            Meta::where('object_type', MailBox::class)->where('object_id', $boxId)->delete();
            MailBox::where('id', $boxId)->delete();
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
