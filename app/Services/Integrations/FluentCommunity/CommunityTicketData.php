<?php

namespace FluentSupport\App\Services\Integrations\FluentCommunity;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\CommunityTicket\CommunityTicketPolicy;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

/**
 * Tells the community menu which posts and comments already have a ticket: adds
 * `fluent_support_ticket` to FluentCommunity's feed and comment API responses, for
 * moderator agents only, from one lookup per response.
 */
class CommunityTicketData
{
    const FIELD = 'fluent_support_ticket';

    public function boot()
    {
        add_filter('fluent_community/feeds_api_response', [$this, 'addToFeeds']);
        add_filter('fluent_community/bookmarks_api_response', [$this, 'addToFeeds']);
        add_filter('fluent_community/feed_api_response', [$this, 'addToFeed']);
        add_filter('fluent_community/comments_api_response', [$this, 'addToComments']);
        add_filter('fluent_community/comment_api_response', [$this, 'addToComment']);
    }

    /**
     * The feed list and the bookmarks list: `feeds.data`, plus `sticky` on the feed list.
     */
    public function addToFeeds($data)
    {
        $items = [];
        if (isset($data['feeds']['data']) && is_iterable($data['feeds']['data'])) {
            foreach ($data['feeds']['data'] as $feed) {
                $items[] = $feed;
            }
        }
        if (!empty($data['sticky'])) {
            $items[] = $data['sticky'];
        }

        $this->attach($items);

        return $data;
    }

    public function addToFeed($data)
    {
        if (!empty($data['feed'])) {
            $this->attach([$data['feed']]);
        }

        return $data;
    }

    public function addToComments($data)
    {
        if (isset($data['comments']) && is_iterable($data['comments'])) {
            $items = [];
            foreach ($data['comments'] as $comment) {
                $items[] = $comment;
            }
            $this->attach($items);
        }

        return $data;
    }

    public function addToComment($data)
    {
        if (!empty($data['comment'])) {
            $this->attach([$data['comment']]);
        }

        return $data;
    }

    /**
     * @param array $items FluentCommunity Feed and Comment models; a post's loaded comments are included.
     */
    private function attach(array $items)
    {
        if (!$items || !CommunityTicketPolicy::currentUserCan()) {
            return;
        }

        $targets = [];
        foreach ($items as $item) {
            if ($item instanceof \FluentCommunity\App\Models\Feed) {
                $targets['_fcom_feed_' . $item->id] = $item;
                if ($item->relationLoaded('comments')) {
                    foreach ($item->comments as $comment) {
                        $targets['_fcom_comment_' . $comment->id] = $comment;
                    }
                }
            } elseif ($item instanceof \FluentCommunity\App\Models\Comment) {
                $targets['_fcom_comment_' . $item->id] = $item;
            }
        }

        if (!$targets) {
            return;
        }

        $ticketIds = Meta::where('object_type', 'ticket')
            ->whereIn('key', array_keys($targets))
            ->pluck('object_id', 'key')
            ->all();

        if (!$ticketIds) {
            return;
        }

        $tickets = Ticket::whereIn('id', array_unique(array_values($ticketIds)))->get()->keyBy('id');

        // The agent and their mailbox restrictions once, not once per ticket.
        $access = new AgentTicketAccess();
        $agent = Helper::getAgentByUserId();
        $restrictions = $agent ? $agent->getMeta('agent_restrictions', []) : [];

        foreach ($ticketIds as $key => $ticketId) {
            $ticket = $tickets->get($ticketId);
            if (!$ticket) {
                continue;
            }

            // A ticket the agent may not open: say it exists, nothing more.
            $targets[$key]->setAttribute(self::FIELD, $access->canAccess($agent, $ticket, $restrictions) ? [
                'id'     => (int) $ticket->id,
                'number' => (string) $ticket->display_ticket_number,
                'url'    => admin_url('admin.php?page=fluent-support#/tickets/' . (int) $ticket->id . '/view'),
            ] : [
                'exists' => true,
            ]);
        }
    }
}
