<?php

namespace Modules\AnalyticsPremium\Http\Controllers;

use App\Http\Controllers\Controller;

class AnalyticsPageController extends Controller
{
    public function show()
    {
        return redirect()->route('analytics.dashboard');
    }
}
