<?php
/**
 * Closed-state transitions through the generic ticket property endpoint.
 *
 * PUT /tickets/{id}/property must refuse moving into or out of `closed`. Only
 * closeTicket()/reOpenTicket() set the closure fields and fire the close/reopen hooks.
 *
 * @package Fluent_Support
 */

use FluentSupport\App\Http\Controllers\TicketController;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Validator\ValidationException;

class TicketStatusTransitionTest extends WP_UnitTestCase
{
    /**
     * The router binds the dispatched WP_REST_Request as 'wprestrequest', and every
     * later framework Request merges its inputs. Drop it so params don't leak.
     */
    private function forgetRestRequestBinding()
    {
        $app = isset($GLOBALS['fluent_support_test_app']) ? $GLOBALS['fluent_support_test_app'] : null;

        if ($app && $app->bound('wprestrequest')) {
            $app->forgetInstance('wprestrequest');
        }
    }

    public function set_up()
    {
        parent::set_up();

        $this->forgetRestRequestBinding();
    }

    public function tear_down()
    {
        $this->forgetRestRequestBinding();

        parent::tear_down();
    }

    /**
     * Build a WP user plus its fs_agents row with exactly the given permissions.
     * Not an administrator, since admins bypass permission checks.
     */
    private function makeAgent(array $permissions)
    {
        $email = 'agent-' . wp_generate_password(8, false) . '@example.test';

        $userId = self::factory()->user->create([
            'role'       => 'subscriber',
            'user_email' => $email,
        ]);

        $agent = Agent::create([
            'first_name' => 'Test',
            'last_name'  => 'Agent',
            'email'      => $email,
            'user_id'    => $userId,
            'status'     => 'active',
        ]);

        PermissionManager::attachPermissions($userId, $permissions);

        return $agent;
    }

    /**
     * currentUserPermissions() caches in a static not keyed by user, so refresh it.
     */
    private function actAs(Agent $agent)
    {
        wp_set_current_user($agent->user_id);
        PermissionManager::currentUserPermissions(false);
    }

