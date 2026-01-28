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

    public function getFixedSlotsForecastByStation(Request $request)
    {
        $forecast = [];
        $school = $this->getSchool($request);
        $slots = [6, 9, 12, 15, 18, 21];

        $stationsSchools = $school->stationsSchools ?? [];
        foreach ($stationsSchools as $ss) {
            $station = Station::find($ss->station_id ?? null);
            if (!$station || !$station->accuweather) {
                continue;
            }

            $accuweatherData = json_decode($station->accuweather, true) ?: [];
            $hourly = $accuweatherData['12HoursForecast'] ?? [];
            if (empty($hourly)) {
                continue;
            }

            $byHour = [];
            foreach ($hourly as $item) {
                $hour = $this->extractForecastHour($item);
                if ($hour === null) {
                    continue;
                }
                $byHour[$hour] = $item;
            }

            foreach ($slots as $slotHour) {
                $item = $byHour[$slotHour] ?? $this->findNearestForecastHour($byHour, $slotHour);
                if (!$item) {
                    continue;
                }

                $forecast[] = [
                    'time' => sprintf('%02d:00', $slotHour),
                    'temperature' => $this->extractForecastTemp($item),
                    'icon' => $item['icon'] ?? ($item['WeatherIcon'] ?? null),
                ];
            }

            if (!empty($forecast)) {
                break;
            }
        }

        return $this->sendResponse($forecast, 'Weather slots send correctly');
    }

    private function extractForecastHour(array $item): ?int
    {
        if (!empty($item['time'])) {
            $parts = explode(':', $item['time']);
            if (isset($parts[0]) && is_numeric($parts[0])) {
                return (int) $parts[0];
            }
        }

        if (!empty($item['DateTime'])) {
            $timestamp = strtotime($item['DateTime']);
            if ($timestamp !== false) {
                return (int) date('G', $timestamp);
            }
        }

        if (!empty($item['EpochDateTime'])) {
            return (int) date('G', (int) $item['EpochDateTime']);
        }

        return null;
    }

    private function extractForecastTemp(array $item): ?float
    {
        if (isset($item['temperature'])) {
            return (float) $item['temperature'];
        }

        if (!empty($item['Temperature']) && is_array($item['Temperature'])) {
            if (isset($item['Temperature']['Value'])) {
                return (float) $item['Temperature']['Value'];
            }
        }

        return null;
    }

    private function findNearestForecastHour(array $byHour, int $slotHour): ?array
    {
        if (empty($byHour)) {
            return null;
        }

        $closest = null;
        $closestDiff = null;
        foreach ($byHour as $hour => $item) {
            $diff = abs($hour - $slotHour);
            if ($closestDiff === null || $diff < $closestDiff) {
                $closestDiff = $diff;
                $closest = $item;
            }
        }

        return $closest;
    }
}
