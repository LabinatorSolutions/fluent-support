<?php

namespace FluentSupport\App\Modules\Reports;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\Framework\Http\Request\Request;

/**
 * The Reports overview, the agent's own reports and the dashboard. Pro's report
 * tabs call Reports directly.
 */
class ReportController extends Controller
{
    /**
     * Ticket counts for a date range, narrowed by mailbox, product, customer or agent.
     */
    public function stats(Request $request)
    {
        list($from, $to) = self::dateRange($request);
        $filters = [
            'mailbox_id'  => $request->getInt('mailbox_id') ?: $request->getInt('business_box'),
            'product_id'  => $request->getInt('product_id'),
            'agent_id'    => $request->getInt('agent_id'),
            'customer_id' => $request->getInt('customer_id'),
        ];

        $titles = [
            'new_tickets'    => __('New Tickets', 'fluent-support'),
            'active_tickets' => __('Active Tickets', 'fluent-support'),
            'closed_tickets' => __('Closed Tickets', 'fluent-support'),
            'responses'      => $filters['agent_id'] ? __('Total Responses', 'fluent-support') : __('Responses', 'fluent-support'),
            'open_tickets'   => __('Open Tickets', 'fluent-support'),
        ];

        $overall = [];
        foreach (Reports::overview($from, $to, $filters) as $key => $count) {
            $overall[$key] = ['title' => $titles[$key], 'key' => $key, 'count' => $count];
        }

        return ['overall_reports' => $overall];
    }

    public function myClosedChart(Request $request)
    {
        list($from, $to) = self::dateRange($request);

        return ['stats' => Reports::chart('closed', $from, $to, ['agent_id' => $this->agent()->id])];
    }

    public function myRepliesChart(Request $request)
    {
        list($from, $to) = self::dateRange($request);

        return ['stats' => Reports::chart('replies', $from, $to, ['agent_id' => $this->agent()->id])];
    }

    public function mySummary(Request $request)
    {
        return ['summary' => Reports::agentSummary(self::date($request->get('from')), self::date($request->get('to')), [$this->agent()->id])];
    }

    /**
     * The agent's dashboard: their cards, plus the widgets asked for in `with`
     * (suggested_tickets, ticket_to_watch, overall_stats, tickets_by_products).
     */
    public function dashboard(Request $request)
    {
        $agent = $this->agent();
        $with = array_map('sanitize_key', (array) $request->get('with', []));

        $stats = Reports::agentToday($agent->id);
        if (PermissionManager::currentUserCan('fst_manage_unassigned_tickets')) {
            $stats['unassigned_tickets'] = [
                'title' => __('Unassigned Tickets', 'fluent-support'),
                'count' => Reports::unassigned()->count()
            ];
        }

        $data = ['stats' => $stats];
        if (in_array('suggested_tickets', $with, true)) {
            $data['suggested_tickets'] = TicketHelper::getSuggestedTickets($agent->id);
        }
        if (in_array('ticket_to_watch', $with, true) && defined('FLUENTSUPPORTPRO')) {
            $data['ticket_to_watch'] = TicketHelper::getTicketsToWatch();
        }
        if (in_array('overall_stats', $with, true)) {
            $data['overall_stats'] = Reports::waitStats();
        }
        if (in_array('tickets_by_products', $with, true)) {
            $data['tickets_by_product'] = Reports::awaitingByProduct();
        }
        if (defined('FLUENTSUPPORTPRO')) {
            $data['dashboard_notice'] = apply_filters('fluent_support/dashboard_notice', '', $agent);
        }

        return $data;
    }

    /**
     * Today's numbers per agent (the dashboard's Agent Performance).
     */
    public function agentsToday()
    {
        if (!PermissionManager::currentUserCan('fst_agent_today_performance')) {
            return ['agent_today_stats' => []];
        }

        return ['agent_today_stats' => Reports::agentsToday()];
    }

    /**
     * [from, to] from date_range: an array or "from,to".
     */
    public static function dateRange(Request $request)
    {
        $range = $request->get('date_range', []);
        $range = is_array($range) ? $range : explode(',', (string) $range);

        return [self::date($range[0] ?? ''), self::date($range[1] ?? '')];
    }

    /**
     * A Y-m-d date, or '' for anything else.
     */
    public static function date($value)
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function agent()
    {
        $agent = Helper::getAgentByUserId();
        if (!$agent) {
            throw ApiException::forbidden();
        }

        return $agent;
    }
}
