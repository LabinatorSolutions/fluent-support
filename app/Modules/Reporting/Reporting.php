<?php

namespace FluentSupport\App\Modules\Reporting;

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
            if (!empty($filters['agent_id'])) {
                $query->where('person_id', $filters['person_id']);
            }
        }

        $items = $query->get();

        return $this->getResult($period, $items);
    }

}
