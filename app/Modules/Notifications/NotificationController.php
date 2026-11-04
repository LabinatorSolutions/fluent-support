<?php

namespace FluentSupport\App\Modules\Notifications;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Http\Request\Request;

/**
 * The notification bell and page for the logged-in agent.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $agent = $this->agent();
        $perPage = min(max($request->getInt('per_page', 15), 1), 100);
        $query = NotificationService::query($agent->id, $this->filters($request));

        $page = $query ? $query->paginate($perPage)->toArray() : ['data' => [], 'total' => 0, 'per_page' => $perPage, 'current_page' => 1];
        $page['data'] = array_map([$this, 'format'], $page['data']);

        return $this->withMeta(['notifications' => $page, 'unread_count' => NotificationService::unreadCount($agent->id)]);
    }

    public function unread(Request $request)
    {
        $agent = $this->agent();
        $query = NotificationService::query($agent->id, ['status' => 'unread'] + $this->filters($request));
        $limit = min($request->getInt('limit') ?: 8, 20);

        return $this->withMeta([
            'notifications' => $query ? array_map([$this, 'format'], $query->limit($limit)->get()->toArray()) : [],
            'unread_count'  => NotificationService::unreadCount($agent->id)
        ]);
    }

    public function unreadCount(Request $request)
    {
        return ['count' => NotificationService::unreadCount($this->agent()->id, $this->filters($request))];
    }

    public function markRead($notification_id)
    {
        $agent = $this->agent();

        return [
            'updated'      => NotificationService::markRead($agent->id, (int) $notification_id),
            'unread_count' => NotificationService::unreadCount($agent->id)
        ];
    }

    public function markAllRead()
    {
        $agent = $this->agent();

        return [
            'updated'      => NotificationService::markRead($agent->id),
            'unread_count' => NotificationService::unreadCount($agent->id)
        ];
    }

    private function agent()
    {
        $agent = Helper::getAgentByUserId();

        if (!$agent) {
            throw new ApiException(__('Sorry, You do not have permission. Please add yourself as support agent first', 'fluent-support'));
        }

        return $agent;
    }

    private function filters(Request $request)
    {
        return array_filter($request->getSafe([
            'category'  => 'sanitize_text_field',
            'status'    => 'sanitize_text_field',
            'ticket_id' => 'intval',
        ]));
    }

    private function withMeta(array $response)
    {
        $categories = NotificationService::categories();

        return $response + ['meta' => ['categories' => $categories], 'notification_categories' => $categories];
    }

    /**
     * One notification as the bell shows it. Payload values are allowlisted and
     * sanitized; people carry no email.
     */
    public function format(array $n)
    {
        $event = NotificationService::LEGACY_EVENTS[$n['event_type'] ?? ''] ?? ($n['event_type'] ?? '');
        $raw = is_array($n['payload'] ?? null) ? $n['payload'] : [];

        $payload = [];
        foreach (['ticket_title', 'ticket_priority', 'conversation_type', 'content_preview', 'status', 'assignment_type', 'workflow_name', 'summary'] as $key) {
            if (array_key_exists($key, $raw)) {
                $payload[$key] = sanitize_text_field($raw[$key]);
            }
        }
        foreach (['customer', 'agent', 'actor', 'assigner', 'assigned_agent'] as $key) {
            if (!empty($raw[$key]) && is_array($raw[$key])) {
                $payload[$key] = array_filter([
                    'id'          => !empty($raw[$key]['id']) ? (int) $raw[$key]['id'] : null,
                    'person_type' => !empty($raw[$key]['person_type']) ? sanitize_text_field($raw[$key]['person_type']) : null,
                    'name'        => !empty($raw[$key]['name']) ? sanitize_text_field($raw[$key]['name']) : null,
                    'photo'       => !empty($raw[$key]['photo']) ? esc_url_raw($raw[$key]['photo']) : null,
                ]);
            }
        }
        if (!empty($raw['mentioned_handles']) && is_array($raw['mentioned_handles'])) {
            $payload['mentioned_handles'] = array_values(array_map('sanitize_text_field', $raw['mentioned_handles']));
        }

        $actor = !empty($n['actor']) ? ['id' => (int) $n['actor']['id'], 'full_name' => sanitize_text_field($n['actor']['full_name'] ?? ''), 'photo' => esc_url_raw($n['actor']['photo'] ?? '')] : null;
        $ticket = !empty($n['ticket']) ? ['id' => (int) $n['ticket']['id'], 'title' => sanitize_text_field($n['ticket']['title'] ?? '')] : null;

        $actorName = ($actor['full_name'] ?? '') ?: ($payload['customer']['name'] ?? $payload['agent']['name'] ?? $payload['actor']['name'] ?? $payload['assigner']['name'] ?? __('Someone', 'fluent-support'));
        $title = ($ticket['title'] ?? '') ?: ($payload['ticket_title'] ?? '');
        list($label, $inTicket, $plain) = $this->wording($event);

        return [
            'id'              => (int) ($n['id'] ?? 0),
            'actor_id'        => !empty($n['actor_id']) ? (int) $n['actor_id'] : null,
            'ticket_id'       => !empty($n['ticket_id']) ? (int) $n['ticket_id'] : null,
            'conversation_id' => !empty($n['conversation_id']) ? (int) $n['conversation_id'] : null,
            'event_type'      => $n['event_type'] ?? '',
            'category'        => $n['category'] ?? '',
            'payload'         => $payload,
            'actor'           => $actor,
            'ticket'          => $ticket,
            'recipients'      => array_map(fn($r) => [
                'id'      => !empty($r['id']) ? (int) $r['id'] : null,
                'user_id' => !empty($r['user_id']) ? (int) $r['user_id'] : null,
                'is_read' => !empty($r['is_read']) ? 1 : 0,
                'read_at' => $r['read_at'] ?? null,
            ], (array) ($n['recipients'] ?? [])),
            'created_at'      => $n['created_at'] ?? null,
            'updated_at'      => $n['updated_at'] ?? null,
            'event_label'     => $label,
            'event_icon'      => NotificationService::EVENTS[$event][2] ?? 'reply',
            'summary'         => $title ? sprintf($inTicket, $actorName, $title) : sprintf($plain, $actorName),
        ];
    }

    /**
     * [label, summary with a ticket title, summary without one] for an event.
     */
    private function wording($event)
    {
        switch ($event) {
            case NotificationService::AGENT_MENTIONED:
                /* translators: 1: who, 2: ticket title */
                return [__('mentioned you', 'fluent-support'), __('%1$s mentioned you in %2$s', 'fluent-support'), /* translators: %1$s: who */ __('%1$s mentioned you', 'fluent-support')];
            case NotificationService::AGENT_REPLIED:
            case NotificationService::CUSTOMER_REPLIED:
                /* translators: 1: who, 2: ticket title */
                return [__('replied to a ticket', 'fluent-support'), __('%1$s replied to a ticket in %2$s', 'fluent-support'), /* translators: %1$s: who */ __('%1$s replied to a ticket', 'fluent-support')];
            case NotificationService::TICKET_ASSIGNED:
                /* translators: 1: who, 2: ticket title */
                return [__('assigned a ticket', 'fluent-support'), __('%1$s assigned a ticket in %2$s', 'fluent-support'), /* translators: %1$s: who */ __('%1$s assigned a ticket', 'fluent-support')];
            case NotificationService::TICKET_REASSIGNED:
                /* translators: 1: who, 2: ticket title */
                return [__('reassigned a ticket', 'fluent-support'), __('%1$s reassigned a ticket in %2$s', 'fluent-support'), /* translators: %1$s: who */ __('%1$s reassigned a ticket', 'fluent-support')];
            case NotificationService::TICKET_CLOSED:
                /* translators: 1: who, 2: ticket title */
                return [__('closed a ticket', 'fluent-support'), __('%1$s closed a ticket in %2$s', 'fluent-support'), /* translators: %1$s: who */ __('%1$s closed a ticket', 'fluent-support')];
            case NotificationService::TICKET_REOPENED:
                /* translators: 1: who, 2: ticket title */
                return [__('reopened a ticket', 'fluent-support'), __('%1$s reopened a ticket in %2$s', 'fluent-support'), /* translators: %1$s: who */ __('%1$s reopened a ticket', 'fluent-support')];
            case NotificationService::WORKFLOW_TRIGGERED:
                /* translators: 1: who, 2: ticket title */
                return [__('triggered automation', 'fluent-support'), __('%1$s triggered automation in %2$s', 'fluent-support'), /* translators: %1$s: who */ __('%1$s triggered automation', 'fluent-support')];
        }

        /* translators: 1: who, 2: ticket title */
        return [__('updated a ticket', 'fluent-support'), __('%1$s updated a ticket in %2$s', 'fluent-support'), /* translators: %1$s: who */ __('%1$s updated a ticket', 'fluent-support')];
    }
}
