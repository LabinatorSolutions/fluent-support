<?php

namespace FluentSupport\App\Modules\Customers;

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\Framework\Foundation\Policy;

/**
 * Policy for /customers: reads keep the module boundary, deletes are gated below.
 */
class CustomerPolicy extends Policy
{
    /**
     * @param  \FluentSupport\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return PermissionManager::currentUserCan('fst_sensitive_data');
    }

    public function delete(Request $request)
    {
        return $this->guardCustomerDeletion($request);
    }

    public function bulkDelete(Request $request)
    {
        return $this->guardCustomerDeletion($request);
    }

    /**
     * Deletion cascades into tickets, conversations and attachments.
     */
    protected function guardCustomerDeletion(Request $request)
    {
        // The router runs either the per-route method or verifyRequest(), not both,
        // so re-apply the module boundary. The exception code becomes the HTTP status.
        if (!$this->verifyRequest($request)) {
            throw new \Exception(
                esc_html__('You do not have permission to manage customers.', 'fluent-support'),
                is_user_logged_in() ? 403 : 401
            );
        }

        // manage_options can't be granted by the plugin's own permissions.
        // Sites can drop this requirement. The boundary above still applies.
        $requiresAdmin = apply_filters('fluent_support/customer_delete_requires_admin', true);

        if ($requiresAdmin && !current_user_can('manage_options')) {
            throw new \Exception(
                esc_html__('Only administrators can delete customers.', 'fluent-support'),
                403
            );
        }

        return true;
    }
}
