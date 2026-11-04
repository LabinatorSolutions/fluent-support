<?php
/**
 * Reconciling a customer record with an account whose address has moved.
 *
 * Unverified email changes aren't followed, so EmailClaimService mails a
 * confirmation link to the claimed address; only its real owner can confirm.
 * These cases check that a confirmed link moves and merges, and that every
 * unconfirmed or replayed variation does nothing.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\EmailClaimService;

return function () {

    $marker  = FsFactory::marker();
    $adminId = get_current_user_id();

    $becomeUser = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
    };

    $makeWpUser = function ($slug, $email) use ($marker) {
        $userId = wp_insert_user([
            'user_login' => substr($marker . '-' . $slug, 0, 60),
            'user_email' => $email,
            'user_pass'  => wp_generate_password(20),
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create WP user: ' . $userId->get_error_message());
        }

        return (int) $userId;
    };

    $oldEmail       = $marker . '-claim-old@example.invalid';
    $newEmail       = $marker . '-claim-new@example.invalid';
    $bystanderEmail = $marker . '-claim-bystander@example.invalid';

    // Create accounts before the stray record, or user_register would adopt it.
    $claimantUserId  = $makeWpUser('claim-owner', $oldEmail);
    $bystanderUserId = $makeWpUser('claim-bystander', $bystanderEmail);

    $serialBase = 930000 + wp_rand(1, 60000);
    $usedToken  = '';
    $renameUserId = 0;
    $slashUserId  = 0;
    $aposUserId   = 0;

    try {
        $claimant = FsFactory::customer([
            'email'   => $oldEmail,
            'user_id' => $claimantUserId,
        ]);

        $ownTicket = FsFactory::ticket([
            'customer_id'   => $claimant->id,
            'title'         => 'Email claim own ticket',
            'privacy'       => 'private',
            'serial_number' => $serialBase,
        ]);

        // The unlinked record an inbound mail from the new address would create.
        $stray = FsFactory::customer([
            'email'   => $newEmail,
            'user_id' => null,
        ]);

        $strayTicket = FsFactory::ticket([
            'customer_id'   => $stray->id,
            'title'         => 'Email claim stray ticket',
            'privacy'       => 'private',
            'serial_number' => $serialBase + 1,
        ]);

        // One key only on the stray (must survive the merge), one on both (kept record wins).
        $stray->updateMeta('fs_claim_carried', 'from-stray');
        $stray->updateMeta('fs_claim_kept', 'from-stray');
        Customer::find($claimant->id)->updateMeta('fs_claim_kept', 'from-claimant');

        // The account holder moves their own address with no confirmation.
        $becomeUser($claimantUserId);
        wp_update_user(['ID' => $claimantUserId, 'user_email' => $newEmail]);
        $becomeUser($adminId);

        FsTest::case('an_unverified_change_is_offered_for_confirmation_but_moves_nothing', function () use (
            $becomeUser, $adminId, $claimantUserId, $claimant, $oldEmail, $newEmail
        ) {
            $becomeUser($claimantUserId);

            $stored = Customer::find($claimant->id);
            FsTest::assertSame($oldEmail, (string) $stored->email,
                'an unverified account email change must leave the support address where it is');

            $divergence = EmailClaimService::getDivergence();

            FsTest::assert(is_array($divergence),
                'the gap between the account address and the support address must be offered for confirmation');
            FsTest::assertSame($oldEmail, (string) $divergence['from'],
                'the notice must name the address support mail goes to now');
            FsTest::assertSame($newEmail, (string) $divergence['to'],
                'the notice must name the address the account now holds');

            $becomeUser($adminId);
        });

        FsTest::case('merging_carries_internal_notification_actors_across', function () use ($serialBase, $marker) {
            // The notification tables only exist once internal notifications are
            // enabled. Skip rather than create them.
            if (!\FluentSupport\App\Modules\Notifications\NotificationService::tablesExist()) {
                FsTest::skip('internal notification tables are not installed');
                return;
            }

            $email = $marker . '-notify-merge@example.invalid';

            $keep    = FsFactory::customer(['user_id' => null]);
            $absorbed = FsFactory::customer(['email' => $email, 'user_id' => null]);

            $ticket = FsFactory::ticket([
                'customer_id'   => $absorbed->id,
                'title'         => 'Notification actor merge ticket',
                'privacy'       => 'private',
                'serial_number' => $serialBase + 4,
            ]);

            $notification = \FluentSupport\App\Models\Notification::create([
                'actor_id'    => $absorbed->id,
                'object_id'   => $ticket->id,
                'object_type' => 'ticket',
                'action'      => 'fluent_support/test_actor_merge',
            ]);

            try {
                EmailClaimService::apply(Customer::find($keep->id), $email);

                FsTest::assert(!Customer::find($absorbed->id),
                    'the absorbed record must be gone, or this case is not testing a merge');

                $moved = \FluentSupport\App\Models\Notification::find($notification->id);

                FsTest::assert($moved !== null, 'the notification must survive the merge');

                if ($moved) {
                    FsTest::assertSame((int) $keep->id, (int) $moved->actor_id,
                        'a notification must not keep naming a person row that has been deleted');
                }
            } finally {
                \FluentSupport\App\Models\Notification::where('id', $notification->id)->delete();
            }
        });

        FsTest::case('an_agent_correcting_the_address_reissues_the_ticket_links', function () use (
            $becomeUser, $adminId, $marker, $serialBase
        ) {
            // A signed ticket link grants full access, so the old inbox must lose
            // it when the address is corrected.
            $becomeUser($adminId);

            $customer = FsFactory::customer(['user_id' => null]);

            $ticket = FsFactory::ticket([
                'customer_id'   => $customer->id,
                'title'         => 'Agent edit reissue ticket',
                'privacy'       => 'private',
                'serial_number' => $serialBase + 3,
            ]);

            $before = (string) Ticket::find($ticket->id)->hash;

            \FluentSupport\App\Modules\Customers\CustomerService::update(Customer::find($customer->id), [
                'email'      => $marker . '-agent-corrected@example.invalid',
                'first_name' => 'Corrected',
                'last_name'  => 'Customer',
            ]);

            $after = (string) Ticket::find($ticket->id)->hash;

            FsTest::assert($before !== '', 'the ticket must start with a hash for this case to mean anything');
            FsTest::assert($after !== $before,
                'an agent moving the contact address must reissue the links already sent to the previous inbox');
        });

        FsTest::case('a_name_only_edit_holds_the_address_even_when_the_account_email_needs_slashing', function () use (
            $becomeUser, $adminId, $marker, &$aposUserId
        ) {
            // wp_insert_user() slashes the previous address before profile_update.
            // Compared to the unslashed new one, an apostrophe address always
            // looks changed, so a name-only admin edit would move it.
            $account = "o'" . $marker . '@example.invalid';
            $held    = $marker . '-apos-held@example.invalid';

            $aposUserId = wp_insert_user([
                'user_login' => substr($marker . '-apos', 0, 60),
                'user_email' => $account,
                'user_pass'  => wp_generate_password(20),
                'role'       => 'subscriber',
            ]);

            if (is_wp_error($aposUserId)) {
                FsTest::fail('could not create an account with an apostrophe address: ' . $aposUserId->get_error_message());
                $aposUserId = 0;
                return;
            }

            $aposUserId = (int) $aposUserId;

            FsTest::assertSame($account, (string) get_user_by('ID', $aposUserId)->user_email,
                'the account must actually hold the apostrophe address, or this case proves nothing');

            $customer = FsFactory::customer(['email' => $held, 'user_id' => $aposUserId]);

            $becomeUser($adminId);
            wp_update_user(['ID' => $aposUserId, 'first_name' => 'RenamedOnly']);

            FsTest::assertSame($held, (string) Customer::find($customer->id)->email,
                'a name-only edit must hold the support address whatever characters the account address contains');
        });

        FsTest::case('a_name_reaches_the_customer_record_as_the_account_holds_it', function () use (
            $becomeUser, $adminId, $makeWpUser, $marker, &$slashUserId
        ) {
            // profile_update passes wp_insert_user()'s slashed data through, so it
            // must be unslashed before it is stored.
            $email = $marker . '-slash@example.invalid';
            $slashUserId = $makeWpUser('slash-owner', $email);

            $customer = FsFactory::customer(['email' => $email, 'user_id' => $slashUserId]);

            $becomeUser($slashUserId);
            wp_update_user([
                'ID'         => $slashUserId,
                'first_name' => wp_slash("O'Brien"),
                'last_name'  => wp_slash('D\'Angelo'),
            ]);

            $stored = Customer::find($customer->id);

            FsTest::assertSame("O'Brien", (string) $stored->first_name,
                'the name on the customer record must read exactly as WordPress stores it');
            FsTest::assertSame("D'Angelo", (string) $stored->last_name,
                'the surname on the customer record must read exactly as WordPress stores it');
            FsTest::assertSame(get_user_meta($slashUserId, 'first_name', true), (string) $stored->first_name,
                'the customer record and the WordPress account must agree on the name');

            $becomeUser($adminId);
        });

        FsTest::case('a_confirmation_link_survives_an_address_containing_the_field_separator', function () {
            // is_email() allows a pipe, and the token joins its fields on a pipe.
            // Unencoded, the token would be rejected for that customer.
            $odd = 'first|last@example.invalid';

            $token = EmailClaimService::buildToken(12, 34, 'plain@example.invalid', $odd, time() + 3600);

            $parse = new ReflectionMethod('FluentSupport\\App\\Services\\EmailClaimService', 'parseToken');
            $parse->setAccessible(true);
            $claim = $parse->invoke(null, $token);

            FsTest::assert(is_array($claim),
                'a token for an address containing the field separator must still parse');

            if (is_array($claim)) {
                FsTest::assertSame($odd, (string) $claim['to'],
                    'the address must come back exactly as it went in');
                FsTest::assertSame(12, (int) $claim['customer_id'], 'the record id must survive the round trip');
                FsTest::assertSame(34, (int) $claim['user_id'], 'the account id must survive the round trip');
            }
        });

        FsTest::case('creating_a_portal_ticket_does_not_move_the_held_address', function () use (
            $becomeUser, $adminId, $claimantUserId, $claimant, $oldEmail
        ) {
            // Portal ticket-create resolves the customer from the account's own,
            // unconfirmed email. It must not move the held address either.
            $becomeUser($claimantUserId);

            \FluentSupport\App\Modules\CustomerPortal\PortalService::findCustomer([], '127.0.0.1', true);

            $stored = Customer::find($claimant->id);

            FsTest::assertSame($oldEmail, (string) $stored->email,
                'intake must not move the contact address of a record that belongs to an account');

            $becomeUser($adminId);
        });

        FsTest::case('an_administrator_editing_only_a_name_does_not_move_the_address', function () use (
            $becomeUser, $adminId, $makeWpUser, $marker, &$renameUserId
        ) {
            // Own fixtures: with the shared ones the collision guard (the stray
            // holds that address) would refuse the move and pass vacuously.
            $before = $marker . '-rename-old@example.invalid';
            $after  = $marker . '-rename-new@example.invalid';

            $renameUserId = $makeWpUser('rename-owner', $before);

            $customer = FsFactory::customer([
                'email'      => $before,
                'user_id'    => $renameUserId,
                'first_name' => 'Original',
            ]);

            $becomeUser($renameUserId);
            wp_update_user(['ID' => $renameUserId, 'user_email' => $after]);

            FsTest::assertSame($before, (string) Customer::find($customer->id)->email,
                'the divergence must exist for this case to mean anything');

            // An admin rename must not bless an unverified address the account
            // already carries. The check compares against the update, not the account.
            $becomeUser($adminId);
            wp_update_user(['ID' => $renameUserId, 'first_name' => 'Renamed']);

            $stored = Customer::find($customer->id);

            FsTest::assertSame($before, (string) $stored->email,
                'an administrator who did not touch the email must not move the support address');
            FsTest::assertSame('Renamed', (string) $stored->first_name,
                'names must still sync, or this case would pass for the wrong reason');
        });

        FsTest::case('the_confirmation_email_is_sent_to_the_new_address_only', function () use (
            $becomeUser, $adminId, $claimantUserId, $oldEmail, $newEmail
        ) {
            $becomeUser($claimantUserId);

            $sent = [];

            $capture = function ($short, $atts) use (&$sent) {
                $sent[] = $atts;
                return true;
            };

            // The lab has no portal page, and the link needs one.
            $portalUrl = function () {
                return home_url('/support');
            };

            add_filter('pre_wp_mail', $capture, 10, 2);
            add_filter('fluent_support/portal_base_url', $portalUrl);

            $error = EmailClaimService::issue(EmailClaimService::getDivergence());

            remove_filter('fluent_support/portal_base_url', $portalUrl);
            remove_filter('pre_wp_mail', $capture, 10);

            FsTest::assertSame('', (string) $error, 'issuing the confirmation must not error');
            FsTest::assertSame(1, count($sent), 'exactly one confirmation email must be sent');

            if (!$sent) {
                return;
            }

            $recipients = (array) $sent[0]['to'];

            FsTest::assert(in_array($newEmail, $recipients, true),
                'the confirmation must go to the address being claimed, which is the inbox that proves control of it');
            FsTest::assert(!in_array($oldEmail, $recipients, true),
                'the confirmation must not go to the previous address; WordPress already notifies it of the account change');

            $becomeUser($adminId);
        });

        FsTest::case('a_ticket_changing_owner_is_reissued', function () use ($serialBase) {
            // Reparenting alone must reissue the hash. A merge also moves the
            // address, which rotates hashes anyway, so it can't prove this.
            // absorb() saves tickets one by one so this model event fires.
            $from = FsFactory::customer(['user_id' => null]);
            $to   = FsFactory::customer(['user_id' => null]);

            $ticket = FsFactory::ticket([
                'customer_id'   => $from->id,
                'title'         => 'Email claim reparent ticket',
                'privacy'       => 'private',
                'serial_number' => $serialBase + 2,
            ]);

            $before = (string) Ticket::find($ticket->id)->hash;

            $row = Ticket::find($ticket->id);
            $row->customer_id = $to->id;
            $row->save();

            $after = (string) Ticket::find($ticket->id)->hash;

            FsTest::assert($before !== '', 'the ticket must start with a hash for this case to mean anything');
            FsTest::assert($after !== $before,
                'a ticket moving to another customer must be reissued, or every link already mailed for it keeps working under its new owner');
        });

        FsTest::case('a_tampered_confirmation_link_is_refused', function () use (
            $becomeUser, $adminId, $claimantUserId, $claimant, $oldEmail, $newEmail
        ) {
            $becomeUser($claimantUserId);

            $token = EmailClaimService::buildToken(
                $claimant->id, $claimantUserId, $oldEmail, $newEmail, time() + 3600
            );

            // Flip the last signature byte after decoding. The last base64 char
            // can carry padding bits, so flipping it may change nothing.
            $raw  = base64_decode(strtr($token, '-_', '+/'), true);
            $last = substr($raw, -1);
            $raw  = substr($raw, 0, -1) . ($last === 'a' ? 'b' : 'a');

            $tampered = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

            FsTest::assert($tampered !== $token, 'the tampered token must actually differ from the original');

            $resolved = EmailClaimService::resolveClaim($tampered);

            FsTest::assertSame('invalid', $resolved['status'],
                'a token whose signature does not verify must be refused');

            $becomeUser($adminId);
        });

        FsTest::case('an_expired_confirmation_link_is_refused', function () use (
            $becomeUser, $adminId, $claimantUserId, $claimant, $oldEmail, $newEmail
        ) {
            $becomeUser($claimantUserId);

            $token = EmailClaimService::buildToken(
                $claimant->id, $claimantUserId, $oldEmail, $newEmail, time() - 10
            );

            $resolved = EmailClaimService::resolveClaim($token);

            FsTest::assertSame('expired', $resolved['status'],
                'a link past its expiry must be refused even though its signature is sound');

            $becomeUser($adminId);
        });

        FsTest::case('a_confirmation_link_does_nothing_for_another_signed_in_account', function () use (
            $becomeUser, $adminId, $claimantUserId, $bystanderUserId, $claimant, $oldEmail, $newEmail
        ) {
            // A forwarded link, or one opened by another signed-in user, must not move anything.
            $becomeUser($bystanderUserId);

            $token = EmailClaimService::buildToken(
                $claimant->id, $claimantUserId, $oldEmail, $newEmail, time() + 3600
            );

            $resolved = EmailClaimService::resolveClaim($token);

            FsTest::assertSame('wrong_account', $resolved['status'],
                'confirming must require being signed in as the account the link was issued to');

            $stored = Customer::find($claimant->id);
            FsTest::assertSame($oldEmail, (string) $stored->email,
                'a link opened by the wrong account must leave the support address alone');

            $becomeUser($adminId);
        });

        FsTest::case('no_claim_is_offered_while_another_account_holds_the_address', function () use (
            $becomeUser, $adminId, $claimantUserId, $bystanderUserId, $newEmail, $stray
        ) {
            // A confirmation can't settle two accounts disputing one address. An agent must.
            $strayRow = Customer::find($stray->id);
            $strayRow->user_id = $bystanderUserId;
            $strayRow->save();

            $becomeUser($claimantUserId);

            FsTest::assert(EmailClaimService::getDivergence() === null,
                'an address already held by another accounts record must not be offered for claiming');

            $token = EmailClaimService::buildToken(
                Customer::where('user_id', $claimantUserId)->first()->id,
                $claimantUserId,
                Customer::where('user_id', $claimantUserId)->first()->email,
                $newEmail,
                time() + 3600
            );

            FsTest::assertSame('conflict', EmailClaimService::resolveClaim($token)['status'],
                'a link into a contested address must be refused rather than creating a duplicate');

            $becomeUser($adminId);

            $strayRow = Customer::find($stray->id);
            $strayRow->user_id = null;
            $strayRow->save();
        });

        FsTest::case('confirming_moves_the_address_absorbs_the_stray_record_and_rotates_hashes', function () use (
            $becomeUser, $adminId, $claimantUserId, $claimant, $stray, $ownTicket, $strayTicket, $oldEmail, $newEmail, &$usedToken
        ) {
            $becomeUser($claimantUserId);

            $ownHashBefore   = (string) Ticket::find($ownTicket->id)->hash;
            $strayHashBefore = (string) Ticket::find($strayTicket->id)->hash;

            $usedToken = EmailClaimService::buildToken(
                $claimant->id, $claimantUserId, $oldEmail, $newEmail, time() + 3600
            );

            $resolved = EmailClaimService::resolveClaim($usedToken);

            FsTest::assertSame('ok', $resolved['status'], 'a sound link from the claiming account must resolve');

            $result = EmailClaimService::apply($resolved['customer'], $resolved['email']);

            $stored = Customer::find($claimant->id);
            FsTest::assertSame($newEmail, (string) $stored->email,
                'a confirmed address must become the address support writes to');

            FsTest::assertSame(1, (int) $result['merged'], 'the stray record must be absorbed');
            FsTest::assertSame(1, (int) $result['tickets'], 'the stray records ticket must move across');

            FsTest::assertSame((int) $claimant->id, (int) Ticket::find($strayTicket->id)->customer_id,
                'a ticket opened from the new address must end up on the customers own record');

            FsTest::assert(!Customer::find($stray->id),
                'an emptied stray record must not be left behind to match future inbound mail');

            // Links for the old address and for the stray's tickets must both die.
            FsTest::assert($ownHashBefore !== '' && (string) Ticket::find($ownTicket->id)->hash !== $ownHashBefore,
                'moving the contact address must rotate the hashes that authorise the signed public ticket view');
            FsTest::assert($strayHashBefore !== '' && (string) Ticket::find($strayTicket->id)->hash !== $strayHashBefore,
                'a ticket changing owner must be reissued, or links already mailed for it keep working under the new owner');

            $becomeUser($adminId);
        });

        FsTest::case('merging_carries_over_custom_data_the_surviving_record_lacks', function () use ($claimant) {
            $kept = Customer::find($claimant->id);

            FsTest::assertSame('from-stray', (string) $kept->getMeta('fs_claim_carried'),
                'data held only by the absorbed record must move across rather than disappear with it');
        });

        FsTest::case('merging_does_not_overwrite_custom_data_the_surviving_record_has', function () use ($claimant) {
            $kept = Customer::find($claimant->id);

            FsTest::assertSame('from-claimant', (string) $kept->getMeta('fs_claim_kept'),
                'a key the surviving record already holds must not be replaced by the absorbed one');
        });

        FsTest::case('merging_leaves_no_custom_data_pointing_at_the_removed_record', function () use ($stray) {
            $orphans = Meta::where('object_type', 'person_meta')
                ->where('object_id', $stray->id)
                ->count();

            FsTest::assertSame(0, (int) $orphans,
                'meta must not be left in fs_meta pointing at a person id that no longer exists');
        });

        FsTest::case('a_confirmation_link_is_inert_while_the_address_it_moved_stays_moved', function () use (
            $becomeUser, $adminId, $claimantUserId, $newEmail, &$usedToken
        ) {
            $becomeUser($claimantUserId);

            FsTest::assert(!empty($usedToken), 'the previous case must have produced a token for this one to mean anything');

            // The link binds the record's current address, so confirming retires it.
            // Nothing is stored: moving the record back before expiry revives it,
            // which only reconfirms the same inbox.
            $resolved = EmailClaimService::resolveClaim($usedToken);

            FsTest::assertSame('stale', $resolved['status'],
                'a link that has already been confirmed must not confirm anything a second time');

            $becomeUser($adminId);
        });

        FsTest::case('a_confirmation_link_dies_when_the_records_address_moves_again', function () use (
            $becomeUser, $adminId, $claimantUserId, $claimant, $newEmail, $marker
        ) {
            $becomeUser($claimantUserId);

            $laterEmail = $marker . '-claim-later@example.invalid';
            wp_update_user(['ID' => $claimantUserId, 'user_email' => $laterEmail]);

            // Issued against the address the record holds right now.
            $token = EmailClaimService::buildToken(
                $claimant->id, $claimantUserId, $newEmail, $laterEmail, time() + 3600
            );

            // An agent corrects the record before the customer gets to the link.
            $becomeUser($adminId);
            $row = Customer::find($claimant->id);
            $row->email = $marker . '-claim-corrected@example.invalid';
            $row->save();

            $becomeUser($claimantUserId);

            FsTest::assertSame('stale', EmailClaimService::resolveClaim($token)['status'],
                'a link must stop working once the record it describes no longer holds the address it was issued against');

            $becomeUser($adminId);
        });
    } finally {
        $becomeUser($adminId);

        // Person meta doesn't cascade and the factory doesn't track fs_meta.
        if (!empty($claimant) && !empty($stray)) {
            Meta::where('object_type', 'person_meta')
                ->whereIn('object_id', [$claimant->id, $stray->id])
                ->delete();
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ([$claimantUserId, $bystanderUserId, $renameUserId, $slashUserId, $aposUserId] as $userId) {
            if ($userId) {
                wp_delete_user($userId);
            }
        }
    }
};
