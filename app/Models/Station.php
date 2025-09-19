<?php

namespace App\Models;

use App\Http\Utils\AccuweatherHelpers;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
 use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="Station",
 *      required={"name","country","province","address","image","map","latitude","longitude","num_hanger","num_chairlift","num_cabin","num_cabin_large","num_fonicular","show_details","active"},
 *      @OA\Property(
 *           property="name",
 *           description="Name of the station",
 *           type="string"
 *       ),
 *      @OA\Property(
 *          property="cp",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="city",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="country",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="province",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="address",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="image",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="map",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="latitude",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="longitude",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="show_details",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="active",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="accuweather",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *       @OA\Property(
 *           property="num_hanger",
 *           description="Number of hangers",
 *           type="integer"
 *       ),
 *       @OA\Property(
 *           property="num_chairlift",
 *           description="Number of chairlifts",
 *           type="integer"
 *       ),
 *       @OA\Property(
 *           property="num_cabin",
 *           description="Number of cabins",
 *           type="integer"
 *       ),
 *       @OA\Property(
 *           property="num_cabin_large",
 *           description="Number of large cabins",
 *           type="integer"
 *       ),
 *       @OA\Property(
 *           property="num_fonicular",
 *           description="Number of funiculars",
 *           type="integer"
 *       ),
 *      @OA\Property(
 *          property="updated_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="deleted_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      )
 * )
 */
class Station extends Model
{
      use LogsActivity, SoftDeletes, HasFactory;     public $table = 'stations';

    public $fillable = [
        'name',
        'cp',
        'city',
        'country',
        'province',
        'address',
        'image',
        'map',
        'latitude',
        'longitude',
        'num_hanger',
        'num_chairlift',
        'num_cabin',
        'num_cabin_large',
        'num_fonicular',
        'show_details',
        'active',
        'accuweather'
    ];

    protected $casts = [
        'name' => 'string',
        'cp' => 'string',
        'city' => 'string',
        'country' => 'string',
        'province' => 'string',
        'address' => 'string',
        'image' => 'string',
        'map' => 'string',
        'latitude' => 'string',
        'longitude' => 'string',
        'show_details' => 'boolean',
        'active' => 'boolean',
        'accuweather' => 'string'
    ];

    public static array $rules = [
        'name' => 'required|string|max:255',
        'cp' => 'nullable|string|max:65535',
        'city' => 'nullable|string|max:65535',
        'country' => 'required|string|max:65535',
        'province' => 'required|string|max:65535',
        'address' => 'required|string|max:100',
        'image' => 'required|string|max:500',
        'map' => 'required|string|max:500',
        'latitude' => 'required|string|max:100',
        'longitude' => 'required|string|max:100',
        'num_hanger' => 'required',
        'num_chairlift' => 'required',
        'num_cabin' => 'required',
        'num_cabin_large' => 'required',
        'num_fonicular' => 'required',
        'show_details' => 'required|boolean',
        'active' => 'required|boolean',
        'accuweather' => 'nullable|string|max:65535',
        'updated_at' => 'nullable',
        'created_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function courses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Course::class, 'station_id');
    }

