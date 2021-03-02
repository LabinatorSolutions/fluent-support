<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Modules\Reporting\Reporting;
use FluentSupport\App\Modules\StatModule;
use FluentSupport\Framework\Request\Request;

class ReportingController extends Controller
{
    public function getOverallReports(Request $request)
    {
        $overallStats = StatModule::getOverAllStats();

        return [
            'overall_reports' => $overallStats
        ];
    }

    public function getTicketsChart(Request $request, Reporting $reporting)
    {
        $stats = $reporting->getTicketsGrowth();

        return [
            'stats' => $stats
        ];
    }

    public function getResolveChart(Request $request, Reporting $reporting)
    {
        $stats = $reporting->getTicketResolveGrowth();

        return [
            'stats' => $stats
        ];
    }

    public function getResponseChart(Request $request, Reporting $reporting)
    {
        $stats = $reporting->getResponseGrowth();

        return [
            'stats' => $stats
        ];
    }
}
