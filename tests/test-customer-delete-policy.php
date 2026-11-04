<?php
/**
 * Customer deletion must not be reachable with the sensitive-data read grant.
 *
 * fst_sensitive_data is a read grant, and deleting a customer cascades into all
 * of their tickets, conversations and attachments.
 *
 * @package Fluent_Support
 */

use FluentSupport\App\App;
use FluentSupport\App\Modules\Customers\CustomerPolicy;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\Framework\Http\Request\Request;

class CustomerDeletePolicyTest extends WP_UnitTestCase
{
    /**
     * @var CustomerPolicy
     */
    protected $policy;

    public function set_up()
    {
        parent::set_up();
        $this->policy = new CustomerPolicy();
    }

    /**
     * Build a subscriber-level user carrying only the sensitive-data permission.
     *
     * @return int
     */
    protected function makeSensitiveDataAgent()
    {
        $userId = self::factory()->user->create(['role' => 'subscriber']);

        PermissionManager::attachPermissions($userId, ['fst_sensitive_data']);

        return $userId;
    }

    /**
     * Switch the current user and refresh the static permission cache.
     *
     * @param int $userId
     * @return void
     */
    protected function loginAs($userId)
    {
        wp_set_current_user($userId);

        PermissionManager::currentUserPermissions(false);
    }

    /**
     * @return Request
     */
    protected function makeRequest()
    {
        return App::make('request');
    }

    public function test_sensitive_data_agent_can_still_read_customers()
    {
        $this->loginAs($this->makeSensitiveDataAgent());

        $this->assertTrue(
            $this->policy->verifyRequest($this->makeRequest()),
            'The control leg must keep working: fst_sensitive_data still grants read access.'
        );
    }

    public function test_sensitive_data_agent_cannot_delete_a_customer()
    {
        $this->loginAs($this->makeSensitiveDataAgent());

        $this->expectException(\Exception::class);

        $this->policy->delete($this->makeRequest());
    }

    public function test_sensitive_data_agent_cannot_bulk_delete_customers()
    {
        $this->loginAs($this->makeSensitiveDataAgent());

        $this->expectException(\Exception::class);

        $this->policy->bulkDelete($this->makeRequest());
    }

    /**
     * The agent passes the module check and is stopped by the administrator check.
     */
    public function test_sensitive_data_agent_is_stopped_by_the_administrator_check()
    {
        $this->loginAs($this->makeSensitiveDataAgent());

        $this->expectExceptionMessage('Only administrators can delete customers.');

        $this->policy->delete($this->makeRequest());
    }

    public function test_agent_with_no_permission_cannot_delete()
    {
        $this->loginAs(self::factory()->user->create(['role' => 'subscriber']));

        $this->expectException(\Exception::class);

        $this->policy->delete($this->makeRequest());
    }

    /**
     * Without fst_sensitive_data the module check stops the user first.
     */
    public function test_user_without_sensitive_data_is_stopped_by_the_module_boundary()
    {
        $this->loginAs(self::factory()->user->create(['role' => 'subscriber']));

        $this->expectExceptionMessage('You do not have permission to manage customers.');

        $this->policy->bulkDelete($this->makeRequest());
    }

    public function test_administrator_can_delete_customers()
    {
        $this->loginAs(self::factory()->user->create(['role' => 'administrator']));

        $this->assertTrue($this->policy->delete($this->makeRequest()));
        $this->assertTrue($this->policy->bulkDelete($this->makeRequest()));
    }

    /**
     * Opting out of the administrator requirement must open both delete routes.
     */
    public function test_filter_lets_a_sensitive_data_agent_delete_without_admin_rights()
    {
        $this->loginAs($this->makeSensitiveDataAgent());

        add_filter('fluent_support/customer_delete_requires_admin', '__return_false');

        try {
            $this->assertTrue($this->policy->delete($this->makeRequest()));
            $this->assertTrue($this->policy->bulkDelete($this->makeRequest()));
        } finally {
            remove_filter('fluent_support/customer_delete_requires_admin', '__return_false');
        }
    }

    /**
     * The filter drops only the administrator check, never the module boundary.
     */
    public function test_filter_does_not_open_deletion_to_users_without_sensitive_data()
    {
        $this->loginAs(self::factory()->user->create(['role' => 'subscriber']));

        add_filter('fluent_support/customer_delete_requires_admin', '__return_false');

        try {
            $this->expectExceptionMessage('You do not have permission to manage customers.');

            $this->policy->delete($this->makeRequest());
        } finally {
            remove_filter('fluent_support/customer_delete_requires_admin', '__return_false');
        }
    }

    /**
     * Agent-group routes use AdminSensitivePolicy and have a delete() action, so
     * the customer guard lives on its own policy and must not change theirs.
     */
    public function test_agent_group_delete_boundary_is_unchanged()
    {
        $this->loginAs($this->makeSensitiveDataAgent());

        $adminSensitive = new \FluentSupport\App\Http\Policies\AdminSensitivePolicy();

        $this->assertTrue($adminSensitive->verifyRequest($this->makeRequest()));
        $this->assertFalse(method_exists($adminSensitive, 'delete'));
    }
}
