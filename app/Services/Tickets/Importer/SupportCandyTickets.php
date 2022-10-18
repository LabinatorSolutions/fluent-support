<?php

namespace FluentSupport\App\Services\Tickets\Importer;

class SupportCandyTickets extends BaseImporter
{
    protected $handler = 'support-candy';

    /**
     * This `supportCandyStats`  method will fetch all available stats of Support Candy.
     */
    public function stats()
    {
        $ticketsCount = $this->getCount();
        $replyCount = $this->db->table('psmsc_threads')
            ->where('type', 'reply')
            ->count();

        return [
            'name'          => 'Support Candy',
            'tickets'       => $ticketsCount,
            'replies'       => $replyCount,
            'customers'     => $this->db->table('psmsc_customers')->count(),
            'handler'       => $this->handler,
            'last_migrated' => get_option('_fs_migrate_support_candy')
        ];
    }

    /**
     * This `doMigration` method will migrate ticket from other support system
     * @param int $page
     * @param string $handler
     * @return array
     */
    public function doMigration($page, $handler)
    {
        $this->handler = $handler;
        $allCounts = $this->getCount();

        $tickets = $this->getTickets($this->limit, $page);

        $results = $this->migrateTickets($tickets);
        $hasMore = $page * $this->limit <= $allCounts;

        if (!$hasMore) {
            update_option('_fs_migrate_support_candy', current_time('mysql'), 'no');
        }

        return [
            'handler'       => $this->handler,
            'insert_ids'    => $results['inserts'],
            'skips'         => count($results['skips']),
            'has_more'      => $hasMore,
            'imported_page' => $page,
            'next_page'     => $page + 1,
            'total_tickets' => $allCounts,
            'remaining'     => $allCounts - ($page * $this->limit)
        ];
    }

    // This `getTickets` will fetch tickets by limit and page number
    private function getTickets($limit, $page)
    {
        global $wpdb;
        $tickets = $this->db->table('psmsc_tickets')
            ->select([
                'psmsc_tickets.*',
                'psmsc_threads.body',
                'psmsc_threads.type'
            ])
            ->selectRaw($wpdb->prefix . 'psmsc_threads.id as thread_id')
            ->join('psmsc_threads', 'psmsc_threads.ticket', '=', 'psmsc_tickets.id')
            ->where('psmsc_threads.type', '=', 'report')
            ->orderBy('psmsc_tickets.id', 'ASC')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        $formattedTickets = [];

        if ($tickets) {
            foreach ($tickets as $ticket) {
                $customerData = $this->getTicketCustomer($ticket->customer);

                if (!$customerData) {
                    continue;
                }

                $replies = $this->getReplies($ticket);
                $ticketData = [
                    'origin_id'              => $ticket->id,
                    'replies'                => $replies,
                    'source'                 => $this->handler,
                    'title'                  => $ticket->subject,
                    'content'                => wp_kses_post($ticket->body),
                    'mailbox_id'             => $this->mailbox->id,
                    'response_count'         => count($replies),
                    'status'                 => 'active',
                    'created_at'             => $ticket->date_created,
                    'updated_at'             => $ticket->date_updated,
                    'last_customer_response' => $ticket->date_created,
                    'waiting_since'          => $ticket->date_created
                ];;

                $ticket->resolved_at = NULL;
                if ($ticket->date_closed && $ticket->date_closed != '0000-00-00 00:00:00') {
                    $ticketData['status'] = 'closed';
                    $ticketData['resolved_at'] = $ticket->date_closed;
                } else if (!$replies) {
                    $ticketData['status'] = 'new';
                }

                $customer = $this->getPerson($customerData, 'customer');

                if(!$customer) {
                    continue;
                }

                $ticketData['customer'] = $customer;

                if ($ticket->assigned_agent) {
                    $ticketData['agent'] = $this->getAgentPerson($ticket->assigned_agent);
                }

                $ticketData['attachments'] = $this->getAttachments($ticket->thread_id);

                $formattedTickets[] = $ticketData;
            }
        }

        return $formattedTickets;
    }

