<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;

class TimeZoneController extends Controller
{
    public function lookup(Request $request)
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $timestamp = $request->query('timestamp', time());

        if (!$lat || !$lng) {
            return response()->json(['error' => 'Missing lat/lng'], 400);
        }

        $apiKey = env('GOOGLE_MAPS_BACKEND_KEY');
        $url = "https://maps.googleapis.com/maps/api/timezone/json?location={$lat},{$lng}&timestamp={$timestamp}&key={$apiKey}";

        $response = Http::get($url);

        return $response->json();
    }
} 