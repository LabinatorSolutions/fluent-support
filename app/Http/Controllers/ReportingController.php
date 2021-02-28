<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Modules\StatModule;
use FluentSupport\Framework\Request\Request;

class ReportingController extends Controller
{
    public function getOverallReports(Request $request)
    {
        $overallStats = StatModule::getOverAllStats();
        $agents = Agent::all();
        $agent_reports = [];

        foreach ($agents as $agent) {
            $agent_reports[] = [
                'agent' => $agent,
                'reports' => StatModule::getAgentStat($agent->id)
            ];
        }

        return [
            'overall_reports' => $overallStats,
            'agent_reports' => $agent_reports
        ];
    }
}
