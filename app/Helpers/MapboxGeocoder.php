<?php

namespace App\Helpers;

class MapboxGeocoder implements GeocoderInterface
{
    /**
     * Mapbox access token
     *
     * @var string
     */
    private $accessToken;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->accessToken = env('MAPBOX_ACCESS_TOKEN', '');
        
        if (empty($this->accessToken)) {
            \Log::error('Mapbox access token is not configured. Please set MAPBOX_ACCESS_TOKEN in your .env file.');
        }
    }
    
    /**
     * Geocode an address using Mapbox Geocoding API
     *
     * @param string $location The address to geocode
     * @return array|false Array with latitude, longitude, and country_code or false if geocoding failed
     */
    public function geocode($location)
    {
        if ($location != 'ForceGeocodeFailure') {
            // Log the incoming location for debugging
            \Log::debug('Geocoding location with Mapbox: ' . $location);
            
            if (empty($this->accessToken)) {
                \Log::error('Cannot geocode: Mapbox access token is not configured');
                return false;
            }
            
            // Use Mapbox Geocoding API
            $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . 
                   urlencode($location) . 
                   '.json?access_token=' . $this->accessToken . 
                   '&limit=1';
            
            // Log the URL we're calling (without the token for security)
            \Log::debug('Mapbox API URL: ' . preg_replace('/access_token=[^&]+/', 'access_token=REDACTED', $url));
            
            $json = @file_get_contents($url);

            if ($json) {
                $data = json_decode($json);
                \Log::debug('Mapbox results: ' . json_encode($data->features));

                if ($data && isset($data->features) && count($data->features) > 0) {
                    $result = $data->features[0];
                    
                    // Mapbox returns coordinates as [longitude, latitude]
                    $coordinates = $result->geometry->coordinates;
                    $longitude = $coordinates[0];
                    $latitude = $coordinates[1];
                    
                    // Get country code from the context
                    $country_code = $this->extractCountryCode($result);
                    
                    $geocoded = [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'country_code' => $country_code,
                    ];
                    
                    \Log::debug('Successfully geocoded with Mapbox: ' . json_encode($geocoded));
                    return $geocoded;
                } else {
                    \Log::warning('No results found from Mapbox for location: ' . $location);
                }
            } else {
                \Log::error('Error fetching from Mapbox for location: ' . $location);
                
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
        if (empty($this->accessToken)) {
            \Log::error('Cannot reverse geocode: Mapbox access token is not configured');
            return (object)[];
        }
        
        // Use Mapbox Reverse Geocoding API
        $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . 
               $lng . ',' . $lat . 
               '.json?access_token=' . $this->accessToken;
        
        $json = @file_get_contents($url);

        if ($json) {
            return json_decode($json);
        }
        
        return (object)[];
    }
    
    /**
     * Extract country code from Mapbox result
     *
     * @param object $result Mapbox geocoding result
     * @return string Country code
     */
    private function extractCountryCode($result)
    {
        // Try to find the country code in the context
        if (isset($result->context)) {
            foreach ($result->context as $context) {
                if (strpos($context->id, 'country') === 0) {
                    // The short code is in the format "country.code"
                    $parts = explode('.', $context->short_code);
                    if (count($parts) > 1) {
                        return strtoupper($parts[1]);
                    }
                }
            }
        }
        
        // If we couldn't find it in the context, try to get it from the place_type
        if (isset($result->place_type) && in_array('country', $result->place_type)) {
            if (isset($result->properties) && isset($result->properties->short_code)) {
                $parts = explode('.', $result->properties->short_code);
                if (count($parts) > 1) {
                    return strtoupper($parts[1]);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Fallback geocoding method that tries to parse the location string
     * and make a more targeted query to Mapbox
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
            \Log::debug('Trying simplified location with Mapbox: ' . $simplifiedLocation);
            
            // Use Mapbox Geocoding API with simplified location
            $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . 
                   urlencode($simplifiedLocation) . 
                   '.json?access_token=' . $this->accessToken . 
                   '&limit=1';
            
            $json = @file_get_contents($url);

            if ($json) {
                $data = json_decode($json);
                \Log::debug('Mapbox fallback results: ' . json_encode($data->features));

                if ($data && isset($data->features) && count($data->features) > 0) {
                    $result = $data->features[0];
                    
                    // Mapbox returns coordinates as [longitude, latitude]
                    $coordinates = $result->geometry->coordinates;
                    $longitude = $coordinates[0];
                    $latitude = $coordinates[1];
                    
                    // Get country code from the context
                    $country_code = $this->extractCountryCode($result);
                    
                    $geocoded = [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'country_code' => $country_code,
                    ];
                    
                    \Log::debug('Successfully geocoded with Mapbox fallback: ' . json_encode($geocoded));
                    return $geocoded;
                }
            }
        }
        
        \Log::warning('Fallback geocoding with Mapbox failed for: ' . $location);
        return false;
    }
} 