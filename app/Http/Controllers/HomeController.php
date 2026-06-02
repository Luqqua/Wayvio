<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use Illuminate\Support\Facades\Schema;

use App\Models\Page;
use App\Models\Button;
use App\Services\Analytics\LandingAnalyticsDispatcher;

class HomeController extends Controller
{
    //Show home message, number of buttons and updated pages
    public function home()
    {

        $homeMessage = config('platform.home_message', null);
        if ($homeMessage === null) {
            $page = Page::select('home_message')->first();
            $homeMessage = $page?->home_message;
        }
        $message = (object) ['home_message' => $homeMessage ?? 'default'];

        $countButton = Button::count();

        $updatedPages = DB::table('links')
            ->join('users', 'users.id', '=', 'links.user_id')
            ->when(Schema::hasColumn('links', 'is_disabled'), function ($q) {
                $q->where('links.is_disabled', false);
            })
            ->select('users.littlelink_name', 'users.image', DB::raw('max(links.created_at) as created_at'))
            ->groupBy('links.user_id')
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        app(LandingAnalyticsDispatcher::class)->recordLandingView(request(), [
            'landing_variant' => 'home',
        ]);

        return view('home', ['message' => $message, 'countButton' => $countButton, 'updatedPages' => $updatedPages]);
    }

    // Show demo page
    public function demo(request $request)
    {
        $homeMessage = config('platform.home_message', null);
        if ($homeMessage === null) {
            $page = Page::select('home_message')->first();
            $homeMessage = $page?->home_message;
        }
        $message = (object) ['home_message' => $homeMessage ?? 'default'];

        $countButton = Button::count();

        $updatedPages = DB::table('links')
            ->join('users', 'users.id', '=', 'links.user_id')
            ->when(Schema::hasColumn('links', 'is_disabled'), function ($q) {
                $q->where('links.is_disabled', false);
            })
            ->select('users.littlelink_name', 'users.image', DB::raw('max(links.created_at) as created_at'))
            ->groupBy('links.user_id')
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        app(LandingAnalyticsDispatcher::class)->recordLandingView($request, [
            'landing_variant' => 'demo',
        ]);

        return view('demo', ['message' => $message, 'countButton' => $countButton, 'updatedPages' => $updatedPages]);
    }

}
