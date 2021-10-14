<?php

namespace FluentSupport\App\Modules\Reporting;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;

class Reporting
{
    use ReportingHelperTrait;

    public function getTicketsGrowth($from = false, $to = false, $filters = [])
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);


        $query = $this->db()->table('fs_tickets')
            ->select($this->prepareSelect($frequency))
            ->whereBetween('created_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy($groupBy)
            ->orderBy($orderBy, 'ASC');

        if ($filters) {
            if (!empty($filters['statuses'])) {
                $query->whereIn('status', $filters['statuses']);
            }

            if (!empty($filters['product_id'])) {
                $query->where('product_id', $filters['product_id']);
            }

            if (!empty($filters['agent_id'])) {
                $query->where('agent_id', $filters['agent_id']);
            }
        }

        $items = $query->get();

        return $this->getResult($period, $items);
    }

    public function getTicketResolveGrowth($from = false, $to = false, $filters = [])
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        $query = $this->db()->table('fs_tickets')
            ->select($this->prepareSelect($frequency, 'resolved_at'))
            ->whereBetween('resolved_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('status', 'closed')
            ->groupBy($groupBy)
            ->orderBy($orderBy, 'ASC');

        if ($filters) {
            if (!empty($filters['product_id'])) {
                $query->where('product_id', $filters['product_id']);
            }

            if (!empty($filters['agent_id'])) {
                $query->where('agent_id', $filters['agent_id']);
            }
        }

        $items = $query->get();

        return $this->getResult($period, $items);
    }

    public function getResponseGrowth($from = false, $to = false, $filters = [])
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        $query = $this->db()->table('fs_conversations')
            ->select($this->prepareSelect($frequency, 'created_at'))
            ->whereBetween('created_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('conversation_type', 'response')
            ->groupBy($groupBy)
            ->orderBy($orderBy, 'ASC');

        if ($filters) {
            if (!empty($filters['person_id'])) {
                $query->where('person_id', $filters['person_id']);
            }
        }

        $items = $query->get();

        return $this->getResult($period, $items);
    }

    public function agentSummary($from = false, $to = false, $agent = false)
    {
        if(!$from) {
            $from = current_time('mysql');
        }

        if(!$to) {
            $to = date('Y-m-d', current_time('timestamp') + 86400);
        } else {
            $to = date('Y-m-d', strtotime($to) + 86400);
        }

        $from = $this->makeFromDate($from);
        $to = $this->makeToDate($to);

        $reports = [];

        $resolves = $this->db()->table('fs_tickets')
            ->select([
                $this->db()->raw('COUNT(id) AS count'),
                'agent_id'
            ])
            ->whereBetween('resolved_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('agent_id')
            ->where('status', 'closed')
            ->get();

        $reports = $this->pushAgentsReport('closed', $resolves, $reports);

        $openTickets = $this->db()->table('fs_tickets')
            ->select([
                $this->db()->raw('COUNT(id) AS count'),
                'agent_id'
            ])
            ->groupBy('agent_id')
            ->where('status', '!=', 'closed')
            ->get();

        $reports = $this->pushAgentsReport('opens', $openTickets, $reports);

        $responses = Conversation::select([
            $this->db()->raw('COUNT(id) AS count'),
            $this->db()->raw('person_id as agent_id'),
        ])
            ->whereHas('person', function ($q) {
                $q->where('person_type', '=', 'agent');
            })
            ->whereBetween('created_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('conversation_type', 'response')
            ->groupBy('agent_id')
            ->get();

        $reports = $this->pushAgentsReport('responses', $responses, $reports);

        foreach ($responses as $response) {
            $reports[$response->agent_id]['interactions'] = Conversation::where('person_id', $response->agent_id)
                ->where('conversation_type', 'response')
                ->whereBetween('created_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
                ->groupBy('ticket_id')
                ->get()
                ->count();
        }

        $agentIds = array_keys($reports);

        if ($agent) {
            $agentIds = array_map('intval', explode(',', $agent));
        }

        $agents = Agent::select(['id', 'first_name', 'last_name'])
            ->whereIn('id', $agentIds)
            ->get();

        foreach ($agents as $agent) {
            $report = wp_parse_args($reports[$agent->id], [
                'interactions' => 0,
                'responses' => 0,
                'opens' => 0,
                'closed' => 0,
                'waiting_tickets' => 0
            ]);
            $agent->stats = $report;
            $agent->active_stat = $this->getActiveStatByAgent($agent->id);
        }

        return $agents;
    }

    private function pushAgentsReport($type, $tickets, $reports)
    {
        foreach ($tickets as $ticket) {
            if(!$ticket->agent_id) {
                continue;
            }

            if(!isset($reports[$ticket->agent_id])) {
                $reports[$ticket->agent_id] = [];
            }

            $reports[$ticket->agent_id][$type] = $ticket->count;
        }

        return $reports;
    }

    public function getActiveStats()
    {
        // We will calculate the wait times for open waiting tickets
        $waitStat = Ticket::waitingOnly()
            ->where('status', '!=', 'closed')
            ->whereNotNull('waiting_since')
            ->select([
                $this->db()->raw('avg(UNIX_TIMESTAMP(waiting_since)) as avg_waiting'),
                $this->db()->raw('MIN(UNIX_TIMESTAMP(waiting_since)) as max_waiting'),
                $this->db()->raw('COUNT(*) as total_tickets')
            ])
            ->first();

        if(!$waitStat) {
            return false;
        }

        $waitStat->avg_waiting = intval($waitStat->avg_waiting);
        if($waitStat->avg_waiting > 0) {
            $waitSeconds = time() -  $waitStat->avg_waiting;
            if( $waitSeconds < 172800 && $waitSeconds > 7200) {
                $avgWait = ceil($waitSeconds / 3600) . ' hours';
            } else {
                $avgWait = human_time_diff($waitStat->avg_waiting, time());
            }
        } else {
            $avgWait = 0;
        }

        return [
            'average_waiting' => $avgWait,
            'max_waiting' => (intval($waitStat->max_waiting)) ? human_time_diff(intval($waitStat->max_waiting), time()) : 0,
            'waiting_tickets' => $waitStat->total_tickets
        ];
    }

    public function getActiveStatByAgent($agentId)
    {
        $waitStat = Ticket::waitingOnly()
            ->where('status', '!=', 'closed')
            ->whereNotNull('waiting_since')
            ->where('agent_id', $agentId)
            ->select([
                $this->db()->raw('avg(UNIX_TIMESTAMP(waiting_since)) as avg_waiting'),
                $this->db()->raw('MIN(UNIX_TIMESTAMP(waiting_since)) as max_waiting'),
                $this->db()->raw('COUNT(*) as total_tickets')
            ])
            ->first();

        if(!$waitStat) {
            return false;
        }

        $waitStat->avg_waiting = intval($waitStat->avg_waiting);
        if($waitStat->avg_waiting > 0) {
            $waitSeconds = time() -  $waitStat->avg_waiting;
            if( $waitSeconds < 172800 && $waitSeconds > 7200) {
                $avgWait = ceil($waitSeconds / 3600) . ' hours';
            } else {
                $avgWait = human_time_diff($waitStat->avg_waiting, time());
            }
        } else {
            $avgWait = 0;
        }

        return [
            'average_waiting' => $avgWait,
            'max_waiting' => (intval($waitStat->max_waiting)) ? human_time_diff(intval($waitStat->max_waiting), time()) : 0,
            'waiting_tickets' => $waitStat->total_tickets
        ];
    }
}