    protected function getAttachments($threadId)
    {
        $attachments = $this->db->table('psmsc_attachments')
            ->where('source_id', $threadId)
            ->orderBY('id', 'ASC')
            ->get();

        $formattedAttachments = [];

        foreach ($attachments as $attachment) {
            $uploadDir = wp_get_upload_dir();
            $fileUrl = str_replace($uploadDir['basedir'], $uploadDir['baseurl'], $attachment->file_path);

            $fileInfo = wp_check_filetype($attachment->file_path);

            $formattedAttachments[] = [
                'full_url'  => $fileUrl,
                'title'     => $attachment->name,
                'file_path' => $attachment->file_path,
                'driver'    => 'local',
                'status'    => 'active',
                'file_type' => (!empty($fileInfo['type'])) ? $fileInfo['type'] : ''
            ];
        }

        return $formattedAttachments;
    }

    protected function getReplies($ticket)
    {
        $replies = $this->db->table('psmsc_threads')
            ->select([
                'psmsc_threads.*',
                'psmsc_customers.user',
                'psmsc_customers.name',
                'psmsc_customers.email'
            ])
            ->join('psmsc_customers', 'psmsc_customers.id', '=', 'psmsc_threads.customer')
            ->where('psmsc_threads.type', 'reply')
            ->where('psmsc_threads.ticket', $ticket->id)
            ->orderBy('psmsc_threads.id', 'ASC')
            ->get();

        $formattedReplies = [];

        foreach ($replies as $reply) {
            $formattedReplies[] = [
                'content'           => $reply->body,
                'conversation_type' => 'response',
                'created_at'        => $reply->date_created,
                'updated_at'        => $reply->date_updated,
                'is_customer_reply' => ($ticket->customer === $reply->customer),
                'user'              => [
                    'user_id'   => $reply->user,
                    'email'     => $reply->email,
                    'full_name' => $reply->name
                ],
                'attachments'       => $this->getAttachments($reply->id)
            ];
        }

        return $formattedReplies;
    }

    private function getCount()
    {
        return $this->db->table('psmsc_tickets')->count();
    }

    private function getTicketCustomer($customerId)
    {
        $customer = $this->db->table('psmsc_customers')
            ->where('id', $customerId)
            ->first();

        if (!$customer) {
            return false;
        }

        return [
            'user_id'   => $customer->user,
            'full_name' => $customer->name,
            'email'     => $customer->email
        ];
    }

    protected function getAgentPerson($agentId)
    {
        static $agents = [];

        if (isset($agents[$agentId])) {
            return $agents[$agentId];
        }

        $agent = $this->db->table('psmsc_agents')
            ->where('id', $agentId)
            ->first();

        if (!$agent) {
            return false;
        }

        $agents[$agentId] = $this->getPerson([
            'user_id'   => $agent->user,
            'full_name' => $agent->name
        ], 'agent');
        return $agents[$agentId];
    }

    public function deleteTickets($page)
    {
        $tables = [
            'psmsc_agents',
            'psmsc_attachments',
            'psmsc_categories',
            'psmsc_custom_fields',
            'psmsc_customers',
            'psmsc_email_notifications',
            'psmsc_email_otp',
            'psmsc_holidays',
            'psmsc_logs',
            'psmsc_options',
            'psmsc_priorities',
            'psmsc_scheduled_tasks',
            'psmsc_statuses',
            'psmsc_threads',
            'psmsc_tickets',
            'psmsc_wh_exceptions',
            'psmsc_working_hrs'
        ];

        global $wpdb;
        foreach ($tables as $table) {
            $this->db->query("TRUNCATE TABLE {$wpdb->prefix}{$table}");
        }

        return [
            'has_more' => false,
            'message'  => 'All Support Candy Tickets and associated data has been deleted. You may now deactivate Support Candy Plugin and start using Fluent Support'
        ];
    }
}
