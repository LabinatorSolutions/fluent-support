<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\Framework\Http\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * Draft-only agents (fst_draft_reply) can't perform mutating actions.
     *
     * @throws ApiException
     */
    protected function ensureCanManageTickets()
    {
        if (PermissionManager::canManageTickets()) {
            return;
        }

        // 422, as every caller's catch already answered; an ApiException keeps
        // its message when nothing catches it (framework 2.12 hides others).
        throw new ApiException(
            esc_html__('You do not have permission to perform this action.', 'fluent-support'),
            422
        );
    }

    /**
     * Enforces the agent's ticket visibility level.
     *
     * @param object $ticket
     * @throws ApiException
     */
    protected function ensureCanAccessTicket($ticket)
    {
        if (PermissionManager::canAccessTicket($ticket)) {
            return;
        }

        throw ApiException::forbidden(
            esc_html__('Sorry, You do not have permission to this ticket', 'fluent-support')
        );
    }
}
