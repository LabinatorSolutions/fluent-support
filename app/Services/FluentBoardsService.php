<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Services\Tickets\ResponseService;
use Exception;
use FluentSupport\App\Models\Conversation;

class FluentBoardsService
{

    public function addInternalNote($task)
    {
        $urlBase = admin_url('admin.php?page=fluent-boards#/');
        $boardsProfileUrl = $urlBase . 'boards/' . $task->board_id . '/tasks/' . $task->id;

        $internalNote = sprintf(
            /* translators: an activity line shown after the agent's name ("Sarah created a Fluent Boards task for this ticket"); %s is the link to the task */
            __('created a Fluent Boards task for this ticket. %s', 'fluent-support'),
            '<a href="' . esc_url($boardsProfileUrl) . '">' . esc_html__('View task', 'fluent-support') . '</a>'
        );

        $agent = Helper::getAgentByUserId(get_current_user_id());

        ResponseService::addActivity($task->source_id, $agent, $internalNote);
    }

    public function addComment($task)
    {
        $baseUrl = admin_url('admin.php?page=fluent-support#/tickets/');
        $ticketUrl = $baseUrl . $task->source_id . '/view';

        $description = 'This task was created from Fluent Support. Here you can find this ticket: <a href="' . $ticketUrl . '" target="_blank">View Ticket</a>';

        $commentData = [
            'description' => $description,
            'created_by' => $task->created_by,
            'task_id' => $task->id,
            'board_id' => $task->board_id,
            'type' => 'comment'
        ];

        \FluentBoards\App\Models\Comment::create( $commentData);
    }
}
