<?php

namespace FluentSupport\App\Http\Controllers;

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
}
