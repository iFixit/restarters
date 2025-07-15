<?php

namespace App\Http\Controllers;

use App\Providers\AppServiceProvider;
use App\Models\Device;
use App\Models\Group;
use App\Helpers\Fixometer;
use App\Models\Party;
use App\Services\IFixitAuthService;
use Auth;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, IFixitAuthService $iFixitAuthService)
    {
        // Check if external auth is enabled
        if (config('external_auth.enabled', true)) {
            // Check for valid iFixit session cookie
            if ($iFixitAuthService->isAuthenticated()) {
                // We have a valid iFixit session, redirect to dashboard
                return redirect(AppServiceProvider::HOME);
            }
        } else {
            // Fall back to regular Laravel auth check when external auth is disabled
            if (Auth::check()) {
                return redirect(AppServiceProvider::HOME);
            }
        }
        
        // No valid session found, render the landing page
        $stats = Fixometer::loginRegisterStats();
        $deviceCount = array_key_exists(0, $stats['device_count_status']) ? $stats['device_count_status'][0]->counter : 0;

        return view('landing', [
            'co2Total' => $stats['waste_stats'][0]->powered_footprint + $stats['waste_stats'][0]->unpowered_footprint,
            'wasteTotal' => $stats['waste_stats'][0]->powered_waste + $stats['waste_stats'][0]->unpowered_waste,
            'partiesCount' => count($stats['allparties']),
            'deviceCount' => $deviceCount,
        ]);
    }
}
