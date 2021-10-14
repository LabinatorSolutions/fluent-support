<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Modules\Reporting\Reporting;
use FluentSupport\App\Modules\StatModule;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Request\Request;

class ReportingController extends Controller
{
    public function getOverallReports(Request $request)
    {
        return [
            'overall_reports' => StatModule::getOverAllStats()
        ];
    }

    public function getTicketsChart(Request $request, Reporting $reporting)
    {
        list($from, $to) = $request->get('date_range') ?: ['', ''];

        return [
            'stats' => $reporting->getTicketsGrowth($from, $to)
        ];
    }

    public function getResolveChart(Request $request, Reporting $reporting)
    {
        list($from, $to) = $request->get('date_range') ?: ['', ''];

        return [
            'stats' => $reporting->getTicketResolveGrowth($from, $to)
        ];
    }

    public function getResponseChart(Request $request, Reporting $reporting)
    {
        list($from, $to) = $request->get('date_range') ?: ['', ''];

        return [
            'stats' => $reporting->getResponseGrowth($from, $to)
        ];
    }

    public function getAgentsSummary(Request $request, Reporting $reporting)
    {
        return [
          'summary' =>  $reporting->agentSummary($request->get('from'), $request->get('to'))
        ];
    }

    public function getAgentOverallReports(Request $request)
    {
        $agent =  Helper::getAgentByUserId(get_current_user_id());

        return [
            'overall_reports' => StatModule::getAgentOverallStats($agent->id)
        ];
    }

    public function getAgentResolveChart(Request $request, Reporting $reporting)
    {
        $agent =  Helper::getAgentByUserId(get_current_user_id());
        list($from, $to) = $request->get('date_range') ?: ['', ''];

        return [
            'stats' => $reporting->getTicketResolveGrowth($from, $to, ['agent_id' => $agent->id])
        ];
    }

    public function getAgentResponseChart(Request $request, Reporting $reporting)
    {
        $agent =  Helper::getAgentByUserId(get_current_user_id());
        list($from, $to) = $request->get('date_range') ?: ['', ''];

        return [
            'stats' => $reporting->getResponseGrowth($from, $to, ['person_id' => $agent->id])
        ];
    }

    public function getPersonalSummary(Reporting $reporting, Request $request)
    {
        $agent =  Helper::getAgentByUserId(get_current_user_id());

        return [
            'summary' =>  $reporting->agentSummary($request->get('from'), $request->get('to'), $agent->id)
        ];
    }
}
