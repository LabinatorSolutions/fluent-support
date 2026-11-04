<?php

namespace FluentSupport\App\Modules\Notifications;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

/**
 * Turns ticket events into notifications. Most events tell the ticket's assigned
 * agent; an assignment tells the new assignee; a mention tells the mentioned
 * agents who can see the ticket.
 */
class NotificationListener
{
    public function register()
    {
        add_action('fluent_support/response_added_by_agent', [$this, 'agentReplied'], 20, 3);
        add_action('fluent_support/response_added_by_agent', [$this, 'mentioned'], 20, 3);
        add_action('fluent_support/note_added_by_agent', [$this, 'mentioned'], 20, 3);
        add_action('fluent_support/agent_assigned_to_ticket', [$this, 'assigned'], 15, 4);
        add_action('fluent_support/response_added_by_customer', [$this, 'customerReplied'], 20, 3);
        add_action('fluent_support/ticket_closed', [$this, 'closed'], 15, 2);
        add_action('fluent_support/ticket_reopen', [$this, 'reopened'], 15, 2);
        add_action('fluent_support/workflow_triggered', [$this, 'workflowTriggered'], 15, 4);
    }

    public function agentReplied($response, $ticket, $person)
    {
        $this->tellAssignee(NotificationService::AGENT_REPLIED, $ticket, $person, $response, ['agent' => $this->person($person)]);
    }

    public function customerReplied($response, $ticket, $person)
    {
        $this->tellAssignee(NotificationService::CUSTOMER_REPLIED, $ticket, $person, $response, ['customer' => $this->person($person)]);
    }

    public function closed($ticket, $person)
    {
        $this->tellAssignee(NotificationService::TICKET_CLOSED, $ticket, $person, null, ['status' => 'closed', 'actor' => $this->person($person)]);
    }

    public function reopened($ticket, $person)
    {
        $this->tellAssignee(NotificationService::TICKET_REOPENED, $ticket, $person, null, ['status' => 'reopened', 'actor' => $this->person($person)]);
    }

    public function mentioned($response, $ticket, $person)
    {
        $ids = NotificationService::mentionedAgentIds($response->content);
        $agents = $ids ? $this->canSee(Agent::whereIn('id', $ids)->get()->all(), $ticket) : [];

        if (!$agents) {
            return;
        }

        NotificationService::notify(NotificationService::AGENT_MENTIONED, [
            'actor_id'        => $person->id,
            'ticket_id'       => $ticket->id,
            'conversation_id' => $response->id,
            'payload'         => [
                'ticket_title'      => $ticket->title,
                'conversation_type' => $response->conversation_type,
                'mentioned_handles' => array_map(fn($agent) => trim("{$agent->first_name} {$agent->last_name}"), $agents),
                'content_preview'   => $this->preview($response->content)
            ]
        ], array_map(fn($agent) => $agent->id, $agents));
    }

    public function assigned($agent, $ticket, $assigner, $previousAgentId = null)
    {
        // Only while the ticket still has this agent (a queued assignment may be stale).
        if (empty($agent->id) || (int) $ticket->agent_id !== (int) $agent->id || !$this->canSee([$agent], $ticket)) {
            return;
        }

        $reassigned = (int) $previousAgentId > 0;

        NotificationService::notify($reassigned ? NotificationService::TICKET_REASSIGNED : NotificationService::TICKET_ASSIGNED, [
            'actor_id'  => !empty($assigner->id) ? (int) $assigner->id : null,
            'ticket_id' => $ticket->id,
            'payload'   => [
                'ticket_title'    => $ticket->title,
                'ticket_priority' => $ticket->priority,
                'assignment_type' => $reassigned ? 'reassignment' : 'assignment',
                'assigned_agent'  => $this->person($agent),
                'assigner'        => $this->person($assigner)
            ]
        ], [$agent->id]);
    }

    public function workflowTriggered($workflow, $ticket, $person = null, $context = [])
    {
        if (!$workflow || empty($ticket->id)) {
            return;
        }

        $context = is_array($context) ? $context : [];
        $name = !empty($workflow->title) ? $workflow->title : __('Workflow', 'fluent-support');
        $source = !empty($context['source']) ? sanitize_text_field($context['source']) : null;

        $this->tellAssignee(NotificationService::WORKFLOW_TRIGGERED, $ticket, $person, null, [
            'workflow_id'   => !empty($workflow->id) ? (int) $workflow->id : null,
            'workflow_name' => $name,
            'trigger_type'  => $workflow->trigger_type ?? null,
            'trigger_key'   => $workflow->trigger_key ?? null,
            /* translators: %s is the workflow name */
            'summary'       => sprintf($source === 'manual' ? __('Manual workflow "%s" was run', 'fluent-support') : __('Automation workflow "%s" was triggered', 'fluent-support'), $name),
            'actor'         => $this->person($person),
            'context'       => [
                'source' => $source,
                'event'  => !empty($context['event']) ? sanitize_text_field($context['event']) : null
            ]
        ]);
    }

    /**
     * Notify the ticket's assigned agent.
     */
    private function tellAssignee($event, $ticket, $actor, $response, array $payload)
    {
        if (!$ticket->agent_id || !Agent::where('id', $ticket->agent_id)->exists()) {
            return;
        }

        $payload = ['ticket_title' => $ticket->title, 'ticket_priority' => $ticket->priority] + $payload;
        if ($response) {
            $payload['conversation_type'] = $response->conversation_type;
            $payload['content_preview'] = $this->preview($response->content);
        }

        NotificationService::notify($event, [
            'actor_id'        => !empty($actor->id) ? (int) $actor->id : null,
            'ticket_id'       => $ticket->id,
            'conversation_id' => $response ? $response->id : null,
            'payload'         => $payload
        ], [$ticket->agent_id]);
    }

    /**
     * The agents who can open the ticket (mailbox restrictions and visibility).
     */
    private function canSee(array $agents, $ticket)
    {
        $restrictions = Meta::where('object_type', 'person_meta')->where('key', 'agent_restrictions')
            ->whereIn('object_id', array_map(fn($agent) => $agent->id, $agents))
            ->pluck('value', 'object_id')
            ->toArray();

        $access = new AgentTicketAccess();

        return array_values(array_filter($agents, function ($agent) use ($restrictions, $access, $ticket) {
            return $access->canAccess($agent, $ticket, isset($restrictions[$agent->id]) ? (Helper::safeUnserialize($restrictions[$agent->id]) ?: []) : []);
        }));
    }

    private function person($person)
    {
        if (!$person) {
            return null;
        }

        return [
            'id'          => (int) $person->id,
            'person_type' => $person->person_type,
            'name'        => $person->full_name ?: $person->email,
            'email'       => $person->email
        ];
    }

    private function preview($content)
    {
        return wp_html_excerpt(wp_strip_all_tags((string) $content), 180, '...');
    }
}
