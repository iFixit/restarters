<?php

namespace App\Helpers;

interface GeocoderInterface
{
    /**
     * Geocode an address to get coordinates and country code
     *
     * @param string $location The address to geocode
     * @return array|false Array with latitude, longitude, and country_code or false if geocoding failed
     */
    public function geocode($location);
    
    /**
     * Reverse geocode coordinates to get address details
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return object Address details
     */
    public function reverseGeocode($lat, $lng);
} 