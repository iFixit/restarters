<?php

namespace App\Helpers;

class OpenStreetMapGeocoder implements GeocoderInterface
{
    /**
     * Geocode an address using OpenStreetMap's Nominatim service
     *
     * @param string $location The address to geocode
     * @return array|false Array with latitude, longitude, and country_code or false if geocoding failed
     */
    public function geocode($location)
    {
        if ($location != 'ForceGeocodeFailure') {
            // Log the incoming location for debugging
            \Log::debug('Geocoding location: ' . $location);
            
            // Add a user agent as required by Nominatim usage policy
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Restarters.net/1.0'
                ]
            ];
            $context = stream_context_create($opts);
            
            // Use Nominatim for geocoding
            $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($location);
            
            // Log the URL we're calling
            \Log::debug('Nominatim URL: ' . $url);
            
            $json = @file_get_contents($url, false, $context);

            if ($json) {
                $results = json_decode($json);
                \Log::debug('Nominatim results: ' . json_encode($results));

                if ($results && count($results) > 0) {
                    $result = $results[0];
                    
                    // Get country code using reverse geocoding
                    $country_code = $this->getCountryCode($result->lat, $result->lon);
                    
                    $geocoded = [
                        'latitude' => $result->lat,
                        'longitude' => $result->lon,
                        'country_code' => $country_code,
                    ];
                    
                    \Log::debug('Successfully geocoded: ' . json_encode($geocoded));
                    return $geocoded;
                } else {
                    \Log::warning('No results found for location: ' . $location);
                }
            } else {
                \Log::error('Error fetching from Nominatim for location: ' . $location);
                
                // Try a fallback approach - parse the location string
                return $this->fallbackGeocoding($location);
            }
        }

        return false;
    }

    /**
     * Reverse geocode coordinates to get address details
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return object Address details
     */
    public function reverseGeocode($lat, $lng)
    {
        // Add a user agent as required by Nominatim usage policy
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Restarters.net/1.0'
            ]
        ];
        $context = stream_context_create($opts);
        
        // Use Nominatim for reverse geocoding
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng&addressdetails=1";
        $json = file_get_contents($url, false, $context);

        return json_decode($json);
    }
    
    /**
     * Get country code from coordinates
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return string Country code
     */
    private function getCountryCode($lat, $lng)
    {
        $result = $this->reverseGeocode($lat, $lng);
        
        if ($result && isset($result->address) && isset($result->address->country_code)) {
            return strtoupper($result->address->country_code);
        }
        
        return '';
    }

    /**
     * Fallback geocoding method that tries to parse the location string
     * and make a more targeted query to Nominatim
     *
     * @param string $location The address to geocode
     * @return array|false Array with latitude, longitude, and country_code or false if geocoding failed
     */
    private function fallbackGeocoding($location)
    {
        \Log::debug('Attempting fallback geocoding for: ' . $location);
        
        // Split the location into parts (assuming comma-separated format)
        $parts = array_map('trim', explode(',', $location));
        
        if (count($parts) >= 2) {
            // Try with just the city/town and country/state (last two parts)
            $simplifiedLocation = implode(', ', array_slice($parts, -2));
            \Log::debug('Trying simplified location: ' . $simplifiedLocation);
            
            // Add a user agent as required by Nominatim usage policy
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Restarters.net/1.0'
                ]
            ];
            $context = stream_context_create($opts);
            
            // Use Nominatim for geocoding with the simplified location
            $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($simplifiedLocation);
            $json = @file_get_contents($url, false, $context);

            if ($json) {
                $results = json_decode($json);
                \Log::debug('Fallback results: ' . json_encode($results));

                if ($results && count($results) > 0) {
                    $result = $results[0];
                    
                    // Get country code using reverse geocoding
                    $country_code = $this->getCountryCode($result->lat, $result->lon);
                    
                    $geocoded = [
                        'latitude' => $result->lat,
                        'longitude' => $result->lon,
                        'country_code' => $country_code,
                    ];
                    
                    \Log::debug('Successfully geocoded with fallback: ' . json_encode($geocoded));
                    return $geocoded;
                }
            }
        }
        
        \Log::warning('Fallback geocoding failed for: ' . $location);
        return false;
    }
} 