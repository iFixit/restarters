<?php

namespace App\Helpers;

class Geocoder
{
    private $osmGeocoder;
    
    public function __construct()
    {
        $this->osmGeocoder = new OpenStreetMapGeocoder();
    }

    private function googleKey()
    {
        // This method is kept for backward compatibility but is no longer used
        return null;
    }

    public function geocode($location)
    {
        return $this->osmGeocoder->geocode($location);
    }

    public function reverseGeocode($lat, $lng)
    {
        return $this->osmGeocoder->reverseGeocode($lat, $lng);
    }
}
