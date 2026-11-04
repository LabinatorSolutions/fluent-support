<?php
/**
 * Agent authorization on the file upload endpoints: only agents with a write
 * capability may upload, and only to a ticket they can reach.
 *
 * @package Fluent_Support
 */

use FluentSupport\App\Http\Controllers\UploaderController;
use FluentSupport\App\Http\Policies\PortalPolicy;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

class AgentUploadAuthorizationTest extends WP_UnitTestCase
{
    /**
     * The router binds the dispatched WP_REST_Request as 'wprestrequest', and every
     * later framework Request merges its inputs. Drop it so ticket_id doesn't leak.
     */
    public function tear_down()
    {
        $app = isset($GLOBALS['fluent_support_test_app']) ? $GLOBALS['fluent_support_test_app'] : null;

        if ($app && $app->bound('wprestrequest')) {
            $app->forgetInstance('wprestrequest');
        }

        $_FILES = [];

        parent::tear_down();
    }

    /**
     * Build a WP user plus its fs_agents row with exactly the given permissions.
     * Not an administrator, since admins bypass permission checks.
     */
    private function makeAgent(array $permissions, $email = null)
    {
        $email = $email ?: 'agent-' . wp_generate_password(8, false) . '@example.test';

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

    private function makeTicket($mailboxId = null)
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'last_name'  => 'Customer',
            'email'      => 'customer-' . wp_generate_password(8, false) . '@example.test',
        ]);

        return Ticket::create([
            'customer_id' => $customer->id,
            'mailbox_id'  => $mailboxId,
            'title'       => 'Upload authorization ticket',
            'content'     => 'body',
            'status'      => 'new',
        ]);
    }

    /**
     * Uses the Application instance tests/bootstrap.php stashes.
     */
    private function makeRequest(array $post = [])
    {
        return new \FluentSupport\Framework\Http\Request\Request(
            $GLOBALS['fluent_support_test_app'], [], $post
        );
    }

    private function policyAllows()
    {
        return (new PortalPolicy())->uploadTicketFiles($this->makeRequest());
    }

    /* ---------------------------------------------------------------------
     * Policy gate — who may upload at all
     * ------------------------------------------------------------------ */

    public function test_view_only_agent_cannot_upload()
    {
        $this->actAs($this->makeAgent(['fst_view_tickets']));

        $this->assertFalse(
            $this->policyAllows(),
            'A read-only agent must not be able to upload attachments.'
        );
    }

    public function test_agent_without_any_ticket_permission_cannot_upload()
    {
        $this->actAs($this->makeAgent(['fst_view_all_reports']));

        $this->assertFalse(
            $this->policyAllows(),
            'A reports-only agent has no write capability and must be refused.'
        );
    }

    public function test_manage_agent_can_upload()
    {
        $this->actAs($this->makeAgent(['fst_manage_own_tickets']));

        $this->assertTrue($this->policyAllows());
    }

    public function test_draft_agent_can_upload()
    {
        $this->actAs($this->makeAgent(['fst_draft_reply']));

        $this->assertTrue(
            $this->policyAllows(),
            'Draft agents compose replies with attachments and must keep uploading.'
        );
    }

    public function test_settings_agent_can_upload()
    {
        $this->actAs($this->makeAgent(['fst_manage_settings']));

        $this->assertTrue(
            $this->policyAllows(),
            'The mailbox and saved-reply editors paste-upload through this endpoint.'
        );
    }

    public function test_saved_replies_agent_can_upload()
    {
        $this->actAs($this->makeAgent(['fst_manage_saved_replies']));

        $this->assertTrue($this->policyAllows());
    }

    /* ---------------------------------------------------------------------
     * Controller gate — which ticket the upload may target
     * ------------------------------------------------------------------ */

    /**
     * uploadImage() reaches checkTicketAccess() before it inspects the uploaded
     * file, so the guard can be exercised without a multipart fixture.
     */
    private function uploadImageResponse($ticketId)
    {
        $request = $this->makeRequest(['ticket_id' => $ticketId]);

        return (new UploaderController())->uploadImage($request);
    }

    private function responseMessage($response)
    {
        $data = $response instanceof \WP_REST_Response ? $response->get_data() : $response;

        return is_array($data) && isset($data['message']) ? $data['message'] : '';
    }

    public function test_upload_rejects_ticket_that_does_not_exist()
    {
        $this->actAs($this->makeAgent(['fst_manage_other_tickets']));

        $response = $this->uploadImageResponse(999999);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(403, $response->get_status());
        $this->assertStringContainsString(
            'permission to upload a file to this ticket',
            $this->responseMessage($response)
        );
    }

    public function test_upload_rejects_ticket_in_a_restricted_mailbox()
    {
        $ticket = $this->makeTicket(7);

        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $agent->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [7],
        ]);

        $this->actAs($agent);

        $response = $this->uploadImageResponse($ticket->id);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(403, $response->get_status());
    }

    public function test_upload_allows_a_ticket_the_agent_can_reach()
    {
        $ticket = $this->makeTicket();

        $this->actAs($this->makeAgent(['fst_manage_other_tickets']));

        $response = $this->uploadImageResponse($ticket->id);

        // No file, so this stops at image validation, past the access guard.
        $this->assertStringNotContainsString(
            'permission to upload a file to this ticket',
            $this->responseMessage($response)
        );
    }

    /* ---------------------------------------------------------------------
     * The same guard on uploadTicketFiles()
     * ------------------------------------------------------------------ */

    /**
     * uploadTicketFiles() needs a "file" part before it reaches the ticket guard.
     * The guard fires before anything is written.
     */
    private function uploadTicketFilesResponse($ticketId)
    {
        // Validation would reject this file (is_uploaded_file() is false outside a real
        // upload), so a missing guard surfaces as a ValidationException and fails the test.
        $tmp = wp_tempnam('fs-upload-guard');
        file_put_contents($tmp, 'fixture');

        $_FILES = [
            'file' => [
                'name'     => 'guard.txt',
                'type'     => 'text/plain',
                'tmp_name' => $tmp,
                'error'    => UPLOAD_ERR_OK,
                'size'     => 7,
            ],
        ];

        try {
            $request = $this->makeRequest(['ticket_id' => $ticketId]);

            return (new UploaderController())->uploadTicketFiles($request);
        } finally {
            $_FILES = [];
            @unlink($tmp);
        }
    }

    public function test_ticket_file_upload_rejects_ticket_that_does_not_exist()
    {
        $this->actAs($this->makeAgent(['fst_manage_other_tickets']));

        $response = $this->uploadTicketFilesResponse(999999);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(403, $response->get_status());
        $this->assertStringContainsString(
            'permission to upload a file to this ticket',
            $this->responseMessage($response)
        );
    }

    public function test_ticket_file_upload_rejects_restricted_mailbox()
    {
        $ticket = $this->makeTicket(9);

        $agent = $this->makeAgent(['fst_manage_other_tickets']);
        $agent->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [9],
        ]);

        $this->actAs($agent);

        $response = $this->uploadTicketFilesResponse($ticket->id);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(403, $response->get_status());
    }

    /* ---------------------------------------------------------------------
     * End to end through the registered REST route, proving the route's policy
     * actually dispatches to uploadTicketFiles().
     * ------------------------------------------------------------------ */

    private function dispatch($route, $ticketId = null)
    {
        do_action('rest_api_init', rest_get_server());

        $request = new \WP_REST_Request('POST', $route);

        if ($ticketId !== null) {
            $request->set_param('ticket_id', $ticketId);
        }

        return rest_do_request($request);
    }

    /**
     * @dataProvider uploadRoutes
     */
    public function test_route_denies_view_only_agent($route)
    {
        $this->actAs($this->makeAgent(['fst_view_tickets']));

        $response = $this->dispatch($route, 1);

        $this->assertTrue(
            $response->is_error(),
            "{$route} must refuse a read-only agent at the permission callback."
        );
        $this->assertContains($response->get_status(), [401, 403]);
    }

    /**
     * @dataProvider uploadRoutes
     */
    public function test_route_admits_manage_agent($route)
    {
        $this->actAs($this->makeAgent(['fst_manage_other_tickets']));

        $response = $this->dispatch($route, 1);

        // Fails later (no multipart body), but must pass the permission callback.
        $this->assertNotContains(
            $response->get_status(),
            [401, 403],
            "{$route} must not refuse an agent who can manage tickets."
        );
    }

    public function uploadRoutes()
    {
        return [
            'admin route'  => ['/fluent-support/v2/ticket_file_upload'],
            'portal route' => ['/fluent-support/v2/customer-portal/ticket_file_upload'],
        ];
    }

    public function test_upload_without_a_ticket_id_is_allowed()
    {
        $this->actAs($this->makeAgent(['fst_manage_other_tickets']));

        $response = $this->uploadImageResponse(0);

        // The Add Ticket form uploads before its ticket exists.
        $this->assertStringNotContainsString(
            'permission to upload a file to this ticket',
            $this->responseMessage($response)
        );
    }
}