    public function monitorNwds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MonitorNwd::class, 'station_id');
    }

    public function monitorsSchools(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MonitorsSchool::class, 'station_id');
    }

    public function stationServices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\StationService::class, 'station_id');
    }

    public function stationsSchools(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\StationsSchool::class, 'station_id');
    }

    /**
     * Get "this" Station weather forecast;
     * this takes a while (and costs X money per Y queries),
     * so store in database as cache.
     *
     * @return array Returns array with 'success' boolean and 'message' string
     */
    public function downloadAccuweatherData()
    {
        $stationInfo = [
            'station_id' => $this->id,
            'station_name' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude
        ];

        try {
            // Validate station coordinates
            if (!$this->validateCoordinates()) {
                $message = 'Invalid or missing coordinates for station';
                Log::channel('accuweather')->warning($message, $stationInfo);
                return ['success' => false, 'message' => $message];
            }

            // Preserve previously stored data to avoid losing forecast
            $data = [];
            if ($this->accuweather) {
                $decodedData = json_decode($this->accuweather, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
                    $data = $decodedData;
                } else {
                    Log::channel('accuweather')->warning('Failed to decode existing weather data, starting fresh', array_merge($stationInfo, [
                        'json_error' => json_last_error_msg()
                    ]));
                }
            }

            // Initialize AccuWeather helper with error handling
            try {
                $ah = new AccuweatherHelpers();
            } catch (\Exception $e) {
                $message = 'Failed to initialize AccuWeather API: ' . $e->getMessage();
                Log::channel('accuweather')->error($message, $stationInfo);
                return ['success' => false, 'message' => $message];
            }

            // Get or validate LocationKey
            $locationKey = $data['LocationKey'] ?? '';
            if (empty($locationKey)) {
                Log::channel('accuweather')->info('Fetching location key for station', $stationInfo);

                $locationKey = $ah->getLocationKeyByCoords($this->latitude, $this->longitude);

                if (empty($locationKey)) {
                    $message = 'Could not retrieve location key from AccuWeather API';
                    Log::channel('accuweather')->error($message, $stationInfo);
                    return ['success' => false, 'message' => $message];
                }

                $data['LocationKey'] = $locationKey;
                Log::channel('accuweather')->info('Successfully retrieved location key', array_merge($stationInfo, [
                    'location_key' => $locationKey
                ]));
            }

            $forecastsUpdated = false;

            // Get 12-hour forecast with comprehensive validation
            try {
                Log::channel('accuweather')->info('Fetching 12-hour forecast', array_merge($stationInfo, [
                    'location_key' => $locationKey
                ]));

                $new12h = $ah->get12HourForecast($locationKey);

                if (!empty($new12h) && is_array($new12h)) {
                    $mapped12h = [];
                    $validEntries = 0;

                    foreach ($new12h as $index => $line) {
                        if ($this->validate12HourForecastEntry($line, $index, $stationInfo)) {
                            try {
                                $mapped12h[] = [
                                    'time' => Carbon::parse($line['DateTime'])->format('H:i'),
                                    'temperature' => $line['Temperature']['Value'],
                                    'icon' => $line['WeatherIcon']
                                ];
                                $validEntries++;
                            } catch (\Exception $e) {
                                Log::channel('accuweather')->warning('Failed to parse 12-hour forecast entry', array_merge($stationInfo, [
                                    'index' => $index,
                                    'error' => $e->getMessage(),
                                    'entry' => $line
                                ]));
                            }
                        }
                    }

                    if ($validEntries > 0) {
                        $data['12HoursForecast'] = $mapped12h;
                        $forecastsUpdated = true;
                        Log::channel('accuweather')->info('Successfully processed 12-hour forecast', array_merge($stationInfo, [
                            'valid_entries' => $validEntries,
                            'total_entries' => count($new12h)
                        ]));
                    } else {
                        Log::channel('accuweather')->warning('No valid 12-hour forecast entries found', $stationInfo);
                    }
                } else {
                    Log::channel('accuweather')->warning('Empty or invalid 12-hour forecast response', array_merge($stationInfo, [
                        'response_type' => gettype($new12h),
                        'response_empty' => empty($new12h)
                    ]));
                }
            } catch (\Exception $e) {
                Log::channel('accuweather')->error('Exception while processing 12-hour forecast', array_merge($stationInfo, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]));
            }

            // Get 5-day forecast with comprehensive validation
            try {
                Log::channel('accuweather')->info('Fetching 5-day forecast', array_merge($stationInfo, [
                    'location_key' => $locationKey
                ]));

                $new5d = $ah->getDailyForecast($locationKey, 5);

                if (!empty($new5d) && is_array($new5d)) {
                    $mapped5d = [];
                    $validEntries = 0;

                    foreach ($new5d as $index => $line) {
                        if ($this->validate5DayForecastEntry($line, $index, $stationInfo)) {
                            try {
                                $mapped5d[] = [
                                    'day' => Carbon::parse($line['Date'])->format('Y-m-d'),
                                    'temperature_min' => $line['Temperature']['Minimum']['Value'],
                                    'temperature_max' => $line['Temperature']['Maximum']['Value'],
                                    'icon' => $line['Day']['Icon']
                                ];
                                $validEntries++;
                            } catch (\Exception $e) {
                                Log::channel('accuweather')->warning('Failed to parse 5-day forecast entry', array_merge($stationInfo, [
                                    'index' => $index,
                                    'error' => $e->getMessage(),
                                    'entry' => $line
                                ]));
                            }
                        }
                    }

                    if ($validEntries > 0) {
                        $data['5DaysForecast'] = $mapped5d;
                        $forecastsUpdated = true;
                        Log::channel('accuweather')->info('Successfully processed 5-day forecast', array_merge($stationInfo, [
                            'valid_entries' => $validEntries,
                            'total_entries' => count($new5d)
                        ]));
                    } else {
                        Log::channel('accuweather')->warning('No valid 5-day forecast entries found', $stationInfo);
                    }
                } else {
                    Log::channel('accuweather')->warning('Empty or invalid 5-day forecast response', array_merge($stationInfo, [
                        'response_type' => gettype($new5d),
                        'response_empty' => empty($new5d)
                    ]));
                }
            } catch (\Exception $e) {
                Log::channel('accuweather')->error('Exception while processing 5-day forecast', array_merge($stationInfo, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]));
            }

            // Update the database with validated data
            try {
                $data['last_updated'] = Carbon::now()->toISOString();
                $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $message = 'Failed to encode weather data to JSON: ' . json_last_error_msg();
                    Log::channel('accuweather')->error($message, $stationInfo);
                    return ['success' => false, 'message' => $message];
                }

                $this->accuweather = $jsonData;
                $this->save();

                $message = $forecastsUpdated ? 'Weather data successfully updated' : 'Weather data preserved (no new forecasts available)';
                Log::channel('accuweather')->info($message, array_merge($stationInfo, [
                    'forecasts_updated' => $forecastsUpdated,
                    'has_12h_forecast' => isset($data['12HoursForecast']),
                    'has_5d_forecast' => isset($data['5DaysForecast'])
                ]));

                return ['success' => true, 'message' => $message];

            } catch (\Exception $e) {
                $message = 'Failed to save weather data to database: ' . $e->getMessage();
                Log::channel('accuweather')->error($message, array_merge($stationInfo, [
                    'error' => $e->getMessage()
                ]));
                return ['success' => false, 'message' => $message];
            }

        } catch (\Exception $e) {
            $message = 'Unexpected error during weather data download: ' . $e->getMessage();
            Log::channel('accuweather')->error($message, array_merge($stationInfo, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            return ['success' => false, 'message' => $message];
        }
    }

    /**
     * Validate station coordinates
     *
     * @return bool
     */
    private function validateCoordinates()
    {
        // Check if coordinates exist
        if (empty($this->latitude) || empty($this->longitude)) {
            return false;
        }

        // Convert to float for validation
        $lat = floatval($this->latitude);
        $lng = floatval($this->longitude);

        // Validate latitude range (-90 to 90)
        if ($lat < -90 || $lat > 90) {
            return false;
        }

        // Validate longitude range (-180 to 180)
        if ($lng < -180 || $lng > 180) {
            return false;
        }

        // Check for obviously invalid coordinates (0,0)
        if ($lat == 0 && $lng == 0) {
            return false;
        }

        return true;
    }

    /**
     * Validate 12-hour forecast entry
     *
     * @param mixed $entry
     * @param int $index
     * @param array $stationInfo
     * @return bool
     */
    private function validate12HourForecastEntry($entry, $index, $stationInfo)
    {
        if (!is_array($entry)) {
            Log::channel('accuweather')->warning('12-hour forecast entry is not an array', array_merge($stationInfo, [
                'index' => $index,
                'entry_type' => gettype($entry)
            ]));
            return false;
        }

        // Validate required fields
        $requiredFields = ['DateTime', 'Temperature', 'WeatherIcon'];
        foreach ($requiredFields as $field) {
            if (!isset($entry[$field])) {
                Log::channel('accuweather')->warning('Missing required field in 12-hour forecast entry', array_merge($stationInfo, [
                    'index' => $index,
                    'missing_field' => $field,
                    'available_fields' => array_keys($entry)
                ]));
                return false;
            }
        }

        // Validate DateTime field
        if (empty($entry['DateTime']) || !is_string($entry['DateTime'])) {
            Log::channel('accuweather')->warning('Invalid DateTime in 12-hour forecast entry', array_merge($stationInfo, [
                'index' => $index,
                'datetime_value' => $entry['DateTime'],
                'datetime_type' => gettype($entry['DateTime'])
            ]));
            return false;
        }

        // Validate Temperature structure
        if (!is_array($entry['Temperature']) || !isset($entry['Temperature']['Value'])) {
            Log::channel('accuweather')->warning('Invalid Temperature structure in 12-hour forecast entry', array_merge($stationInfo, [
                'index' => $index,
                'temperature_value' => $entry['Temperature'] ?? null,
                'temperature_type' => gettype($entry['Temperature'] ?? null)
            ]));
            return false;
        }

        // Validate temperature value is numeric
        if (!is_numeric($entry['Temperature']['Value'])) {
            Log::channel('accuweather')->warning('Temperature value is not numeric in 12-hour forecast entry', array_merge($stationInfo, [
                'index' => $index,
                'temperature_value' => $entry['Temperature']['Value'],
                'value_type' => gettype($entry['Temperature']['Value'])
            ]));
            return false;
        }

        // Validate WeatherIcon is numeric
        if (!is_numeric($entry['WeatherIcon'])) {
            Log::channel('accuweather')->warning('WeatherIcon is not numeric in 12-hour forecast entry', array_merge($stationInfo, [
                'index' => $index,
                'weather_icon' => $entry['WeatherIcon'],
                'icon_type' => gettype($entry['WeatherIcon'])
            ]));
            return false;
        }

        return true;
    }

    /**
     * Validate 5-day forecast entry
     *
     * @param mixed $entry
     * @param int $index
     * @param array $stationInfo
     * @return bool
     */
    private function validate5DayForecastEntry($entry, $index, $stationInfo)
    {
        if (!is_array($entry)) {
            Log::channel('accuweather')->warning('5-day forecast entry is not an array', array_merge($stationInfo, [
                'index' => $index,
                'entry_type' => gettype($entry)
            ]));
            return false;
        }

        // Validate required fields
        $requiredFields = ['Date', 'Temperature', 'Day'];
        foreach ($requiredFields as $field) {
            if (!isset($entry[$field])) {
                Log::channel('accuweather')->warning('Missing required field in 5-day forecast entry', array_merge($stationInfo, [
                    'index' => $index,
                    'missing_field' => $field,
                    'available_fields' => array_keys($entry)
                ]));
                return false;
            }
        }

        // Validate Date field
        if (empty($entry['Date']) || !is_string($entry['Date'])) {
            Log::channel('accuweather')->warning('Invalid Date in 5-day forecast entry', array_merge($stationInfo, [
                'index' => $index,
                'date_value' => $entry['Date'],
                'date_type' => gettype($entry['Date'])
            ]));
            return false;
        }

        // Validate Temperature structure
        if (!is_array($entry['Temperature']) ||
            !isset($entry['Temperature']['Minimum']['Value']) ||
            !isset($entry['Temperature']['Maximum']['Value'])) {
            Log::channel('accuweather')->warning('Invalid Temperature structure in 5-day forecast entry', array_merge($stationInfo, [
                'index' => $index,
                'temperature_structure' => $entry['Temperature'] ?? null
            ]));
            return false;
        }

        // Validate temperature values are numeric
        if (!is_numeric($entry['Temperature']['Minimum']['Value']) ||
            !is_numeric($entry['Temperature']['Maximum']['Value'])) {
            Log::channel('accuweather')->warning('Temperature values are not numeric in 5-day forecast entry', array_merge($stationInfo, [
                'index' => $index,
                'min_temp' => $entry['Temperature']['Minimum']['Value'],
                'max_temp' => $entry['Temperature']['Maximum']['Value'],
                'min_temp_type' => gettype($entry['Temperature']['Minimum']['Value']),
                'max_temp_type' => gettype($entry['Temperature']['Maximum']['Value'])
            ]));
            return false;
        }

        // Validate Day structure and Icon
        if (!is_array($entry['Day']) || !isset($entry['Day']['Icon'])) {
            Log::channel('accuweather')->warning('Invalid Day structure in 5-day forecast entry', array_merge($stationInfo, [
                'index' => $index,
                'day_structure' => $entry['Day'] ?? null
            ]));
            return false;
        }

        // Validate Day Icon is numeric
        if (!is_numeric($entry['Day']['Icon'])) {
            Log::channel('accuweather')->warning('Day Icon is not numeric in 5-day forecast entry', array_merge($stationInfo, [
                'index' => $index,
                'day_icon' => $entry['Day']['Icon'],
                'icon_type' => gettype($entry['Day']['Icon'])
            ]));
            return false;
        }

        return true;
    }

    /**
     * Get all Stations weather forecast.
     */
    public static function downloadAllAccuweatherData()
    {
        $successCount = 0;
        $failureCount = 0;
        $stations = Station::where('active', true)->get();

        Log::channel('accuweather')->info('Starting bulk weather data download', [
            'total_stations' => $stations->count()
        ]);

        foreach ($stations as $station) {
            try {
                $result = $station->downloadAccuweatherData();

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                    Log::channel('accuweather')->warning('Station weather download failed', [
                        'station_id' => $station->id,
                        'station_name' => $station->name,
                        'error' => $result['message']
                    ]);
                }
            } catch (\Exception $e) {
                $failureCount++;
                Log::channel('accuweather')->error('Exception during station weather download', [
                    'station_id' => $station->id,
                    'station_name' => $station->name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::channel('accuweather')->info('Bulk weather data download completed', [
            'total_stations' => $stations->count(),
            'successful_downloads' => $successCount,
            'failed_downloads' => $failureCount
        ]);

        return [
            'total' => $stations->count(),
            'success' => $successCount,
            'failures' => $failureCount
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
    }
}
