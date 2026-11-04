<?php
/**
 * Customer portal REST contract: the status code and the set of response keys of
 * every portal endpoint, for a logged-in customer, a stranger, a logged-out
 * visitor and an admin acting on_behalf of a customer.
 *
 * The portal SPA, the fluent-support-client plugin and Pro's Remote Portal read
 * these responses, so a refactor may change how they are built but not their
 * shape. A missing key breaks a client; an extra key sends data that no client
 * asked for (and may be private).
 *
 * The expected contract lives in fixtures/51-portal-contract.json as a sorted
 * list of key paths per call ("tickets.data[].title"). Values are not compared.
 * The contract assumes the lab plugin set (free + Pro active).
 *
 * To record it (first time, or after an intended change), delete the file and run:
 *   bash tests/bin/lab wp eval-file wp-content/plugins/fluent-support/tests/bin/run-integration.php \
 *     filter=51-portal-contract.php | sed -n '/^--- BEGIN 51-portal-contract.json ---$/,/^--- END/{//!p;}' \
 *     > tests/integration/fixtures/51-portal-contract.json
 * Then review the diff and commit it.
 *
 * Not covered here: ticket_file_upload (needs a real upload, see 68) and logout
 * (ends the run's session).
 */

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

return function () {

    $marker = FsFactory::marker();
    $adminId = get_current_user_id();
    $fixtureFile = __DIR__ . '/fixtures/51-portal-contract.json';

    $recorded = file_exists($fixtureFile)
        ? json_decode((string) file_get_contents($fixtureFile), true)
        : null;
    $isRecording = !is_array($recorded);
    $recording = [];

    /**
     * Key paths of a response, sorted. Lists are merged over all their items, so
     * an optional key on any item counts. Scalars and nulls end a path.
     *
     * @param mixed  $value
     * @param string $prefix
     * @return string[]
     */
    $pathsOf = function ($value, $prefix = '') use (&$pathsOf) {
        if (is_object($value)) {
            $value = json_decode(wp_json_encode($value), true);
        }

        if (!is_array($value) || $value === []) {
            return $prefix === '' ? [] : [$prefix];
        }

        $paths = [];
        $isList = array_keys($value) === range(0, count($value) - 1);

        foreach ($value as $key => $item) {
            $path = $isList
                ? $prefix . '[]'
                : ($prefix === '' ? (string) $key : $prefix . '.' . $key);
            $paths = array_merge($paths, $pathsOf($item, $path));
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    };

    /**
     * Drop a leaf path that another path continues ("x" next to "x.id"): a key that
     * is null on one item and an object on another is one key, not two.
     *
     * @param string[] $paths
     * @return string[]
     */
    $mergeNullLeaves = function ($paths) {
        return array_values(array_filter($paths, function ($path) use ($paths) {
            foreach ($paths as $other) {
                if (strpos($other, $path . '.') === 0 || strpos($other, $path . '[]') === 0) {
                    return false;
                }
            }
            return true;
        }));
    };

    /**
     * Compare one REST result with the recorded contract, or record it.
     *
     * @param string $label
     * @param array  $result FsTest::rest() result.
     * @return void
     */
    $assertContract = function ($label, $result) use ($pathsOf, $mergeNullLeaves, $isRecording, $recorded, &$recording) {
        FsTest::assert($result['db_error'] === '', $label . ': database error ' . $result['db_error']);

        $actual = [
            'status' => (int) $result['status'],
            'paths'  => $mergeNullLeaves($pathsOf($result['data'])),
        ];

        if ($isRecording) {
            $recording[$label] = $actual;
            return;
        }

        if (!isset($recorded[$label])) {
            FsTest::fail($label . ': not in the recorded contract. Actual: ' . wp_json_encode($actual));
            return;
        }

        $expected = $recorded[$label];

        FsTest::assertSame((int) $expected['status'], $actual['status'], $label . ': status');

        $missing = array_values(array_diff($expected['paths'], $actual['paths']));
        $extra = array_values(array_diff($actual['paths'], $expected['paths']));

        FsTest::assert(!$missing, $label . ': keys no longer sent: ' . implode(', ', $missing));
        FsTest::assert(!$extra, $label . ': keys not in the contract are now sent: ' . implode(', ', $extra));
    };

    $becomeUser = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
    };

    $makeWpUser = function ($slug) use ($marker) {
        $userId = wp_insert_user([
            'user_login' => substr($marker . '-' . $slug, 0, 60),
            'user_email' => $marker . '-' . $slug . '@example.invalid',
            'user_pass'  => wp_generate_password(20),
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create portal WP user: ' . $userId->get_error_message());
        }

        return (int) $userId;
    };

    $ownerUserId = $makeWpUser('contract-owner');
    $strangerUserId = $makeWpUser('contract-stranger');
    $feedbackConversationId = 0;
    $ratedConversationId = 0;

    try {
        $owner = FsFactory::customer([
            'email'   => $marker . '-contract-owner@example.invalid',
            'user_id' => $ownerUserId,
        ]);
        FsFactory::customer([
            'email'   => $marker . '-contract-stranger@example.invalid',
            'user_id' => $strangerUserId,
        ]);

        $agent = FsFactory::agent();
        $mailbox = FsFactory::mailbox();
        $product = FsFactory::product();

        // A ticket with an agent, a product, a customer reply, a rated agent reply and
        // an unrated one, so every optional block of the list and thread is present
        // whatever order the cases run in. The unrated reply takes the feedback call.
        $ticket = FsFactory::ticket([
            'customer_id' => $owner->id,
            'agent_id'    => $agent->id,
            'mailbox_id'  => $mailbox->id,
            'product_id'  => $product->id,
            'privacy'     => 'private',
            'status'      => 'active',
        ]);
        FsFactory::conversation($ticket, ['person_id' => $owner->id]);
        $ratedReply = FsFactory::conversation($ticket, ['person_id' => $agent->id]);
        $ratedConversationId = (int) $ratedReply->id;
        Meta::create([
            'object_type' => 'conversation_meta',
            'object_id'   => $ratedConversationId,
            'key'         => 'agent_feedback_ratings',
            'value'       => 'like',
        ]);
        $feedbackConversationId = (int) FsFactory::conversation($ticket, ['person_id' => $agent->id])->id;

        $serial = (int) Ticket::find($ticket->id)->serial_number;
        $onBehalf = ['email' => $owner->email];

        // Customer: read endpoints.

        FsTest::case('customer_public_options_contract', function () use ($becomeUser, $ownerUserId, $assertContract) {
            $becomeUser($ownerUserId);
            $assertContract('customer GET customer-portal/public_options', FsTest::rest('GET', 'customer-portal/public_options'));
        });

        FsTest::case('customer_custom_fields_contract', function () use ($becomeUser, $ownerUserId, $assertContract) {
            $becomeUser($ownerUserId);
            $assertContract('customer GET customer-portal/custom-fields-rendered', FsTest::rest('GET', 'customer-portal/custom-fields-rendered'));
        });

        FsTest::case('customer_me_with_portal_settings_contract', function () use ($becomeUser, $ownerUserId, $assertContract) {
            $becomeUser($ownerUserId);
            $assertContract('customer GET customer-portal/me', FsTest::rest('GET', 'customer-portal/me', ['with_portal_settings' => 1]));
        });

        FsTest::case('customer_countries_contract', function () use ($becomeUser, $ownerUserId, $assertContract) {
            $becomeUser($ownerUserId);
            $result = FsTest::rest('GET', 'options/countries');

            // The contract is "a map of country code to name", not the list of countries.
            $countries = $result['data']['countries'] ?? null;
            FsTest::assert(is_array($countries) && count($countries) > 100, 'countries: expected the full country map');
            if (is_array($countries)) {
                $result['data']['countries'] = ['*' => reset($countries)];
            }

            $assertContract('customer GET options/countries', $result);
        });

        FsTest::case('customer_ticket_list_contract', function () use ($becomeUser, $ownerUserId, $assertContract) {
            $becomeUser($ownerUserId);
            $assertContract('customer GET customer-portal/tickets', FsTest::rest('GET', 'customer-portal/tickets', ['filter_type' => 'all']));
        });

        FsTest::case('customer_ticket_view_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $serial) {
            $becomeUser($ownerUserId);
            $assertContract('customer GET customer-portal/tickets/{id}', FsTest::rest('GET', 'customer-portal/tickets/' . $serial));
        });

        // Customer: write endpoints.

        FsTest::case('customer_create_ticket_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $marker, $mailbox, $product) {
            $becomeUser($ownerUserId);

            $result = FsTest::rest('POST', 'customer-portal/tickets', [
                'title'      => 'Contract portal ticket ' . $marker,
                'content'    => '<p>Contract portal ticket body</p>',
                'mailbox_id' => $mailbox->id,
                'product_id' => $product->id,
            ]);

            $createdId = (int) ($result['data']['ticket']['id'] ?? 0);
            if ($createdId && ($created = Ticket::find($createdId))) {
                FsFactory::adoptRestTicket($created);
            }

            $assertContract('customer POST customer-portal/tickets', $result);
        });

        FsTest::case('customer_reply_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $serial) {
            $becomeUser($ownerUserId);
            $assertContract('customer POST customer-portal/tickets/{id}/responses', FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/responses', [
                'content' => '<p>Contract customer reply</p>',
            ]));
        });

        FsTest::case('customer_agent_feedback_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $serial, $feedbackConversationId) {
            $becomeUser($ownerUserId);
            $assertContract('customer POST customer-portal/tickets/{id}/agent-feedback', FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/agent-feedback', [
                'conversation_id' => $feedbackConversationId,
                'approval_status' => 'like',
            ]));
        });

        FsTest::case('customer_close_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $serial) {
            $becomeUser($ownerUserId);
            $assertContract('customer POST customer-portal/tickets/{id}/close', FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/close'));
        });

        // Runs between close and re-open: a closed ticket also sends who closed it.
        FsTest::case('customer_closed_ticket_view_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $serial) {
            $becomeUser($ownerUserId);
            $assertContract('customer GET customer-portal/tickets/{id} (closed)', FsTest::rest('GET', 'customer-portal/tickets/' . $serial));
        });

        FsTest::case('customer_reopen_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $serial) {
            $becomeUser($ownerUserId);
            $assertContract('customer POST customer-portal/tickets/{id}/re-open', FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/re-open'));
        });

        // Refusals: a refactor must keep the status codes and error envelopes.

        FsTest::case('stranger_ticket_view_refusal_contract', function () use ($becomeUser, $strangerUserId, $assertContract, $serial) {
            $becomeUser($strangerUserId);
            $assertContract('stranger GET customer-portal/tickets/{id}', FsTest::rest('GET', 'customer-portal/tickets/' . $serial));
        });

        FsTest::case('stranger_reply_refusal_contract', function () use ($becomeUser, $strangerUserId, $assertContract, $serial) {
            $becomeUser($strangerUserId);
            $assertContract('stranger POST customer-portal/tickets/{id}/responses', FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/responses', [
                'content' => '<p>Contract stranger reply</p>',
            ]));
        });

        FsTest::case('stranger_close_refusal_contract', function () use ($becomeUser, $strangerUserId, $assertContract, $serial) {
            $becomeUser($strangerUserId);
            $assertContract('stranger POST customer-portal/tickets/{id}/close', FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/close'));
        });

        FsTest::case('stranger_feedback_refusal_contract', function () use ($becomeUser, $strangerUserId, $assertContract, $serial, $feedbackConversationId) {
            $becomeUser($strangerUserId);
            $assertContract('stranger POST customer-portal/tickets/{id}/agent-feedback', FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/agent-feedback', [
                'conversation_id' => $feedbackConversationId,
                'approval_status' => 'like',
            ]));
        });

        // An unknown ticket number gets the same answer as a foreign one, so numbers can't be probed.
        $unknownSerial = $serial + 7000000;

        FsTest::case('unknown_ticket_view_refusal_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $unknownSerial) {
            $becomeUser($ownerUserId);
            $assertContract('customer GET customer-portal/tickets/{unknown}', FsTest::rest('GET', 'customer-portal/tickets/' . $unknownSerial));
        });

        FsTest::case('unknown_ticket_reply_refusal_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $unknownSerial) {
            $becomeUser($ownerUserId);
            $assertContract('customer POST customer-portal/tickets/{unknown}/responses', FsTest::rest('POST', 'customer-portal/tickets/' . $unknownSerial . '/responses', [
                'content' => '<p>Contract unknown reply</p>',
            ]));
        });

        FsTest::case('unknown_ticket_close_refusal_contract',function () use ($becomeUser, $ownerUserId, $assertContract, $unknownSerial) {
            $becomeUser($ownerUserId);
            $assertContract('customer POST customer-portal/tickets/{unknown}/close', FsTest::rest('POST', 'customer-portal/tickets/' . $unknownSerial . '/close'));
        });

        FsTest::case('unknown_ticket_feedback_refusal_contract', function () use ($becomeUser, $ownerUserId, $assertContract, $unknownSerial, $feedbackConversationId) {
            $becomeUser($ownerUserId);
            $assertContract('customer POST customer-portal/tickets/{unknown}/agent-feedback', FsTest::rest('POST', 'customer-portal/tickets/' . $unknownSerial . '/agent-feedback', [
                'conversation_id' => $feedbackConversationId,
                'approval_status' => 'like',
            ]));
        });

        FsTest::case('logged_out_ticket_list_refusal_contract', function () use ($becomeUser, $assertContract) {
            $becomeUser(0);
            $assertContract('logged-out GET customer-portal/tickets', FsTest::rest('GET', 'customer-portal/tickets'));
        });

        // An admin acting on_behalf of the customer (Remote Portal, fluent-support-client).

        FsTest::case('on_behalf_ticket_list_contract', function () use ($becomeUser, $adminId, $assertContract, $onBehalf) {
            $becomeUser($adminId);
            $assertContract('on_behalf GET customer-portal/tickets', FsTest::rest('GET', 'customer-portal/tickets', [
                'filter_type' => 'all',
                'on_behalf'   => $onBehalf,
            ]));
        });

        FsTest::case('on_behalf_ticket_view_contract', function () use ($becomeUser, $adminId, $assertContract, $onBehalf, $serial) {
            $becomeUser($adminId);
            $assertContract('on_behalf GET customer-portal/tickets/{id}', FsTest::rest('GET', 'customer-portal/tickets/' . $serial, [
                'on_behalf' => $onBehalf,
            ]));
        });

        FsTest::case('on_behalf_create_ticket_contract', function () use ($becomeUser, $adminId, $assertContract, $onBehalf, $marker, $mailbox, $product) {
            $becomeUser($adminId);

            $result = FsTest::rest('POST', 'customer-portal/tickets', [
                'title'      => 'Contract on_behalf ticket ' . $marker,
                'content'    => '<p>Contract on_behalf ticket body</p>',
                'mailbox_id' => $mailbox->id,
                'product_id' => $product->id,
                'on_behalf'  => $onBehalf,
            ]);

            $createdId = (int) ($result['data']['ticket']['id'] ?? 0);
            if ($createdId && ($created = Ticket::find($createdId))) {
                FsFactory::adoptRestTicket($created);
            }

            $assertContract('on_behalf POST customer-portal/tickets', $result);
        });

        FsTest::case('on_behalf_reply_contract', function () use ($becomeUser, $adminId, $assertContract, $onBehalf, $serial) {
            $becomeUser($adminId);
            $assertContract('on_behalf POST customer-portal/tickets/{id}/responses', FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/responses', [
                'content'   => '<p>Contract on_behalf reply</p>',
                'on_behalf' => $onBehalf,
            ]));
        });

        if ($isRecording) {
            // The lab mounts the plugin read-only, so print the contract for the host to save.
            FsTest::case('record_portal_contract', function () use (&$recording) {
                ksort($recording);

                WP_CLI::log('--- BEGIN 51-portal-contract.json ---');
                WP_CLI::log(wp_json_encode($recording, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                WP_CLI::log('--- END 51-portal-contract.json ---');

                FsTest::skip('no contract file; printed ' . count($recording) . ' calls, save them as fixtures/51-portal-contract.json');
            });
        }
    } finally {
        $becomeUser($adminId);

        $ratedIds = array_filter([$feedbackConversationId, $ratedConversationId]);
        if ($ratedIds) {
            Meta::where('object_type', 'conversation_meta')
                ->whereIn('object_id', $ratedIds)
                ->delete();
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($ownerUserId);
        wp_delete_user($strangerUserId);
    }
};
