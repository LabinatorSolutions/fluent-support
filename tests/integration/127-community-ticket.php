<?php
/**
 * Create a ticket from a FluentCommunity post or comment (POST community/ticket).
 *
 * A site-level community moderator who can manage tickets opens a ticket for the post
 * author. The ticket and its lookup meta are one transaction under a short lock, so a
 * repeat returns the same ticket. The customer's created emails go out unless the
 * agent asks for a silent create. Skips when FluentCommunity is not active.
 *
 * FluentCommunity rows and users are not factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\App\Vite;

return function () {

    if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
        FsTest::skip('FluentCommunity is not active');
        return;
    }

    global $wpdb;

    $adminId = get_current_user_id();
    $marker = FsFactory::marker();

    $users = [];
    $fcRows = ['feeds' => [], 'comments' => [], 'spaces' => [], 'xprofiles' => [], 'metas' => []];

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    // A WordPress user with an email the fixture cleanup recognises.
    $makeUser = function ($label) use (&$users, $marker) {
        $login = FsTest::uniq('fs-fcom-' . $label);
        $id = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $marker . '-' . $label . '-' . wp_generate_password(6, false, false) . '@example.test',
            'role'       => 'subscriber',
        ]);
        if (is_wp_error($id)) {
            throw new RuntimeException('Could not create user: ' . $id->get_error_message());
        }
        $users[] = $id;

        return $id;
    };

    $xprofile = function ($userId, $status = 'active') use (&$fcRows) {
        $row = \FluentCommunity\App\Models\XProfile::create([
            'user_id'      => $userId,
            'username'     => 'fs_fcom_' . $userId,
            'display_name' => 'Fixture ' . $userId,
            'status'       => $status,
        ]);
        $fcRows['xprofiles'][] = $row->id;

        return $row;
    };

    // Site-level community role, as FluentCommunity stores it. Each user is made once:
    // FluentCommunity caches a user's permissions for the request.
    $setCommunityRole = function ($userId, $role) use (&$fcRows) {
        $meta = \FluentCommunity\App\Models\Meta::create([
            'object_type' => 'user',
            'object_id'   => $userId,
            'meta_key'    => '_user_community_roles',
            'value'       => [$role],
        ]);
        $fcRows['metas'][] = $meta->id;
    };

    $makeAgentUser = function (array $permissions, array $restrictedBoxIds = []) use ($makeUser) {
        $userId = $makeUser('agent');
        $agent = FsFactory::agent();
        $agent->user_id = $userId;
        $agent->save();
        PermissionManager::attachPermissions($userId, $permissions);

        if ($restrictedBoxIds) {
            $agent->updateMeta('agent_restrictions', [
                'businessBoxRestrictions' => true,
                'restrictedBusinessBoxes' => array_map('intval', $restrictedBoxIds),
            ]);
        }

        return $userId;
    };

    $manage = ['fst_view_tickets', 'fst_manage_own_tickets', 'fst_manage_unassigned_tickets', 'fst_manage_other_tickets'];

    $space = \FluentCommunity\App\Models\Space::create([
        'title'   => 'Fixture space ' . $marker,
        'slug'    => FsTest::uniq('fs-fcom-space'),
        'type'    => 'community',
        'privacy' => 'public',
        'status'  => 'published',
    ]);
    $fcRows['spaces'][] = $space->id;

    $makePost = function ($authorId, array $attributes = []) use (&$fcRows, $space, $marker) {
        $feed = \FluentCommunity\App\Models\Feed::create(array_merge([
            'user_id'          => $authorId,
            'space_id'         => $space->id,
            'title'            => 'Community post ' . $marker,
            'slug'             => FsTest::uniq('fs-fcom-post'),
            'message'          => 'Post body ' . $marker,
            'message_rendered' => '<p>Post body ' . $marker . '</p>',
            'type'             => 'text',
            'status'           => 'published',
        ], $attributes));
        $fcRows['feeds'][] = $feed->id;

        return $feed;
    };

    $makeComment = function ($authorId, $postId, array $attributes = []) use (&$fcRows, $marker) {
        $comment = \FluentCommunity\App\Models\Comment::create(array_merge([
            'user_id'          => $authorId,
            'post_id'          => $postId,
            'message'          => 'Comment body ' . $marker,
            'message_rendered' => '<p>Comment body ' . $marker . '</p>',
            'type'             => 'comment',
            'status'           => 'published',
        ], $attributes));
        $fcRows['comments'][] = $comment->id;

        return $comment;
    };

    // The endpoint uses the default mailbox; point it at a fixture one so no real
    // mailbox setting is touched, and switch on the customer's created emails there.
    $box = FsFactory::mailbox(['settings' => ['admin_email_address' => '']]);
    $settings = new Settings();
    foreach (['ticket_created_email_to_customer', 'ticket_created_by_agent_email_to_customer'] as $key) {
        $emailSettings = $settings->getBoxEmailSettings($box, $key);
        $emailSettings['status'] = 'yes';
        $settings->saveBoxEmailSettings($box, $key, $emailSettings);
    }
    $routeToBox = function ($data) use ($box) {
        $data['mailbox_id'] = $box->id;

        return $data;
    };
    add_filter('fluent_support/create_ticket_data', $routeToBox);

    $create = function (array $params) {
        return FsTest::rest('POST', '/community/ticket', $params);
    };

    $adopt = function ($result) {
        if (!empty($result['data']['ticket_id'])) {
            $ticket = Ticket::find($result['data']['ticket_id']);
            if ($ticket && strpos((string) $ticket->title, FsFactory::marker()) !== false) {
                FsFactory::adoptRestTicket($ticket);
            }
        }

        return $result;
    };

    $adoptCustomerOf = function ($authorId) {
        $customer = Customer::where('user_id', $authorId)->first();
        if ($customer && strpos((string) $customer->email, FsFactory::marker()) !== false) {
            FsFactory::adoptRestCustomer($customer);
        }

        return $customer;
    };

    $ticketCount = function () {
        return Ticket::where('source', 'fluent_community')->count();
    };

    $metaCount = function ($key) {
        return Meta::where('object_type', 'ticket')->where('key', $key)->count();
    };

    $lockName = function ($metaKey) {
        return '_fcom_lock' . $metaKey;
    };

    $lockExists = function ($metaKey) use ($wpdb, $lockName) {
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$wpdb->options` WHERE option_name = %s", $lockName($metaKey)));
    };

    // The feature is off by default (Business Settings); switch it on for the suite.
    $originalBusiness = \FluentSupport\App\Services\Helper::getOption('global_business_settings', []);
    $setEnabled = function ($on) use ($originalBusiness) {
        \FluentSupport\App\Services\Helper::updateOption('global_business_settings', array_merge((array) $originalBusiness, ['enable_community_tickets' => $on ? 'yes' : 'no']));
    };
    $setEnabled(true);

    try {
        $moderatorAgentId = $makeAgentUser($manage);
        $setCommunityRole($moderatorAgentId, 'moderator');

        FsTest::interceptMail();

        // ---------------------------------------------------------------- permissions

        FsTest::case('a plain community member cannot create a ticket', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adminId, $ticketCount) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $memberId = $makeUser('member');
            $before = $ticketCount();

            $runAs($memberId);
            $result = $create(['type' => 'feed', 'id' => $post->id]);
            $runAs($adminId);

            FsTest::assertSame(403, $result['status'], 'status');
            FsTest::assertSame($before, $ticketCount(), 'no ticket');
        });

        FsTest::case('a moderator who is not a ticket agent is refused', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $setCommunityRole, $adminId, $ticketCount) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $moderatorId = $makeUser('mod-only');
            $setCommunityRole($moderatorId, 'moderator');
            $before = $ticketCount();

            $runAs($moderatorId);
            $result = $create(['type' => 'feed', 'id' => $post->id]);
            $runAs($adminId);

            FsTest::assertSame(403, $result['status'], 'status');
            FsTest::assertSame($before, $ticketCount(), 'no ticket');
        });

        FsTest::case('a ticket agent who is not a community moderator is refused', function () use ($makeUser, $makeAgentUser, $manage, $runAs, $xprofile, $makePost, $create, $adminId, $ticketCount) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $agentId = $makeAgentUser($manage);
            $before = $ticketCount();

            $runAs($agentId);
            $result = $create(['type' => 'feed', 'id' => $post->id]);
            $runAs($adminId);

            FsTest::assertSame(403, $result['status'], 'status');
            FsTest::assertSame($before, $ticketCount(), 'no ticket');
        });

        FsTest::case('a moderator of one space only is refused', function () use ($makeUser, $makeAgentUser, $manage, $runAs, $xprofile, $makePost, $create, $space, $adminId, $ticketCount, &$fcRows) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $agentId = $makeAgentUser($manage);
            $pivot = \FluentCommunity\App\Models\SpaceUserPivot::create([
                'space_id' => $space->id,
                'user_id'  => $agentId,
                'status'   => 'active',
                'role'     => 'moderator',
            ]);
            $before = $ticketCount();

            try {
                $runAs($agentId);
                $result = $create(['type' => 'feed', 'id' => $post->id]);
                $runAs($adminId);
            } finally {
                \FluentCommunity\App\Models\SpaceUserPivot::where('id', $pivot->id)->delete();
            }

            FsTest::assertSame(403, $result['status'], 'status');
            FsTest::assertSame($before, $ticketCount(), 'no ticket');
        });

        FsTest::case('an agent restricted from the target mailbox gets 403 and no ticket', function () use ($makeUser, $makeAgentUser, $manage, $setCommunityRole, $runAs, $xprofile, $makePost, $create, $box, $adminId, $ticketCount, $metaCount) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $agentId = $makeAgentUser($manage, [$box->id]);
            $setCommunityRole($agentId, 'moderator');
            $before = $ticketCount();

            $runAs($agentId);
            $result = $create(['type' => 'feed', 'id' => $post->id]);
            $runAs($adminId);

            FsTest::assertSame(403, $result['status'], 'status');
            FsTest::assertSame($before, $ticketCount(), 'no ticket');
            FsTest::assertSame(0, $metaCount('_fcom_feed_' . $post->id), 'no meta');
        });

        FsTest::case('an existing ticket in a mailbox the agent cannot see is not returned', function () use ($makeUser, $makeAgentUser, $manage, $setCommunityRole, $runAs, $xprofile, $makePost, $create, $adopt, $box, $moderatorAgentId, $adminId) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);

            $runAs($moderatorAgentId);
            $first = $adopt($create(['type' => 'feed', 'id' => $post->id]));
            $runAs($adminId);
            FsTest::assertHealthy($first, 'first create');

            $restrictedId = $makeAgentUser($manage, [$box->id]);
            $setCommunityRole($restrictedId, 'moderator');

            $runAs($restrictedId);
            $second = $create(['type' => 'feed', 'id' => $post->id]);
            $runAs($adminId);

            FsTest::assertSame(403, $second['status'], 'status');
            FsTest::assert(empty($second['data']['ticket_id']) && empty($second['data']['ticket_url']), 'the ticket id is not leaked');
        });

        // ---------------------------------------------------------------- scope

        FsTest::case('a post on the main feed (no space) and a comment on it get a ticket', function () use ($makeUser, $runAs, $xprofile, $makePost, $makeComment, $create, $adopt, $moderatorAgentId, $adminId) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author, ['space_id' => null]);
            $comment = $makeComment($author, $post->id);

            $runAs($moderatorAgentId);
            $postResult = $adopt($create(['type' => 'feed', 'id' => $post->id]));
            $commentResult = $adopt($create(['type' => 'comment', 'id' => $comment->id]));
            $runAs($adminId);

            FsTest::assertHealthy($postResult, 'post');
            FsTest::assertSame(false, $postResult['data']['already_exists'], 'post already_exists');
            FsTest::assertHealthy($commentResult, 'comment');
        });

        FsTest::case('an unpublished post, a missing id and a comment on an unpublished post are 404 with no ticket', function () use ($makeUser, $runAs, $xprofile, $makePost, $makeComment, $create, $moderatorAgentId, $adminId, $ticketCount) {
            $author = $makeUser('author');
            $xprofile($author);
            $draft = $makePost($author, ['status' => 'draft']);
            $onDraft = $makeComment($author, $draft->id);
            $before = $ticketCount();

            $runAs($moderatorAgentId);
            $draftResult = $create(['type' => 'feed', 'id' => $draft->id]);
            $missing = $create(['type' => 'feed', 'id' => 2147483000]);
            $commentResult = $create(['type' => 'comment', 'id' => $onDraft->id]);
            $runAs($adminId);

            FsTest::assertSame(404, $draftResult['status'], 'unpublished post');
            FsTest::assertSame(404, $missing['status'], 'missing post');
            FsTest::assertSame(404, $commentResult['status'], 'comment on unpublished post');
            FsTest::assertSame($before, $ticketCount(), 'no ticket');
        });

        FsTest::case('an unknown type is rejected', function () use ($runAs, $create, $moderatorAgentId, $adminId) {
            $runAs($moderatorAgentId);
            $result = $create(['type' => 'space', 'id' => 1]);
            $runAs($adminId);

            FsTest::assertSame(422, $result['status'], 'status');
        });

        FsTest::case('a blocked or pending author gets no ticket and no meta', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $moderatorAgentId, $adminId, $ticketCount, $metaCount) {
            $before = $ticketCount();
            $runAs($moderatorAgentId);

            foreach (['blocked', 'pending'] as $status) {
                $author = $makeUser('author-' . $status);
                $xprofile($author, $status);
                $post = $makePost($author);

                $result = $create(['type' => 'feed', 'id' => $post->id]);

                FsTest::assertSame(422, $result['status'], $status . ' status');
                FsTest::assertSame(0, $metaCount('_fcom_feed_' . $post->id), $status . ' meta');
            }

            $runAs($adminId);
            FsTest::assertSame($before, $ticketCount(), 'no ticket');
        });

        // ---------------------------------------------------------------- ticket and customer

        FsTest::case('the ticket belongs to the author, carries the post text and the source, and links to the admin', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $adoptCustomerOf, $moderatorAgentId, $adminId, $metaCount, $marker) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);

            $runAs($moderatorAgentId);
            $result = $adopt($create(['type' => 'feed', 'id' => $post->id]));
            $runAs($adminId);
            $customer = $adoptCustomerOf($author);

            FsTest::assertHealthy($result, 'create');
            FsTest::assertSame(false, $result['data']['already_exists'], 'already_exists');
            $ticket = Ticket::find($result['data']['ticket_id']);
            FsTest::assert((bool) $ticket, 'ticket exists');
            FsTest::assertSame('fluent_community', $ticket->source, 'source');
            FsTest::assert($customer && (int) $ticket->customer_id === (int) $customer->id, 'customer is the author');
            FsTest::assert(strpos((string) $ticket->content, 'Post body ' . $marker) !== false, 'content has the post text, got: ' . $ticket->content);
            FsTest::assert(strpos((string) $ticket->content, 'started this conversation') === false, 'not an outreach placeholder');
            FsTest::assert(strpos((string) $ticket->content, $post->getPermalink()) !== false, 'content links the post');
            FsTest::assertSame($post->title, $ticket->title, 'title is the post title');
            FsTest::assertSame(1, $metaCount('_fcom_feed_' . $post->id), 'one meta row');
            FsTest::assert(strpos($result['data']['ticket_url'], 'page=fluent-support#/tickets/' . $ticket->id . '/view') !== false, 'admin url, got: ' . $result['data']['ticket_url']);
            FsTest::assertSame((string) Ticket::find($ticket->id)->display_ticket_number, $result['data']['ticket_number'], 'ticket_number is the number the admin shows');
        });

        FsTest::case('a comment ticket uses the comment text and its own meta key', function () use ($makeUser, $runAs, $xprofile, $makePost, $makeComment, $create, $adopt, $adoptCustomerOf, $moderatorAgentId, $adminId, $metaCount, $marker) {
            $postAuthor = $makeUser('post-author');
            $xprofile($postAuthor);
            $commenter = $makeUser('commenter');
            $xprofile($commenter);
            $post = $makePost($postAuthor);
            $comment = $makeComment($commenter, $post->id);

            $runAs($moderatorAgentId);
            $result = $adopt($create(['type' => 'comment', 'id' => $comment->id]));
            $runAs($adminId);
            $customer = $adoptCustomerOf($commenter);

            FsTest::assertHealthy($result, 'create');
            $ticket = Ticket::find($result['data']['ticket_id']);
            FsTest::assert($customer && (int) $ticket->customer_id === (int) $customer->id, 'customer is the commenter, not the post author');
            FsTest::assert(strpos((string) $ticket->content, 'Comment body ' . $marker) !== false, 'comment text');
            FsTest::assertSame($post->title, $ticket->title, 'title is the parent post title');
            FsTest::assertSame(1, $metaCount('_fcom_comment_' . $comment->id), 'comment meta');
            FsTest::assertSame(0, $metaCount('_fcom_feed_' . $post->id), 'the post has no ticket');
        });

        FsTest::case('a guest customer with the author email is reused, not duplicated', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $moderatorAgentId, $adminId) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $guest = FsFactory::customer(['email' => get_userdata($author)->user_email]);
            $before = Customer::where('email', $guest->email)->count();

            $runAs($moderatorAgentId);
            $result = $adopt($create(['type' => 'feed', 'id' => $post->id]));
            $runAs($adminId);

            FsTest::assertHealthy($result, 'create');
            FsTest::assertSame((int) $guest->id, (int) Ticket::find($result['data']['ticket_id'])->customer_id, 'guest customer used');
            FsTest::assertSame($before, Customer::where('email', $guest->email)->count(), 'no second customer');
        });

        FsTest::case('a customer linked to the user id is reused', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $moderatorAgentId, $adminId) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $linked = FsFactory::customer(['user_id' => $author]);

            $runAs($moderatorAgentId);
            $result = $adopt($create(['type' => 'feed', 'id' => $post->id]));
            $runAs($adminId);

            FsTest::assertSame((int) $linked->id, (int) Ticket::find($result['data']['ticket_id'])->customer_id, 'linked customer used');
            FsTest::assertSame(1, Customer::where('user_id', $author)->count(), 'one customer for the user');
        });

        // ---------------------------------------------------------------- repeat

        FsTest::case('a second call returns the same ticket, also when it is closed, with no new ticket or email', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $moderatorAgentId, $adminId, $ticketCount) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);

            $runAs($moderatorAgentId);
            $first = $adopt($create(['type' => 'feed', 'id' => $post->id]));
            $ticket = Ticket::find($first['data']['ticket_id']);
            $ticket->status = 'closed';
            $ticket->save();

            $count = $ticketCount();
            FsTest::interceptMail();
            $second = $create(['type' => 'feed', 'id' => $post->id]);
            $runAs($adminId);

            FsTest::assertHealthy($second, 'second call');
            FsTest::assertSame(true, $second['data']['already_exists'], 'already_exists');
            FsTest::assertSame((int) $ticket->id, (int) $second['data']['ticket_id'], 'same ticket');
            FsTest::assertSame((string) $ticket->display_ticket_number, $second['data']['ticket_number'], 'existing ticket keeps its admin number');
            FsTest::assertSame($count, $ticketCount(), 'no new ticket');
            FsTest::assertSame(0, count(FsTest::sentMails()), 'no email');
            FsTest::assertSame('closed', Ticket::find($ticket->id)->status, 'still closed');
        });

        // ---------------------------------------------------------------- email

        FsTest::case('the author is emailed by default', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $moderatorAgentId, $adminId) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $email = get_userdata($author)->user_email;

            FsTest::interceptMail();
            $runAs($moderatorAgentId);
            $result = $adopt($create(['type' => 'feed', 'id' => $post->id]));
            $runAs($adminId);

            FsTest::assertHealthy($result, 'create');
            $toAuthor = 0;
            foreach (FsTest::sentMails() as $mail) {
                $to = isset($mail['to']) ? (array) $mail['to'] : [];
                $toAuthor += in_array($email, $to, true) ? 1 : 0;
            }
            // The fixture mailbox has both created emails on: still one email.
            FsTest::assertSame(1, $toAuthor, 'one created email to the author');
        });

        FsTest::case('a silent create sends no customer email but still fires ticket_created', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $moderatorAgentId, $adminId) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $email = get_userdata($author)->user_email;

            $fired = 0;
            $listener = function () use (&$fired) {
                $fired++;
            };
            add_action('fluent_support/ticket_created', $listener);

            FsTest::interceptMail();
            try {
                $runAs($moderatorAgentId);
                $result = $adopt($create(['type' => 'feed', 'id' => $post->id, 'notify' => false]));
                $runAs($adminId);
            } finally {
                remove_action('fluent_support/ticket_created', $listener);
            }

            FsTest::assertHealthy($result, 'create');
            FsTest::assertSame(1, $fired, 'ticket_created fired');
            foreach (FsTest::sentMails() as $mail) {
                $to = isset($mail['to']) ? (array) $mail['to'] : [];
                FsTest::assert(!in_array($email, $to, true), 'the author must not be emailed');
            }
            FsTest::assertSame(
                true,
                apply_filters('fluent_support/should_send_notification', true, 'email', 'ticket_created_email_to_customer', Ticket::find($result['data']['ticket_id']), null),
                'the suppression is gone once the call ends'
            );
        });

        // ---------------------------------------------------------------- lock and transaction

        FsTest::case('a live lock answers 409 and creates nothing; the lock is left alone', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $moderatorAgentId, $adminId, $ticketCount, $lockName, $lockExists, $wpdb) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $metaKey = '_fcom_feed_' . $post->id;
            $wpdb->insert($wpdb->options, ['option_name' => $lockName($metaKey), 'option_value' => (string) time(), 'autoload' => 'no']);
            $before = $ticketCount();

            try {
                $runAs($moderatorAgentId);
                $result = $create(['type' => 'feed', 'id' => $post->id]);
                $runAs($adminId);

                FsTest::assertSame(409, $result['status'], 'status');
                FsTest::assertSame($before, $ticketCount(), 'no ticket');
                FsTest::assert($lockExists($metaKey), "another request's lock is not released");
            } finally {
                $wpdb->delete($wpdb->options, ['option_name' => $lockName($metaKey)]);
            }
        });

        FsTest::case('a lock older than 60 seconds is taken over and released', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $moderatorAgentId, $adminId, $lockName, $lockExists, $wpdb) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $metaKey = '_fcom_feed_' . $post->id;
            $wpdb->insert($wpdb->options, ['option_name' => $lockName($metaKey), 'option_value' => (string) (time() - 120), 'autoload' => 'no']);

            try {
                $runAs($moderatorAgentId);
                $result = $adopt($create(['type' => 'feed', 'id' => $post->id]));
                $runAs($adminId);

                FsTest::assertHealthy($result, 'create');
                FsTest::assert(!$lockExists($metaKey), 'lock released');
            } finally {
                $wpdb->delete($wpdb->options, ['option_name' => $lockName($metaKey)]);
            }
        });

        FsTest::case('a failure before the commit leaves no ticket, no meta and no lock, and the retry makes exactly one', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $moderatorAgentId, $adminId, $ticketCount, $metaCount, $lockExists) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $metaKey = '_fcom_feed_' . $post->id;
            $before = $ticketCount();

            // Fails once the ticket row is inserted, inside the transaction. A model event
            // cannot be unregistered, so the flag switches it off again.
            $failing = true;
            Ticket::created(function () use (&$failing) {
                if ($failing) {
                    throw new RuntimeException('forced failure after insert');
                }
            });

            try {
                $runAs($moderatorAgentId);
                $failed = $create(['type' => 'feed', 'id' => $post->id]);
            } finally {
                $failing = false;
            }

            FsTest::assert($failed['status'] >= 400, 'the call failed, status ' . $failed['status']);
            FsTest::assertSame($before, $ticketCount(), 'no ticket after the failure');
            FsTest::assertSame(0, $metaCount($metaKey), 'no meta after the failure');
            FsTest::assert(!$lockExists($metaKey), 'lock released after the failure');

            $retry = $adopt($create(['type' => 'feed', 'id' => $post->id]));
            $runAs($adminId);

            FsTest::assertHealthy($retry, 'retry');
            FsTest::assertSame($before + 1, $ticketCount(), 'exactly one ticket');
            FsTest::assertSame(1, $metaCount($metaKey), 'one meta row');
        });

        FsTest::case('a failure after the commit keeps the one ticket and a retry returns it', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $moderatorAgentId, $adminId, $ticketCount, $metaCount) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $metaKey = '_fcom_feed_' . $post->id;
            $before = $ticketCount();

            $boom = function () {
                throw new RuntimeException('forced hook failure');
            };
            add_action('fluent_support/ticket_created', $boom);

            try {
                $runAs($moderatorAgentId);
                $failed = $create(['type' => 'feed', 'id' => $post->id]);
            } finally {
                remove_action('fluent_support/ticket_created', $boom);
            }

            FsTest::assert($failed['status'] >= 400, 'the call failed, status ' . $failed['status']);
            FsTest::assertSame($before + 1, $ticketCount(), 'the committed ticket stays');
            FsTest::assertSame(1, $metaCount($metaKey), 'its meta stays');

            $retry = $create(['type' => 'feed', 'id' => $post->id]);
            $runAs($adminId);
            $adopt($retry);

            FsTest::assertHealthy($retry, 'retry');
            FsTest::assertSame(true, $retry['data']['already_exists'], 'already_exists');
            FsTest::assertSame($before + 1, $ticketCount(), 'still one ticket');
        });

        // ---------------------------------------------------------------- menu script

        FsTest::case('the menu script is offered to a moderator agent only, and only in the portal', function () use ($makeUser, $runAs, $moderatorAgentId, $adminId) {
            $handle = 'fluent_support_community_ticket';
            $memberId = $makeUser('member');

            $runAs($moderatorAgentId);
            $portal = apply_filters('fluent_community/module_scripts', [], 'portal');
            $admin = apply_filters('fluent_community/module_scripts', [], 'admin');
            ob_start();
            do_action('fluent_community/portal_footer');
            $config = ob_get_clean();

            $runAs($memberId);
            $memberScripts = apply_filters('fluent_community/module_scripts', [], 'portal');
            ob_start();
            do_action('fluent_community/portal_footer');
            $memberConfig = ob_get_clean();
            $runAs($adminId);

            $url = isset($portal[$handle]['url']) ? $portal[$handle]['url'] : '';
            // Built by Vite like the other admin scripts; no query string, FluentCommunity appends ?ver=.
            FsTest::assertSame(Vite::getEnqueuePath('admin/js/community_ticket_menu.js'), $url, 'script url');
            FsTest::assert(substr($url, -strlen('/community_ticket_menu.js')) === '/community_ticket_menu.js' && strpos($url, '?') === false, 'script url shape, got: ' . $url);
            FsTest::assert(!isset($admin[$handle]), 'not in the admin runtime');
            FsTest::assert(strpos($config, 'window.fsCommunityTicket') !== false && strpos($config, 'community') !== false, 'config printed for the moderator');
            FsTest::assert(!isset($memberScripts[$handle]), 'no script for a member');
            FsTest::assertSame('', $memberConfig, 'no config for a member');
        });

        FsTest::case('the menu config says whether the agent may create tickets in the default mailbox', function () use ($makeAgentUser, $manage, $setCommunityRole, $runAs, $moderatorAgentId, $adminId) {
            $default = \FluentSupport\App\Services\Helper::getDefaultMailBox();
            $restrictedId = $makeAgentUser($manage, [$default->id]);
            $setCommunityRole($restrictedId, 'moderator');

            $config = function ($userId) use ($runAs) {
                $runAs($userId);
                ob_start();
                do_action('fluent_community/portal_footer');
                $html = ob_get_clean();
                preg_match('/window\.fsCommunityTicket = (\{.*\});/', $html, $match);

                return $match ? json_decode($match[1], true) : null;
            };

            $allowed = $config($moderatorAgentId);
            $restricted = $config($restrictedId);
            $runAs($adminId);

            FsTest::assertSame(true, $allowed['can_create'], 'moderator agent without restrictions');
            FsTest::assertSame(false, $restricted['can_create'], 'agent restricted from the default mailbox');
        });

        FsTest::case('an agent who manages only their own tickets gets no link to the unassigned ticket they created', function () use ($makeUser, $makeAgentUser, $setCommunityRole, $runAs, $xprofile, $makePost, $create, $adopt, $moderatorAgentId, $adminId) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $ownOnly = $makeAgentUser(['fst_view_tickets', 'fst_manage_own_tickets']);
            $setCommunityRole($ownOnly, 'moderator');

            $runAs($ownOnly);
            $result = $adopt($create(['type' => 'feed', 'id' => $post->id, 'notify' => false]));
            $runAs($adminId);

            FsTest::assertHealthy($result, 'create');
            $ticket = Ticket::find($result['data']['ticket_id']);
            FsTest::assert($ticket && !$ticket->agent_id, 'the ticket is unassigned');
            FsTest::assertSame(null, $result['data']['ticket_url'], 'no link to a ticket the agent may not open');
            FsTest::assertSame((string) $ticket->display_ticket_number, $result['data']['ticket_number'], 'the number is still returned');

            $runAs($moderatorAgentId);
            $full = $create(['type' => 'feed', 'id' => $post->id]);
            $runAs($adminId);
            FsTest::assert(!empty($full['data']['ticket_url']), 'an agent who may open it gets the link');
        });

        // ---------------------------------------------------------------- ticket data on posts and comments

        FsTest::case('feed and comment responses carry the ticket for a moderator agent, not for a member', function () use ($makeUser, $runAs, $xprofile, $makePost, $makeComment, $create, $adopt, $moderatorAgentId, $adminId) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $comment = $makeComment($author, $post->id);
            $other = $makeComment($author, $post->id);

            $runAs($moderatorAgentId);
            $postTicket = $adopt($create(['type' => 'feed', 'id' => $post->id, 'notify' => false]));
            $commentTicket = $adopt($create(['type' => 'comment', 'id' => $comment->id, 'notify' => false]));

            $feeds = \FluentCommunity\App\Models\Feed::whereIn('id', [$post->id])->with('comments')->get();
            $list = apply_filters('fluent_community/feeds_api_response', ['feeds' => ['data' => $feeds], 'sticky' => null], []);
            $bookmarks = apply_filters('fluent_community/bookmarks_api_response', ['feeds' => ['data' => \FluentCommunity\App\Models\Feed::whereIn('id', [$post->id])->get()]], []);
            $single = apply_filters('fluent_community/feed_api_response', ['feed' => \FluentCommunity\App\Models\Feed::find($post->id)], []);
            $comments = apply_filters('fluent_community/comments_api_response', ['comments' => \FluentCommunity\App\Models\Comment::whereIn('id', [$comment->id, $other->id])->get()], []);
            $one = apply_filters('fluent_community/comment_api_response', ['comment' => \FluentCommunity\App\Models\Comment::find($comment->id)], []);
            $runAs($adminId);

            $postData = $list['feeds']['data']->first()->toArray();
            $expectPost = ['id' => $postTicket['data']['ticket_id'], 'number' => $postTicket['data']['ticket_number'], 'url' => $postTicket['data']['ticket_url']];
            FsTest::assertSame($expectPost, $postData['fluent_support_ticket'], 'feed list: the post');
            $embedded = [];
            foreach ($postData['comments'] as $row) {
                $embedded[$row['id']] = isset($row['fluent_support_ticket']) ? $row['fluent_support_ticket']['id'] : null;
            }
            FsTest::assertSame($commentTicket['data']['ticket_id'], $embedded[$comment->id], 'feed list: the embedded comment');
            FsTest::assertSame(null, $embedded[$other->id], 'feed list: a comment without a ticket');
            FsTest::assertSame($expectPost, $single['feed']->toArray()['fluent_support_ticket'], 'single post');
            FsTest::assertSame($expectPost, $bookmarks['feeds']['data']->first()->toArray()['fluent_support_ticket'], 'bookmarks list');
            $byId = [];
            foreach ($comments['comments'] as $row) {
                $byId[$row->id] = $row->toArray();
            }
            FsTest::assertSame($commentTicket['data']['ticket_number'], $byId[$comment->id]['fluent_support_ticket']['number'], 'comment list');
            FsTest::assert(!isset($byId[$other->id]['fluent_support_ticket']), 'comment list: no ticket, no field');
            FsTest::assertSame($commentTicket['data']['ticket_url'], $one['comment']->toArray()['fluent_support_ticket']['url'], 'single comment');

            $memberId = $makeUser('member');
            $runAs($memberId);
            $memberFeed = apply_filters('fluent_community/feed_api_response', ['feed' => \FluentCommunity\App\Models\Feed::find($post->id)], []);
            $runAs($adminId);
            FsTest::assert(!array_key_exists('fluent_support_ticket', $memberFeed['feed']->toArray()), 'a member gets no ticket data');
        });

        FsTest::case('a restricted agent only learns that a ticket exists, and the lookup does not grow with the page', function () use ($makeUser, $makeAgentUser, $manage, $setCommunityRole, $runAs, $xprofile, $makePost, $create, $adopt, $box, $moderatorAgentId, $adminId, $wpdb) {
            $author = $makeUser('author');
            $xprofile($author);
            $posts = [$makePost($author), $makePost($author), $makePost($author)];

            $runAs($moderatorAgentId);
            foreach ($posts as $post) {
                $adopt($create(['type' => 'feed', 'id' => $post->id, 'notify' => false]));
            }
            $runAs($adminId);

            $restrictedId = $makeAgentUser($manage, [$box->id]);
            $setCommunityRole($restrictedId, 'moderator');
            $runAs($restrictedId);
            $restricted = apply_filters('fluent_community/feed_api_response', ['feed' => \FluentCommunity\App\Models\Feed::find($posts[0]->id)], []);

            $runAs($moderatorAgentId);
            $queries = function (array $ids) use ($wpdb) {
                $feeds = \FluentCommunity\App\Models\Feed::whereIn('id', $ids)->get();
                $before = $wpdb->num_queries;
                apply_filters('fluent_community/feeds_api_response', ['feeds' => ['data' => $feeds], 'sticky' => null], []);
                return $wpdb->num_queries - $before;
            };
            $queries([$posts[0]->id]);
            $one = $queries([$posts[0]->id]);
            $three = $queries([$posts[0]->id, $posts[1]->id, $posts[2]->id]);
            $runAs($adminId);

            FsTest::assertSame(['exists' => true], $restricted['feed']->toArray()['fluent_support_ticket'], 'no id, number or url');
            FsTest::assertSame($one, $three, 'same query count for one post and three, got ' . $one . ' and ' . $three);
        });

        FsTest::case('a deleted ticket no longer shows on its post', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $moderatorAgentId, $adminId) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);

            $runAs($moderatorAgentId);
            $result = $create(['type' => 'feed', 'id' => $post->id, 'notify' => false]);
            $runAs($adminId);
            Ticket::find($result['data']['ticket_id'])->delete();

            $runAs($moderatorAgentId);
            $feed = apply_filters('fluent_community/feed_api_response', ['feed' => \FluentCommunity\App\Models\Feed::find($post->id)], []);
            $runAs($adminId);

            FsTest::assert(!array_key_exists('fluent_support_ticket', $feed['feed']->toArray()), 'no ticket data after the ticket is deleted');
        });

        // ---------------------------------------------------------------- business setting

        FsTest::case('with the Business Settings switch off nothing is offered and the endpoint refuses', function () use ($makeUser, $runAs, $xprofile, $makePost, $create, $adopt, $setEnabled, $moderatorAgentId, $adminId, $ticketCount) {
            $author = $makeUser('author');
            $xprofile($author);
            $post = $makePost($author);
            $ticketed = $makePost($author);

            $runAs($moderatorAgentId);
            $adopt($create(['type' => 'feed', 'id' => $ticketed->id, 'notify' => false]));
            $before = $ticketCount();

            $setEnabled(false);
            try {
                $result = $create(['type' => 'feed', 'id' => $post->id]);
                $scripts = apply_filters('fluent_community/module_scripts', [], 'portal');
                ob_start();
                do_action('fluent_community/portal_footer');
                $config = ob_get_clean();
                $feed = apply_filters('fluent_community/feed_api_response', ['feed' => \FluentCommunity\App\Models\Feed::find($ticketed->id)], []);
            } finally {
                $setEnabled(true);
                $runAs($adminId);
            }

            FsTest::assertSame(403, $result['status'], 'endpoint refuses');
            FsTest::assertSame($before, $ticketCount(), 'no ticket');
            FsTest::assert(!isset($scripts['fluent_support_community_ticket']), 'no menu script');
            FsTest::assert(strpos($config, 'fsCommunityTicket') === false, 'no config');
            FsTest::assert(!array_key_exists('fluent_support_ticket', $feed['feed']->toArray()), 'no ticket data on posts');
            FsTest::assertSame(true, \FluentSupport\App\Modules\CommunityTicket\CommunityTicketPolicy::isEnabled(), 'switched back on');
        });

        FsTest::case('the switch is a Business Settings field, off by default', function () {
            $settings = new \FluentSupport\App\Services\EmailNotification\Settings();
            $fields = $settings->get('global_business_settings')['fields'];
            FsTest::assert(isset($fields['enable_community_tickets']) && $fields['enable_community_tickets']['type'] === 'inline-checkbox', 'field listed while FluentCommunity is active');

            $saved = \FluentSupport\App\Services\Helper::getOption('global_business_settings', []);
            \FluentSupport\App\Services\Helper::updateOption('global_business_settings', array_diff_key((array) $saved, ['enable_community_tickets' => 1]));
            try {
                FsTest::assertSame('no', $settings->globalBusinessSettings(false)['enable_community_tickets'], 'default');
                FsTest::assertSame(false, \FluentSupport\App\Modules\CommunityTicket\CommunityTicketPolicy::isEnabled(), 'off when never saved');
            } finally {
                \FluentSupport\App\Services\Helper::updateOption('global_business_settings', $saved);
            }
        });

        // ---------------------------------------------------------------- source label

        FsTest::case('the advanced source filter lists fluent_community', function () {
            FsTest::assert(array_key_exists('fluent_community', Ticket::sourceFilterOptions()), 'source option');
        });
    } finally {
        \FluentSupport\App\Services\Helper::updateOption('global_business_settings', $originalBusiness);
        remove_filter('fluent_support/create_ticket_data', $routeToBox);
        $runAs($adminId);

        $lockLike = $wpdb->esc_like('_fcom_lock_fcom_') . '%';
        $wpdb->query($wpdb->prepare("DELETE FROM `$wpdb->options` WHERE option_name LIKE %s", $lockLike)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        // Customers the endpoint made for the fixture authors (their email carries the marker).
        foreach (Customer::where('email', 'like', '%' . $marker . '%')->get() as $customer) {
            FsFactory::adoptRestCustomer($customer);
        }

        // Tickets (and their meta) go with FsFactory::cleanup(); remove the FluentCommunity side.
        FsFactory::cleanup();
        \FluentCommunity\App\Models\Comment::whereIn('id', $fcRows['comments'])->delete();
        \FluentCommunity\App\Models\Feed::whereIn('id', $fcRows['feeds'])->delete();
        \FluentCommunity\App\Models\Space::whereIn('id', $fcRows['spaces'])->delete();
        \FluentCommunity\App\Models\XProfile::whereIn('id', $fcRows['xprofiles'])->delete();
        \FluentCommunity\App\Models\Meta::whereIn('id', $fcRows['metas'])->delete();

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        foreach ($users as $userId) {
            wp_delete_user($userId);
        }
    }
};
