<?php
/**
 * An agent creating a portal ticket on_behalf of a customer needs Sensitive Data,
 * Manage Other Tickets and Manage Settings, and may only file it into a mailbox
 * they can access. Site admins are never restricted.
 * The agent user and its restriction meta aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Hooks\Handlers\ReCaptchaHandler;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $marker = FsFactory::marker();

    $allowedBox = FsFactory::mailbox();
    $restrictedBox = FsFactory::mailbox();
    $agent = FsFactory::agent();

    $login = FsTest::uniq('fs-onbehalf-agent');
    $agentUserId = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(24, true, true),
        'user_email' => $login . '@example.test',
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($agentUserId)) {
        throw new RuntimeException('Could not create agent user: ' . $agentUserId->get_error_message());
    }

    $switchTo = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    // Posts a ticket on behalf of a fresh customer; returns [result, customer email].
    $createOnBehalf = function ($slug, $mailboxId) use ($marker) {
        $email = $marker . '-' . $slug . '@example.invalid';
        $params = [
            'title'     => 'On behalf ' . $slug . ' ' . $marker,
            'content'   => '<p>On behalf ticket ' . $marker . '</p>',
            'on_behalf' => ['email' => $email, 'first_name' => 'OnBehalf'],
        ];

        if ($mailboxId) {
            $params['mailbox_id'] = $mailboxId;
        }

        return [FsTest::rest('POST', '/customer-portal/tickets', $params), $email];
    };

    // Hand REST-created rows to the factory so cleanup removes them.
    $adopt = function ($email) {
        $customer = Customer::where('email', $email)->first();
        if ($customer) {
            foreach (Ticket::where('customer_id', $customer->id)->get() as $ticket) {
                FsFactory::adoptRestTicket($ticket);
            }
            FsFactory::adoptRestCustomer($customer);
        }
        return $customer;
    };

    $noAgentUserId = 0;
    $customerUserId = 0;

    try {
        $agent->user_id = $agentUserId;
        $agent->save();
        PermissionManager::attachPermissions($agentUserId, ['fst_sensitive_data', 'fst_manage_other_tickets', 'fst_manage_settings']);
        $agent->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        FsTest::case('portal on_behalf: an agent cannot file a ticket into a restricted mailbox', function () use ($switchTo, $agentUserId, $adminId, $createOnBehalf, $adopt, $restrictedBox) {
            $switchTo($agentUserId);
            try {
                [$result, $email] = $createOnBehalf('restricted', $restrictedBox->id);
            } finally {
                $switchTo($adminId);
            }

            FsTest::assertSame(403, (int) $result['status'], 'status');
            FsTest::assert(strpos($result['message'], 'access to this mailbox') !== false, 'message: ' . $result['message']);

            $customer = $adopt($email);
            FsTest::assert(!$customer, 'no customer record created for a refused request');
            FsTest::assertSame(0, (int) Ticket::where('mailbox_id', $restrictedBox->id)->count(), 'no ticket in the restricted mailbox');
        });

        FsTest::case('portal on_behalf: an agent can file a ticket into a mailbox they can access', function () use ($switchTo, $agentUserId, $adminId, $createOnBehalf, $adopt, $allowedBox) {
            $switchTo($agentUserId);
            try {
                [$result, $email] = $createOnBehalf('allowed', $allowedBox->id);
            } finally {
                $switchTo($adminId);
            }

            FsTest::assertHealthy($result, 'on_behalf ticket into allowed mailbox');

            $customer = $adopt($email);
            $ticket = $customer ? Ticket::where('customer_id', $customer->id)->first() : null;
            FsTest::assert($ticket && (int) $ticket->mailbox_id === (int) $allowedBox->id, 'ticket created in the allowed mailbox');
        });

        FsTest::case('portal on_behalf: an agent without Manage Settings is refused', function () use ($switchTo, $agentUserId, $adminId, $createOnBehalf, $adopt, $allowedBox) {
            PermissionManager::attachPermissions($agentUserId, ['fst_sensitive_data', 'fst_manage_other_tickets']);

            $switchTo($agentUserId);
            try {
                [$create, $email] = $createOnBehalf('nosettings', $allowedBox->id);
                $list = FsTest::rest('GET', '/customer-portal/tickets', ['on_behalf' => ['email' => $email]]);
            } finally {
                $switchTo($adminId);
                PermissionManager::attachPermissions($agentUserId, ['fst_sensitive_data', 'fst_manage_other_tickets', 'fst_manage_settings']);
            }

            FsTest::assert(in_array((int) $create['status'], [401, 403], true), 'ticket creation refused, got ' . $create['status']);
            FsTest::assert(!$adopt($email), 'no customer created');
            FsTest::assert(in_array((int) $list['status'], [401, 403], true), 'ticket list refused, got ' . $list['status']);
        });

        FsTest::case('portal on_behalf: permissions without an agent record are refused', function () use ($switchTo, $adminId, $createOnBehalf, $adopt, $allowedBox, &$noAgentUserId) {
            $login = FsTest::uniq('fs-onbehalf-noagent');
            $noAgentUserId = wp_insert_user([
                'user_login' => $login,
                'user_pass'  => wp_generate_password(24, true, true),
                'user_email' => $login . '@example.test',
                'role'       => 'subscriber',
            ]);
            FsTest::assert(!is_wp_error($noAgentUserId), 'user created');
            PermissionManager::attachPermissions($noAgentUserId, ['fst_sensitive_data', 'fst_manage_other_tickets', 'fst_manage_settings']);

            $switchTo($noAgentUserId);
            try {
                FsTest::assert(PermissionManager::userCan('fst_sensitive_data') && PermissionManager::userCan('fst_manage_other_tickets') && PermissionManager::userCan('fst_manage_settings'), 'user holds the on_behalf permissions');

                [$create, $email] = $createOnBehalf('noagent', $allowedBox->id);
                $list = FsTest::rest('GET', '/customer-portal/tickets', ['on_behalf' => ['email' => $email]]);
            } finally {
                $switchTo($adminId);
            }

            FsTest::assert(in_array((int) $create['status'], [401, 403], true), 'ticket creation refused, got ' . $create['status']);
            FsTest::assert(!$adopt($email), 'no customer created');
            FsTest::assert(in_array((int) $list['status'], [401, 403], true), 'ticket list refused, got ' . $list['status']);
        });

        FsTest::case('portal on_behalf: an agent cannot open a customer\'s ticket in a restricted mailbox', function () use ($switchTo, $agentUserId, $adminId, $restrictedBox, $allowedBox) {
            $customer = FsFactory::customer();
            $restricted = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $restrictedBox->id]);
            $allowed = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $allowedBox->id]);
            $onBehalf = ['email' => $customer->email];

            $switchTo($agentUserId);
            try {
                $refused = FsTest::rest('GET', '/customer-portal/tickets/' . $restricted->serial_number, ['on_behalf' => $onBehalf]);
                $opened = FsTest::rest('GET', '/customer-portal/tickets/' . $allowed->serial_number, ['on_behalf' => $onBehalf]);
            } finally {
                $switchTo($adminId);
            }

            // The same answer as an unknown ticket, so the agent can't tell it exists.
            FsTest::assertSame(404, (int) $refused['status'], 'restricted-mailbox ticket status');
            FsTest::assertHealthy($opened, 'ticket in an allowed mailbox opens');
        });

        FsTest::case('portal on_behalf: a site admin is not held to agent mailbox restrictions', function () use ($switchTo, $adminId, $createOnBehalf, $adopt, $restrictedBox) {
            $switchTo($adminId);

            [$result, $email] = $createOnBehalf('admin', $restrictedBox->id);
            FsTest::assertHealthy($result, 'admin on_behalf ticket');

            $customer = $adopt($email);
            $ticket = $customer ? Ticket::where('customer_id', $customer->id)->first() : null;
            FsTest::assert($ticket && (int) $ticket->mailbox_id === (int) $restrictedBox->id, 'admin ticket created in the requested mailbox');
        });

        // A remote portal files tickets over the API and can't answer a captcha. The
        // bypass rests on the on_behalf permission, never on a flag the client sends.
        $withTicketCaptcha = function (callable $run) {
            $existing = Meta::where('object_type', '_fs_recaptcha_settings')->first();
            $original = $existing ? $existing->value : null;
            $fixture = $existing ?: new Meta(['object_type' => '_fs_recaptcha_settings', 'key' => '_fs_recaptcha_settings']);
            $fixture->value = maybe_serialize([
                'is_enabled'              => 'true',
                'reCaptcha_version'       => 'recaptcha_v2',
                'siteKey'                 => 'fixture-site-key',
                'secretKey'               => 'fixture-secret-key',
                'formContainingReCaptcha' => ['ticket_form' => 'yes'],
            ]);
            $fixture->save();

            // Never call Google: any siteverify request fails.
            $blockHttp = function () {
                return new WP_Error('fs_test_blocked', 'blocked in tests');
            };
            add_filter('pre_http_request', $blockHttp, 1);

            try {
                FsTest::assert(ReCaptchaHandler::isRecaptchaApplicable('ticket_form'), 'captcha is on for the ticket form');
                return $run();
            } finally {
                remove_filter('pre_http_request', $blockHttp, 1);
                if ($existing) {
                    $fixture->value = $original;
                    $fixture->save();
                } else {
                    $fixture->delete();
                }
            }
        };

        FsTest::case('portal on_behalf: an agent API client is not asked for a captcha', function () use ($switchTo, $agentUserId, $adminId, $createOnBehalf, $adopt, $allowedBox, $withTicketCaptcha) {
            $switchTo($agentUserId);
            try {
                [$result, $email] = $withTicketCaptcha(function () use ($createOnBehalf, $allowedBox) {
                    return $createOnBehalf('captcha', $allowedBox->id);
                });
            } finally {
                $switchTo($adminId);
            }

            FsTest::assertHealthy($result, 'on_behalf ticket with captcha on');
            $customer = $adopt($email);
            FsTest::assert($customer && Ticket::where('customer_id', $customer->id)->exists(), 'ticket created');
        });

        FsTest::case('portal: a customer in the browser still needs the captcha', function () use ($switchTo, $adminId, $marker, $withTicketCaptcha, $adopt, &$customerUserId) {
            $email = $marker . '-captcha-customer@example.invalid';
            $customerUserId = wp_insert_user([
                'user_login' => FsTest::uniq('fs-captcha-customer'),
                'user_pass'  => wp_generate_password(24, true, true),
                'user_email' => $email,
                'role'       => 'subscriber',
            ]);
            FsTest::assert(!is_wp_error($customerUserId), 'user created');

            $switchTo($customerUserId);
            try {
                $result = $withTicketCaptcha(function () use ($marker) {
                    return FsTest::rest('POST', '/customer-portal/tickets', [
                        'title'   => 'Captcha customer ' . $marker,
                        'content' => '<p>No captcha ' . $marker . '</p>',
                    ]);
                });
            } finally {
                $switchTo($adminId);
            }

            FsTest::assertSame(422, (int) $result['status'], 'status');
            FsTest::assert(strpos($result['message'], 'recaptcha is not verified') !== false, 'message: ' . $result['message']);
            FsTest::assert(!$adopt($email), 'no customer or ticket created');
        });
    } finally {
        $switchTo($adminId);

        Meta::where('object_id', $agent->id)
            ->where('object_type', 'person_meta')
            ->where('key', 'agent_restrictions')
            ->delete();

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        foreach ([$agentUserId, $noAgentUserId, $customerUserId] as $userId) {
            if ($userId && get_user_by('ID', $userId)) {
                wp_delete_user($userId);
            }
        }
    }
};
