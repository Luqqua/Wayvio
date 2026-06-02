<?php

namespace Modules\AdminSecurity\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\AdminSecurity\Services\AdminMonitoringService;
use Illuminate\Http\Request;

class AdminMonitoringController extends Controller
{
    public function __construct(private AdminMonitoringService $service)
    {
    }

    public function subscription(Request $request)
    {
        return response()->json($this->service->subscriptionOverview());
    }

    public function analytics(Request $request)
    {
        return response()->json($this->service->analyticsOverview());
    }

    public function domains(Request $request)
    {
        return response()->json($this->service->domainOverview());
    }

    public function billing(Request $request)
    {
        return response()->json($this->service->billingOverview());
    }

    public function logs(Request $request)
    {
        return response()->json($this->service->logsOverview());
    }
}
