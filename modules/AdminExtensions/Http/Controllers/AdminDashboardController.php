<?php

namespace Modules\AdminExtensions\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\AdminExtensions\Services\AdminDashboardService;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(private AdminDashboardService $service)
    {
    }

    public function listUsers()
    {
        return response()->json($this->service->users());
    }

    public function listDomains()
    {
        return response()->json($this->service->domains());
    }

    public function listWebhookLogs(Request $request)
    {
        return response()->json($this->service->webhookLogs([
            'event_id' => $request->query('event_id'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'user_id' => $request->query('user_id'),
            'session_id' => $request->query('session_id'),
            'limit' => $request->query('limit'),
        ]));
    }

    public function listSystemEvents()
    {
        return response()->json($this->service->systemEvents());
    }

    public function securityOverview()
    {
        return response()->json($this->service->securityOverview());
    }
}
