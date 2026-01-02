<?php

namespace App\Http\Utils;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as GuzzleClient;


/**
 * Convenient wrapper for Accuweather API
 * @see https://developer.accuweather.com/apis
 */
class AccuweatherHelpers
{
    private $baseUrl = 'https://dataservice.accuweather.com/';
    private $apiKey;

    function __construct()
    {
        $this->apiKey = config('services.accuweather.key');

        // Validate API key is configured
        if (empty($this->apiKey) || $this->apiKey === 'xxx') {
            Log::channel('accuweather')->warning('AccuWeather API key not properly configured');
            throw new \Exception('AccuWeather API key not configured. Please set ACCUWEATHER_API_KEY in .env file');
        }
    }


    /**
     * Given a pair of coordinates, get the Accuweather Key of the nearest location.
     *
     * https://developer.accuweather.com/accuweather-locations-api/apis/get/locations/v1/cities/geoposition/search
     *
     * @param double $latitude
     * @param double $longitude
     * @return string
     */
    public function getLocationKeyByCoords($latitude, $longitude)
    {
        $locationKey = '';
        $acUrl = '';

        try
        {
            $acUrl = $this->baseUrl . 'locations/v1/cities/geoposition/search?apikey=' . $this->apiKey .
                        '&toplevel=true&q=' . $latitude . ',' . $longitude;

            $guzzleClient = new GuzzleClient();
            $response1 = $guzzleClient->get($acUrl);
            $body = $response1->getBody();
            $body->seek(0);
            $size = $body->getSize();
            $jsonData = json_decode($body->read($size), true);

            if ($jsonData && isset($jsonData['Key'])) {
                $locationKey = $jsonData['Key'];
            } else {
                Log::channel('accuweather')->error('No location key found in AccuWeather response', [
                    'url' => $acUrl,
                    'response' => $jsonData
                ]);
            }
        }
        catch (\Exception $ex)
        {
            Log::channel('accuweather')->error('getLocationKeyByCoords exception');
            Log::channel('accuweather')->error($acUrl);
            Log::channel('accuweather')->error($ex->getMessage());
        }

        return $locationKey;
    }


    /**
     * Get that location forecast: hourly per next 12 hours.
     *
     * https://developer.accuweather.com/accuweather-forecast-api/apis/get/forecasts/v1/hourly/12hour/%7BlocationKey%7D
     *
     * @param string $locationKey
     * @param int $days
     * @return string[]
     */
    public function get12HourForecast($locationKey)
    {
        $forecast = [];
        $acUrl = '';

        $locationKey = trim($locationKey);
        if (!empty($locationKey))
        {
            try
            {
                $acUrl = $this->baseUrl . 'forecasts/v1/hourly/12hour/' . $locationKey . '?apikey=' . $this->apiKey . '&metric=true';

                $guzzleClient = new GuzzleClient();
                $response1 = $guzzleClient->get($acUrl);
                $body = $response1->getBody();
                $body->seek(0);
                $size = $body->getSize();
                $forecast = json_decode($body->read($size), true);
            }
            catch (\Exception $ex)
            {
                Log::channel('accuweather')->error('get12HourForecast exception');
                Log::channel('accuweather')->error($acUrl);
                Log::channel('accuweather')->error($ex->getMessage());
            }
        }

        return $forecast;
    }


    /**
     * Get that location forecast: dayly per X days
     *
     * https://developer.accuweather.com/accuweather-forecast-api/apis/get/forecasts/v1/daily/5day/%7BlocationKey%7D
     *
     * @param string $locationKey
     * @param int $days
     * @return string[]
     */
    public function getDailyForecast($locationKey, $days = 5, $details = 'false')
    {
        $forecast = [];
        $acUrl = '';

        $locationKey = trim($locationKey);
        if (!empty($locationKey))
        {
            try
            {
                $acUrl = $this->baseUrl . 'forecasts/v1/daily/' . $days . 'day/' . $locationKey . '?apikey=' . $this->apiKey . '&metric=true&details=' . $details;

                $guzzleClient = new GuzzleClient();
                $response1 = $guzzleClient->get($acUrl);
                $body = $response1->getBody();
                $body->seek(0);
                $size = $body->getSize();
                $jsonData = json_decode($body->read($size), true);

                $forecast = $jsonData['DailyForecasts'] ?? [];
            }
            catch (\Exception $ex)
            {
                Log::channel('accuweather')->error('getDailyForecast exception');
                Log::channel('accuweather')->error($acUrl);
                Log::channel('accuweather')->error($ex->getMessage());
            }
        }

        return $forecast;
    }


    /**
     * Get location info by locationKey
     *
     * https://developer.accuweather.com/accuweather-locations-api/apis/get/locations/v1/%7BlocationKey%7D
     *
     * @param string $locationKey
     * @return string[]
     */
    public function getLocationByKey($locationKey)
    {
        $acUrl = '';

        try
        {
            $acUrl = $this->baseUrl . 'locations/v1/' . $locationKey . '?apikey=' . $this->apiKey;

            $guzzleClient = new GuzzleClient();
            $response1 = $guzzleClient->get($acUrl);
            $body = $response1->getBody();
            $body->seek(0);
            $size = $body->getSize();
            $jsonData = json_decode($body->read($size), true);
        }
        catch (\Exception $ex)
        {
            Log::channel('accuweather')->error('getLocationInfoByKey exception');
            Log::channel('accuweather')->error($acUrl);
            Log::channel('accuweather')->error($ex->getMessage());
        }

        return $jsonData;
    }


    /**
     * Get current conditions
     *
     * https://developer.accuweather.com/accuweather-current-conditions-api/apis/get/currentconditions/v1/%7BlocationKey%7D
     *
     * @param string $locationKey
     * @return string[]
     */
    public function getCurrentConditions($locationKey)
    {
        $acUrl = '';

        try
        {
            $acUrl = $this->baseUrl . 'currentconditions/v1/' . $locationKey . '?apikey=' . $this->apiKey;

            $guzzleClient = new GuzzleClient();
            $response1 = $guzzleClient->get($acUrl);
            $body = $response1->getBody();
            $body->seek(0);
            $size = $body->getSize();
            $jsonData = json_decode($body->read($size), true);
        }
        catch (\Exception $ex)
        {
            Log::channel('accuweather')->error('getCurrentConditions exception');
            Log::channel('accuweather')->error($acUrl);
            Log::channel('accuweather')->error($ex->getMessage());
        }

        return $jsonData[0];
    }
}

