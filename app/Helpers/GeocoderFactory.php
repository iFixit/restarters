<?php

namespace App\Helpers;

class GeocoderFactory
{
    /**
     * Create a geocoder instance based on the configured map service
     *
     * @return GeocoderInterface
     */
    public static function create()
    {
        $service = env('MAP_SERVICE', 'openstreetmap');
        
        switch ($service) {
            case 'mapbox':
                return new MapboxGeocoder();
            case 'openstreetmap':
            default:
                return new OpenStreetMapGeocoder();
        }
    }
} 