    private function makeTicket($status = 'active')
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'last_name'  => 'Customer',
            'email'      => 'customer-' . wp_generate_password(8, false) . '@example.test',
        ]);

        return Ticket::create([
            'customer_id' => $customer->id,
            'title'       => 'Status transition ticket',
            'content'     => 'body',
            'status'      => $status,
        ]);
    }

    private function makeRequest(array $post = [])
    {
        return new \FluentSupport\Framework\Http\Request\Request(
            $GLOBALS['fluent_support_test_app'], [], $post
        );
    }

    /**
     * updateTicketProperty() reports failures by throwing a ValidationException.
     *
     * @return string The refusal message, or '' when the call was allowed through.
     */
    private function setPropertyExpectingRefusal($ticketId, $propName, $propValue)
    {
        $request = $this->makeRequest([
            'prop_name'  => $propName,
            'prop_value' => $propValue,
        ]);

        try {
            (new TicketController())->updateTicketProperty($request, $ticketId);
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return isset($errors['message']) ? $errors['message'] : $e->getMessage();
        }

        return '';
    }

    private function setProperty($ticketId, $propName, $propValue)
    {
        $request = $this->makeRequest([
            'prop_name'  => $propName,
            'prop_value' => $propValue,
        ]);

        return (new TicketController())->updateTicketProperty($request, $ticketId);
    }

    private function conversationCount($ticketId)
    {
        return Conversation::where('ticket_id', $ticketId)->count();
    }

    /* ---------------------------------------------------------------------
     * Closing and reopening through the generic property route
     * ------------------------------------------------------------------ */

    public function test_property_route_cannot_close_a_ticket()
    {
        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $this->actAs($agent);

        $ticket = $this->makeTicket('active');
        $notesBefore = $this->conversationCount($ticket->id);
        $closedHooksBefore = did_action('fluent_support/ticket_closed');

        $message = $this->setPropertyExpectingRefusal($ticket->id, 'status', 'closed');

        $this->assertStringContainsString(
            'dedicated close and re-open actions',
            $message,
            'Closing through the property route must be refused and point at closeTicket().'
        );

        $ticket = Ticket::find($ticket->id);

        // Check the stored row. Without the guard it is saved closed before the response.
        $this->assertSame('active', $ticket->status, 'The ticket must not have been closed.');
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_by);
        $this->assertNull($ticket->total_close_time);
        $this->assertSame($notesBefore, $this->conversationCount($ticket->id), 'No internal note may be written for a refused transition.');
        $this->assertSame($closedHooksBefore, did_action('fluent_support/ticket_closed'));
    }

    public function test_property_route_cannot_reopen_a_closed_ticket()
    {
        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $this->actAs($agent);

        $ticket = (new TicketService())->close($this->makeTicket('active'), $agent);
        $resolvedAt = $ticket->resolved_at;
        $reopenHooksBefore = did_action('fluent_support/ticket_reopen');

        $message = $this->setPropertyExpectingRefusal($ticket->id, 'status', 'active');

        $this->assertStringContainsString('dedicated close and re-open actions', $message);

        $ticket = Ticket::find($ticket->id);

        $this->assertSame('closed', $ticket->status, 'The ticket must not have been reopened.');
        $this->assertSame($resolvedAt, $ticket->resolved_at, 'resolved_at must not be left behind on a reopened ticket.');
        $this->assertSame($reopenHooksBefore, did_action('fluent_support/ticket_reopen'));
    }

    /**
     * The guard must cover closed -> new, not just closed -> active.
     */
    public function test_property_route_cannot_move_a_closed_ticket_to_new()
    {
        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $this->actAs($agent);

        $ticket = (new TicketService())->close($this->makeTicket('active'), $agent);

        $message = $this->setPropertyExpectingRefusal($ticket->id, 'status', 'new');

        $this->assertStringContainsString('dedicated close and re-open actions', $message);
        $this->assertSame('closed', Ticket::find($ticket->id)->status);
    }

    /* ---------------------------------------------------------------------
     * Control: ViewTicket.vue only uses this route for new <-> active.
     * ------------------------------------------------------------------ */

    public function test_property_route_still_allows_new_to_active()
    {
        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $this->actAs($agent);

        $ticket = $this->makeTicket('new');
        $notesBefore = $this->conversationCount($ticket->id);

        $this->setProperty($ticket->id, 'status', 'active');

        $this->assertSame('active', Ticket::find($ticket->id)->status);
        $this->assertSame(
            $notesBefore + 1,
            $this->conversationCount($ticket->id),
            'The status change still records its internal note.'
        );
    }

    public function test_property_route_still_allows_active_to_new()
    {
        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $this->actAs($agent);

        $ticket = $this->makeTicket('active');

        $this->setProperty($ticket->id, 'status', 'new');

        $this->assertSame('new', Ticket::find($ticket->id)->status);
    }

    public function test_property_route_still_allows_non_status_properties()
    {
        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $this->actAs($agent);

        $ticket = $this->makeTicket('active');

        $this->setProperty($ticket->id, 'title', 'Renamed ticket');

        $this->assertSame('Renamed ticket', Ticket::find($ticket->id)->title);
    }

    /* ---------------------------------------------------------------------
     * The dedicated close/reopen routes set closure data and fire the hooks.
     * ------------------------------------------------------------------ */

    public function test_dedicated_close_records_closure_data_and_fires_the_hook()
    {
        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $this->actAs($agent);

        $ticket = $this->makeTicket('active');
        $hooksBefore = did_action('fluent_support/ticket_closed');

        // What the Close Ticket button sends. An absent value takes the silent
        // path and skips the hooks under test.
        $request = $this->makeRequest(['close_ticket_silently' => 'no']);

        (new TicketController())->closeTicket($request, $ticket->id);

        $ticket = Ticket::find($ticket->id);

        $this->assertSame('closed', $ticket->status);
        $this->assertNotEmpty($ticket->resolved_at, 'Reporting filters closed tickets by resolved_at.');
        $this->assertSame((int) $agent->id, (int) $ticket->closed_by);
        $this->assertNotNull($ticket->total_close_time);
        $this->assertSame($hooksBefore + 1, did_action('fluent_support/ticket_closed'));
    }

    public function test_dedicated_reopen_clears_resolved_at_and_fires_the_hook()
    {
        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $this->actAs($agent);

        $ticket = (new TicketService())->close($this->makeTicket('active'), $agent);
        $hooksBefore = did_action('fluent_support/ticket_reopen');

        (new TicketController())->reOpenTicket($ticket->id);

        $ticket = Ticket::find($ticket->id);

        $this->assertSame('active', $ticket->status);
        $this->assertNull($ticket->resolved_at, 'A reopened ticket must not stay marked as resolved.');
        $this->assertSame($hooksBefore + 1, did_action('fluent_support/ticket_reopen'));
    }
}
