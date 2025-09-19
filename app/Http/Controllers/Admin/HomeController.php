<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\Station;
use Illuminate\Http\Request;

class HomeController extends AppBaseController
{
    public function get12HourlyForecastByStation(Request $request)
    {
        $forecast = [];

        $school = $this->getSchool($request);

        // Try each associated station and return the first with valid forecast
        $stationsSchools = $school->stationsSchools ?? [];
        foreach ($stationsSchools as $ss) {
            $station = Station::find($ss->station_id ?? null);
            if ($station && $station->accuweather) {
                $accuweatherData = json_decode($station->accuweather, true) ?: [];
                if (!empty($accuweatherData['12HoursForecast'])) {
                    $forecast = $accuweatherData['12HoursForecast'];
                    break;
                }
            }
        }

        return $this->sendResponse($forecast, 'Weather send correctly');
    }

    public function get5DaysForecastByStation(Request $request)
    {
        $forecast = [];

        $school = $this->getSchool($request);

        // Try each associated station and return the first with valid forecast
        $stationsSchools = $school->stationsSchools ?? [];
        foreach ($stationsSchools as $ss) {
            $station = Station::find($ss->station_id ?? null);
            if ($station && $station->accuweather) {
                $accuweatherData = json_decode($station->accuweather, true) ?: [];
                if (!empty($accuweatherData['5DaysForecast'])) {
                    $forecast = $accuweatherData['5DaysForecast'];
                    break;
                }
            }
        }

        return $this->sendResponse($forecast, 'Weather send correctly');
    }
}
