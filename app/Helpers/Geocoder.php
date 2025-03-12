<?php

namespace App\Helpers;

class Geocoder
{
    private $geocoder;

    public function __construct()
    {
        // Use the factory to get the appropriate geocoder implementation
        $this->geocoder = GeocoderFactory::create();
    }

    private function googleKey()
    {
        // This method is kept for backward compatibility but is no longer used
        return null;
    }

    public function geocode($location)
    {
        return $this->geocoder->geocode($location);
    }

    public function reverseGeocode($lat, $lng)
    {
        return $this->geocoder->reverseGeocode($lat, $lng);
    }
}
