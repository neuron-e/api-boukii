<?php

namespace App\Traits;

use App\Models\BookingUser;
use App\Models\Monitor;
use App\Models\MonitorNwd;
use App\Models\Season;
use App\Support\IntervalDiscountHelper;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

trait Utils
{
    public function getCourseAvailability($course, $monitorsGrouped, $startDate = null, $endDate = null, $onlyWeekends = false)
    {
        if (!$course) {
            return null; // o manejar como prefieras
        }

        $totalBookings = 0;
        $totalAvailablePlaces = 0;
        $totalPlaces = 0;
        $totalAvailableHours = 0;
        $totalPlacesHours = 0;
        $totalBookingsPlaces = 0;
        $totalBookingsHours = 0;
        $totalHours = 0;
        $totalHoursAvailable = 0;

        // Si el curso es de tipo 2, buscamos el nÃºmero de monitores para el deporte del curso
        if ($course->course_type == 2 && isset($monitorsGrouped[$course->sport_id])) {
            $monitorsForSport = count($monitorsGrouped[$course->sport_id]);
        } else {
            $monitorsForSport = 1; // Si no hay monitores, consideramos al menos 1
        }

        $dates = $course->courseDates;
        $dates = $dates->filter(function ($date) {
            // Keep default behavior for null; exclude explicit inactive dates
            return $date->active !== 0;
        });
        if ($dates->isEmpty()) {
            $dates = $course->courseDates;
        }
        $today = Carbon::today();
        $season = Season::whereDate('start_date', '<=', $today) // Fecha de inicio menor o igual a hoy
        ->whereDate('end_date', '>=', $today)   // Fecha de fin mayor o igual a hoy
        ->first();

        // Utiliza start_date y end_date de la request si estÃ¡n presentes, sino usa las fechas de la temporada
        $startDate = $startDate ?? ($season ? Carbon::parse($season->start_date) : null);
        $endDate = $endDate ?? ($season ? Carbon::parse($season->end_date) : null);


        if ($startDate && $endDate) {
            $dates = $dates->filter(function ($date) use ($startDate, $endDate, $onlyWeekends) {
                $isInRange = Carbon::parse($date->date)->between($startDate, $endDate);
                $isWeekend = in_array(Carbon::parse($date->date)->dayOfWeek, [CarbonInterface::SATURDAY, CarbonInterface::SUNDAY]);
                return $isInRange && (!$onlyWeekends || $isWeekend);
            });
        }

        $dedupeSubgroups = function ($courseDate) {
            $subgroups = collect();

            if ($courseDate->relationLoaded('courseGroups') && $courseDate->courseGroups) {
                $subgroups = collect($courseDate->courseGroups)->flatMap(function ($group) {
                    return $group->courseSubgroups ?? collect();
                });
            } else {
                $subgroups = collect($courseDate->courseSubgroups);
            }

            return $subgroups
                ->filter(function ($sg) {
                    return !method_exists($sg, 'trashed') || !$sg->trashed();
                })
                ->unique(function ($sg) {
                    $dateKey = $sg->course_date_id ?? 'date';
                    $subKey = $sg->subgroup_dates_id ?? $sg->id ?? spl_object_hash($sg);
                    return $dateKey . '|' . $subKey;
                })
                ->values();
        };

        // Cursos de tipo 1
        if ($course->course_type == 1) {
            if ($course->is_flexible) {
                // Calcular usando subgrupos únicos por fecha para evitar inflar capacidad
                $dateIds = $dates->pluck('id')->all();
                if (!empty($dateIds)) {
                    $totalBookingsPlaces = DB::table('booking_users')
                        ->join('bookings','bookings.id','=','booking_users.booking_id')
                        ->where('booking_users.course_id', $course->id)
                        ->whereIn('booking_users.course_date_id', $dateIds)
                        ->where('booking_users.status', 1)
                        ->where('bookings.status','!=',2)
                        ->count();

                    $allSubgroups = $dates->flatMap(function ($courseDate) use ($dedupeSubgroups) {
                        return $dedupeSubgroups($courseDate);
                    });
                    $totalPlaces = (int) $allSubgroups->sum(function ($sg) {
                        return (int) ($sg->max_participants ?? 0);
                    });

                    foreach ($dates as $courseDate) {
                        $daySubgroups = $dedupeSubgroups($courseDate);
                        $dayCapacity = $daySubgroups->sum(function ($sg) {
                            return (int) ($sg->max_participants ?? 0);
                        });
                        $hoursThisDate = $this->convertSecondsToHours(
                            $this->convertTimeRangeToSeconds($courseDate->hour_start, $courseDate->hour_end)
                        ) * $dayCapacity;
                        $totalHours += $hoursThisDate;
                        $totalAvailableHours += $hoursThisDate;
                    }
                }
                $totalAvailablePlaces = max(0, $totalPlaces - $totalBookingsPlaces);
            } else {
                // âœ… VERIFICAR QUE DATES NO ESTÃ‰ VACÃO ANTES DE ACCEDER
                if ($dates->count() > 0) {

                    // âœ… VERIFICAR QUE TENGA SUBGRUPOS
                    $firstDate = $dates->first();
                    $firstDateSubgroups = $dedupeSubgroups($firstDate);
                    if ($firstDateSubgroups && $firstDateSubgroups->count() > 0) {

                        $bookings = $course->bookingUsers()
                            ->where('status', 1)
                            ->whereHas('booking', function ($query) {
                                $query->where('status', '!=', 2);
                            })
                            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                                $q->whereBetween('date', [$startDate, $endDate]);
                            })
                            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
                            ->get();

                        // Para cursos FIJOS: contar reservas únicas por booking_id, no entradas individuales
                        // Un cliente que reserva un curso fijo tiene 1 reserva pero múltiples booking_users (uno por fecha)
                        $uniqueBookings = $bookings->unique('booking_id');
                        $totalBookingsPlaces += $uniqueBookings->count();

                        // Para cursos NO flexibles, la capacidad es la de un dÃ­a/tanda (no multiplicar por nÂº de fechas)
                        $capPerDate = $firstDateSubgroups->sum(function ($sg) {
                            return (int) ($sg->max_participants ?? 0);
                        });
                        $totalPlaces += $capPerDate;


                    } else {
                        Log::channel('availability')->warning("No subgroups found for course {$course->id}");
                        // Sin subgrupos, no hay disponibilidad
                        $totalHours = 0;
                        $totalPlaces = 0;
                        $totalAvailablePlaces = 0;
                        $totalHoursAvailable = 0;
                    }
                } else {
                    Log::channel('availability')->warning("No dates found after filter for course {$course->id}");

                    // âœ… DEBUG: Â¿POR QUÃ‰ NO HAY FECHAS?
                    Log::channel('availability')->debug("Checking why dates are filtered out:");
                    foreach ($course->courseDates as $courseDate) {
                        $dateCarbon = Carbon::parse($courseDate->date);
                        $isInRange = $dateCarbon->between($startDate, $endDate);
                        $isWeekend = in_array($dateCarbon->dayOfWeek, [CarbonInterface::SATURDAY, CarbonInterface::SUNDAY]);
                        $passesFilter = $isInRange && (!$onlyWeekends || $isWeekend);

                        Log::channel('availability')->debug("Date {$courseDate->date}: in_range={$isInRange}, is_weekend={$isWeekend}, passes_filter={$passesFilter}");
                    }

                    // Sin fechas vÃ¡lidas, no hay disponibilidad
                    $totalHours = 0;
                    $totalPlaces = 0;
                    $totalAvailablePlaces = 0;
                    $totalHoursAvailable = 0;
                }

                $totalAvailablePlaces = $totalPlaces - $totalBookingsPlaces;
            }


        }
        else {
            // Cursos de tipo 2

            $totalPlacesPerHour = $monitorsForSport *
                (1 / $this->convertSecondsToHours(
                    $this->convertDurationToSeconds($course->duration)));


            foreach ($dates as $courseDate) {
                $bookings = $courseDate->bookingUsers()->where('status', 1)->when($onlyWeekends, fn($q) => $q->onlyWeekends())
                    ->whereHas('booking', function ($query) {
                    $query->where('status', '!=', 2); // Excluir reservas canceladas
                })->get();
                $totalBookings += $bookings->count();

                $nwds = MonitorNwd::where('start_date', $courseDate->date)
                    ->where('user_nwd_subtype_id', 2)
                    ->whereIn('monitor_id', collect($monitorsGrouped[$course->sport_id] ?? [])->pluck('id'))
                    ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
                    ->get();

                $bookingUsers = BookingUser::whereIn('monitor_id',
                    collect($monitorsGrouped[$course->sport_id] ?? [])->pluck('id'))
                    ->where('date', $courseDate->date)
                    ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
                    ->where('course_id', $course->id) ->whereHas('booking', function ($query) {
                        $query->where('status', '!=', 2); // La Booking no debe tener status 2
                    })->where('status', 1)
                    ->get();

                $bookingUsersOtherCourses = BookingUser::whereIn('monitor_id',
                    collect($monitorsGrouped[$course->sport_id] ?? [])->pluck('id'))
                    ->whereHas('booking', function ($query) {
                        $query->where('status', '!=', 2); // La Booking no debe tener status 2
                    })->where('status', 1)
                    ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
                    ->where('date', $courseDate->date)->where('course_id', '!=', $course->id)->get();

                $season = Season::whereDate('start_date', '<=', $courseDate->date) // Fecha de inicio menor o igual a hoy
                ->whereDate('end_date', '>=', $courseDate->date)   // Fecha de fin mayor o igual a hoy
                ->first();
                // Si es flexible, contar los intervalos disponibles
                if ($course->is_flexible && $course->price_range) {
                    $minDuration = null;
                    foreach ($course->price_range as $price) {
                        foreach ($price as $participants => $priceValue) {

                            $priceValue = str_replace(',', '.', $priceValue);
                            if (is_numeric($priceValue)) {

                                // Obtener la duraciÃ³n del intervalo en segundos
                                $intervalInSeconds = $this->convertDurationRangeToSeconds($price['intervalo']);
                               // $intervalInSeconds = $this->convertDurationRangeToSeconds("30min");
                                if(!$minDuration) {
                                    $minDuration = $intervalInSeconds;
                                    $totalPlacesPerHour = $monitorsForSport *
                                        (1 /  $this->convertSecondsToHours($minDuration)) * $course->max_participants;

                                    $placesTotalDate = $totalPlacesPerHour *
                                            $this->convertSecondsToHours(
                                                $this->convertTimeRangeToSeconds($courseDate->hour_start, $courseDate->hour_end)
                                            ) * $course->max_participants;


                                    $hoursTotalDate = $monitorsForSport *
                                        $this->convertSecondsToHours(
                                            $this->convertTimeRangeToSeconds($courseDate->hour_start, $courseDate->hour_end)
                                        ) * $course->max_participants;

                                    $totalAvailablePlaces += $placesTotalDate;
                                    $totalPlaces += $placesTotalDate;
                                    $totalHours += $hoursTotalDate;
                                    $totalPlacesHours += $hoursTotalDate;
                                    $totalAvailableHours += $hoursTotalDate;

                                    break 2;
                                }
/*                                if (is_null($durationInSeconds) || $intervalInSeconds < $durationInSeconds) {
                                    $durationInSeconds = $intervalInSeconds;
                                }
                                $start = strtotime($courseDate->hour_min);
                                $end = strtotime($courseDate->hour_max);
                                if ($start && $end) {
                                    while ($start < $end) {
                                        $totalIntervals++;
                                        $start += $intervalInSeconds;
                                    }
                                } else {
                                    $totalIntervals = 5;
                                }*/
                                // Calcular el nÃºmero de intervalos disponibles
                /*                $totalAvailablePlaces += $totalIntervals * $monitorsForSport;
                                $totalPlaces += $totalIntervals * $monitorsForSport;
                                $totalHours += $totalIntervals * $this->convertSecondsToHours($intervalInSeconds);
                                $totalAvailableHours += $totalIntervals * $this->convertSecondsToHours($intervalInSeconds);*/
                                // Romper el bucle una vez que se encuentre un precio numÃ©rico

                            }
                        }
                    }
                }
                else {

                    $placesTotalDate = $this->convertSecondsToHours(
                            $this->convertTimeRangeToSeconds($courseDate->hour_start, $courseDate->hour_end)
                        ) * $totalPlacesPerHour * $course->max_participants;

                    $hoursTotalDate =  $this->convertSecondsToHours(
                        $this->convertTimeRangeToSeconds($courseDate->hour_start, $courseDate->hour_end)
                    * $monitorsForSport * $course->max_participants);

                    $totalAvailablePlaces += $placesTotalDate;
                    $totalPlaces += $placesTotalDate;
                    $totalHours += $hoursTotalDate;
                    $totalPlacesHours += $hoursTotalDate;
                    $totalAvailableHours += $hoursTotalDate;

                }

                foreach ($bookingUsers as $bookingUser) {

                    $placesTotalDate = $this->convertSecondsToHours(
                            $this->convertTimeRangeToSeconds($bookingUser->hour_start, $bookingUser->hour_end)
                        );
                    $hoursTotalDate =  $this->convertSecondsToHours(
                        $this->convertTimeRangeToSeconds($bookingUser->hour_start, $bookingUser->hour_end));



                    $totalPlaces -= $placesTotalDate;
                    $totalAvailablePlaces -= $placesTotalDate;
                    $totalPlacesHours -= $hoursTotalDate;
                    $totalAvailableHours -= $hoursTotalDate;
                    $totalBookingsPlaces += $placesTotalDate;
                    $totalBookingsHours += $hoursTotalDate;

                }

                foreach ($nwds as $nwd) {
                    $start =$nwd->hour_start ?? $season->hour_start;
                    $end = $nwd->end_time ?? $season->hour_end;
                    $placesTotalDate = $this->convertSecondsToHours(
                            $this->convertTimeRangeToSeconds($start, $end)
                        );
                    $hoursTotalDate =  $this->convertSecondsToHours(
                        $this->convertTimeRangeToSeconds($start, $end));

                    $totalPlaces -= $placesTotalDate;
                    $totalAvailablePlaces -= $placesTotalDate;
                    $totalPlacesHours -= $hoursTotalDate;
                    $totalAvailableHours -= $hoursTotalDate;
                }

                if($bookingUsersOtherCourses->count()){
                    foreach ($bookingUsersOtherCourses as $bookingUser) {
                        $placesTotalDate = $this->convertSecondsToHours(
                                $this->convertTimeRangeToSeconds($bookingUser->hour_start, $bookingUser->hour_end)
                            ) ;
                        $hoursTotalDate =  $this->convertSecondsToHours(
                            $this->convertTimeRangeToSeconds($bookingUser->hour_start, $bookingUser->hour_end));
                       // dd($placesTotalDate);
                       // $totalPlaces -= $placesTotalDate;
                        $totalAvailablePlaces -= $placesTotalDate;
                        $totalPlacesHours -= $hoursTotalDate;
                        //$totalHours -= $hoursTotalDate;
                        $totalAvailableHours -= $hoursTotalDate;
                    }
                }
            }
        }


        return [
            'total_reservations_places' => $totalBookingsPlaces,
            'total_reservations_hours' => $totalBookingsHours,
            'total_available_places' => $totalAvailablePlaces,
            'total_places' => $totalPlaces,
            'total_places_hours' => $totalPlacesHours,
            'total_available_hours' => $totalAvailableHours,
            'total_hours' => $totalHours
        ];
    }

    private function convertTimeRangeToSeconds($startTime, $endTime)
    {
        // Convertir horas a segundos
        $startSeconds = strtotime($startTime) - strtotime("TODAY");
        $endSeconds = strtotime($endTime) - strtotime("TODAY");

        // Calcular la diferencia en segundos
        $intervalInSeconds = $endSeconds - $startSeconds;

        return $intervalInSeconds;
    }


    private function convertSecondsToHours($seconds)
    {
        return $seconds / 3600;
    }

    public function getMonitorAvailabilityForCourse($course, $startDate, $endDate)
    {
        $schoolId = $course->school_id;
        $courseId = $course->id;

        // Obtener la temporada actual
        $season = Season::whereDate('start_date', '<=', $startDate)
            ->whereDate('end_date', '>=', $endDate)
            ->first();

        // Obtener reservas de monitores excluyendo el curso actual
        $monitorBookings = BookingUser::where('school_id', $schoolId)
            ->whereHas('course', function ($query) use($courseId) {
                $query->where('course_id', '!=', $courseId);
            })
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })->where('status', 1)
            ->where('monitor_id', '!=', null)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        // Obtener bloqueos (nwds) de monitores en el mismo intervalo de tiempo
        $monitorNwds = MonitorNwd::where('school_id', $schoolId)
            ->where('user_nwd_subtype_id', 2)
            ->where('monitor_id', '!=', null)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get();

        return compact('monitorBookings', 'monitorNwds', 'season');
    }


    private function convertDurationToSeconds($duration)
    {
        if (strpos($duration, 'h') !== false) {
            // Si el formato es "Xh" o "Xh Ymin", convertirlo a segundos
            preg_match('/(\d+)h(?: (\d+)min)?/', $duration, $matches);
            $hours = intval($matches[1]); // Captura la cantidad de horas
            $minutes = isset($matches[2]) ? intval($matches[2]) : 0; // Captura los minutos si existen
            return ($hours * 3600) + ($minutes * 60);
        } elseif (strpos($duration, 'min') !== false) {
            // Si el formato es solo "Ymin", convertirlo a segundos
            preg_match('/(\d+)min/', $duration, $matches);
            $minutes = intval($matches[1]);
            return $minutes * 60;
        } elseif (is_numeric($duration)) {            // Si el formato es solo números, asumimos que son minutos            $minutes = intval($duration);            return $minutes * 60;        } else {
            // Si el formato es "HH:mm:ss", convertirlo a segundos
            $time = explode(':', $duration);
            $hours = intval($time[0]);
            $minutes = isset($time[1]) ? intval($time[1]) : 0;
            $seconds = isset($time[2]) ? intval($time[2]) : 0;
            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }
    }

    private function calculateDuration($start, $end): ?string
    {
        if ($start && $end) {
            $startTime = \Carbon\Carbon::parse($start);
            $endTime = \Carbon\Carbon::parse($end);
            return $startTime->diff($endTime)->format('%H:%I:%S');
        }
        return null;
    }

    private function convertDurationRangeToSeconds($duration)
    {
        if (strpos($duration, 'h') !== false) {
            // Si el formato es "Xh Ymin" o "Xh Ym" o "Xh", convertirlo a segundos
            preg_match('/(\d+)h(?: (\d+)(?:min|m))?/', $duration, $matches);
            if (!empty($matches[1])) {
                $hours = intval($matches[1]);
                $minutes = isset($matches[2]) ? intval($matches[2]) : 0; // Si no hay minutos, establecer en 0
                return ($hours * 3600) + ($minutes * 60);
            }
        } elseif (strpos($duration, 'min') !== false || strpos($duration, 'm') !== false) {
            // Si el formato es solo "Ymin" o "Ym", convertirlo a segundos
            preg_match('/(\d+)(?:min|m)/', $duration, $matches);
            if (!empty($matches[1])) {
                $minutes = intval($matches[1]);
                return $minutes * 60;
            }
        }

        // Si no se pudo convertir, devolver 0 segundos
        return 0;
    }

    public function getGroupedMonitors($schoolId, $monitorId = null, $sportId = null)
    {
        $totalMonitors = Monitor::with(['monitorSportsDegrees.monitorSportAuthorizedDegrees.degree'])
            ->whereHas('monitorsSchools', function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId)->where('active_school', 1);
            })
            ->when($monitorId, function ($query) use ($monitorId) {
                $query->where('id', $monitorId);
            })
            ->when($sportId, function ($query) use ($sportId) {
                $query->whereHas('monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($sportId) {
                    $query->where('sport_id', $sportId);
                });
            })
            ->get();

        $monitorsBySport = [];

        // Itera a travÃ©s de los monitores
        foreach ($totalMonitors as $monitor) {
            // Itera a travÃ©s de los deportes del monitor
            foreach ($monitor->sports as $sport) {
                // Agrupa por deporte
                $monitorsBySport[$sport->id][] = $monitor;
            }
        }

        return $monitorsBySport;
    }


    public function calculateTotalPrice(BookingUser $bookingUser, ?Collection $bookingGroupedUsers = null): array
    {
        $course = $bookingUser->course;
        $courseType = $course->course_type;
        $isFlexible = $course->is_flexible;

        $totalPrice = 0;

        if ($courseType === 1) { // Colectivo
            $totalPrice = $isFlexible
                ? $this->calculateFlexibleCollectivePrice($bookingUser, $bookingGroupedUsers)
                : $this->calculateFixedCollectivePrice($bookingUser);

        } elseif ($courseType === 2) { // Privado
            $totalPrice = $isFlexible
                ? $this->calculatePrivatePrice($bookingUser, $course->price_range ?? [])
                : ($course->price ?? 0);
        } else {
            Log::channel('availability')->warning("Tipo de curso invÃ¡lido: {$courseType}");
            return [
                'priceWithoutExtras' => 0,
                'totalPrice' => 0,
                'extrasPrice' => 0,
                'cancellationInsurancePrice' => 0,
            ];
        }

        $extrasPrice = $this->calculateExtrasPrice($bookingUser);
        $cancellationInsurancesPrice = 0;

        if ($bookingUser->booking && $bookingUser->booking->has_cancellation_insurance) {
            $cancellationInsurancesPrice = ($totalPrice + $extrasPrice) * 0.10;
        }

        $finalPrice = $totalPrice + $extrasPrice + $cancellationInsurancesPrice;

        return [
            'priceWithoutExtras' => $totalPrice,
            'totalPrice' => $finalPrice,
            'extrasPrice' => $extrasPrice,
            'cancellationInsurancePrice' => $cancellationInsurancesPrice,
        ];
    }

    public function calculateFixedCollectivePrice(BookingUser $bookingUser): float
    {
        return $bookingUser->course->price ?? 0;
    }

    public function calculateFlexibleCollectivePrice(BookingUser $bookingUser, ?Collection $bookingGroupedUsers = null): float
    {
        $course = $bookingUser->course;

        if (!$course) {
            return 0.0;
        }

        if ($bookingGroupedUsers instanceof Collection) {
            $clientBookingUsers = $bookingGroupedUsers
                ->where('client_id', $bookingUser->client_id);
        } else {
            $clientBookingUsers = BookingUser::where('course_id', $course->id)
                ->where('client_id', $bookingUser->client_id)
                ->where('status', '!=', 2)
                ->get();
        }

        return IntervalDiscountHelper::calculateFlexibleCollectivePrice($course, collect($clientBookingUsers));
    }

    public function calculatePrivatePrice(BookingUser $bookingUser, array $priceRange): float
    {
        $course = $bookingUser->course;

        $groupCount = BookingUser::where('course_id', $course->id)
            ->where('date', $bookingUser->date)
            ->where('hour_start', $bookingUser->hour_start)
            ->where('hour_end', $bookingUser->hour_end)
            ->where('monitor_id', $bookingUser->monitor_id)
            ->where('group_id', $bookingUser->group_id)
            ->where('booking_id', $bookingUser->booking_id)
            ->where('school_id', $bookingUser->school_id)
            ->where('status', 1)
            ->count();

        $duration = Carbon::parse($bookingUser->hour_end)->diffInMinutes(Carbon::parse($bookingUser->hour_start));
        $interval = $this->getIntervalFromDuration($duration);

        $priceData = collect($priceRange)->firstWhere('intervalo', $interval);
        $pricePerParticipant = $priceData[$groupCount] ?? null;

        if (!$pricePerParticipant) {
            Log::channel('availability')->debug("Precio no definido para curso {$course->id} con $groupCount participantes en intervalo $interval");
            return 0;
        }

        return $pricePerParticipant;
    }

    public function calculateExtrasPrice(BookingUser $bookingUser): float
    {
        return $bookingUser->bookingUserExtras->sum(function ($extra) {
            return $extra->courseExtra->price ?? 0;
        });
    }

    public function getIntervalFromDuration(int $duration): ?string
    {
        return [
            15 => "15m",
            30 => "30m",
            45 => "45m",
            60 => "1h",
            75 => "1h 15m",
            90 => "1h 30m",
            120 => "2h",
            180 => "3h",
            240 => "4h",
        ][$duration] ?? null;
    }

}

