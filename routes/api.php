<?php

use App\Exports\CoursesExport;
use App\Exports\UsedVouchersExport;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\FinanceControllerRefactor;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\API\VoucherAPIController;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\ClientSport;
use App\Models\ClientsSchool;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseGroup;
use App\Models\CourseSubgroup;
use App\Models\Degree;
use App\Models\Language;
use App\Models\Mail;
use App\Models\Monitor;
use App\Models\MonitorNwd;
use App\Models\MonitorSportAuthorizedDegree;
use App\Models\MonitorSportsDegree;
use App\Models\MonitorsSchool;
use App\Models\OldModels\UserSport;
use App\Models\Station;
use App\Models\StationService;
use App\Models\User;
use App\Support\IntervalDiscountHelper;
use App\Traits\Utils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use Payrexx\Models\Request\Gateway as GatewayRequest;
use Payrexx\Models\Request\Transaction as TransactionRequest;
use Payrexx\Models\Response\Transaction as TransactionResponse;
use Payrexx\Payrexx;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// App Version Check - Public endpoint (no auth required)
Route::get('/app-version', [App\Http\Controllers\API\AppVersionController::class, 'getVersion']);

// ENDPOINT ASEGURADO: Solo superadmin puede ejecutar comandos del sistema
Route::any('/users-permissions', function () {
    // Verificar autenticación y permisos de superadmin
    $user = auth('sanctum')->user();
    if (!$user || !$user->hasRole('superadmin')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Superadmin access required.'
        ], 403);
    }
    
    try {
        // Usar Artisan facade en lugar de shell_exec para mayor seguridad
        \Illuminate\Support\Facades\Artisan::call('permission:cache-reset');
        $output = \Illuminate\Support\Facades\Artisan::output();
        
        // Asigna permisos iniciales a los usuarios
        foreach (User::all() as $user) {
            $user->setInitialPermissionsByRole();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Permisos actualizados correctamente',
            'output' => $output
        ]);
        
    } catch (Exception $e) {
        \Log::error('Error updating permissions: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al asignar permisos: ' . $e->getMessage()
        ], 500);
    }
})->middleware('auth:sanctum');

Route::get('/export/vouchers-used', function (Request $request) {
    $request->validate([
        'from' => 'required|date',
        'to' => 'required|date|after_or_equal:from',
    ]);

    $from = $request->input('from');
    $to = $request->input('to');

    return (new UsedVouchersExport($from, $to))->download('courses_export.xlsx');
});



Route::any('/fix-subgroups', function () {
    $subGroups = CourseSubgroup::whereHas('courseDate', function ($query) {
        $query->where('date', '>=', Carbon::today());
    });

    foreach ($subGroups as $subGroup) {
        // Buscar todas las BookingUser relacionadas con el subgrupo
        $bookingUsers = BookingUser::where('course_subgroup_id', $subGroup->id)->get();

        foreach ($bookingUsers as $bookingUser) {
            // Actualizar el monitor_id de cada BookingUser
            $bookingUser->monitor_id = $subGroup->monitor_id;
            $bookingUser->save();
        }
    }
    return 'Subgroups fixed';
});

// Descargar archivo exportado
Route::get('/admin/finance/download-export/{filename}', [FinanceController::class, 'downloadExport'])
    ->name('finance.download-export');

Route::get('/admin/finance/debug-pending', [FinanceController::class, 'debugPendingDiscrepancy']);
Route::get('/download-export/{filename}', [FinanceController::class, 'downloadExport'])
    ->name('finance.download-export');

Route::get('/debug-books', [StatisticsController::class, 'debugSpecificBookings']);
Route::get('/debug-test-detection', [FinanceController::class, 'debugTestDetection']);
Route::get('/finance/season-dashboard', [FinanceController::class, 'getSeasonFinancialDashboard']);
Route::get('/finance/export-dashboard', [FinanceController::class, 'exportSeasonDashboard']);

Route::get('/debug-bookings', [\App\Http\Controllers\Admin\FinanceController::class, 'getCompleteFinancialAnalysis']);
Route::get('bookings/{id}/financial-debug', [FinanceController::class, 'getBookingFinancialDebug'])
    ->name('bookings.financial-debug');

Route::get('/debug-booking-users', function () {
    $from = '2025-01-01';
    $to = '2025-06-30';
    $schoolId = 2;

    $users = BookingUser::whereHas('booking', function ($q) use ($schoolId) {
        $q->where('school_id', $schoolId)->where('status', '!=', 'cancelled');
    })
        ->whereBetween('date', [$from, $to])
        ->with(['course', 'booking', 'bookingUserExtras.courseExtra'])
        ->get();

    $debug = [];

    foreach ($users as $user) {
        $calc = (new class { use Utils; })->calculateTotalPrice($user);

        $debug[] = [
            'id' => $user->id,
            'course_id' => $user->course_id,
            'client_id' => $user->client_id,
            'date' => $user->date,
            'base' => $calc['priceWithoutExtras'],
            'extras' => $calc['extrasPrice'],
            'insurance' => $calc['cancellationInsurancePrice'],
            'total' => $calc['totalPrice'],
        ];
    }

    return response()->json($debug);
});

Route::any('/fix-bookings',
    function () {
        $inicioAnio = Carbon::createFromFormat('Y-m-d', date('Y') . '-01-01');

        // Buscar los duplicados agrupados
        $duplicados = BookingUser::select(
            'course_date_id',
            'client_id',
            'hour_start',
            'hour_end'
        )
            ->whereNull('deleted_at')
            ->groupBy('course_date_id', 'client_id', 'hour_start', 'hour_end')
            ->havingRaw('COUNT(*) > 1')
            ->where('school_id', '!=', 1)
            ->where('status', 1)
            ->where('date', '>=', $inicioAnio)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->get();

        // Crear un array para almacenar los grupos
        $grupos = [];


        foreach ($duplicados as $dup) {
            // Obtener todas las reservas duplicadas
            $bookingUsers = BookingUser::whereNull('deleted_at')
                ->with('courseGroup.bookingUsers')
                ->where('course_date_id', $dup->course_date_id)
                ->where('client_id', $dup->client_id)
                ->where('hour_start', $dup->hour_start)
                ->where('hour_end', $dup->hour_end)
                ->where('school_id', '!=', 1)
                ->where('status', 1)
                ->where('date', '>=', $inicioAnio)
                ->whereHas('booking', function ($query) {
                    $query->where('status', '!=', 2); // La Booking no debe tener status 2
                })
                ->get();
            foreach ($bookingUsers as $bookingUser) {

                // Si solo hay una reserva en este grupo, eliminarla junto con su grupo y subgrupos
                if ($bookingUser->courseGroup && $bookingUser->courseGroup->bookingUsers->count() == 1) {
                    $booking = $bookingUser; // Obtener la única reserva
                    $courseGroupId = $booking->course_group_id;
                    $courseSubgroupId = $booking->course_subgroup_id;

                    DB::transaction(function () use ($booking, $courseGroupId, $courseSubgroupId) {
                        // Eliminar la reserva
                        $booking->delete();

                        // Si existe un grupo asociado y ya no tiene más reservas, eliminarlo
                        if ($courseGroupId) {
                            $remainingBookings = BookingUser::where('course_group_id', $courseGroupId)->count();
                            if ($remainingBookings == 0) {
                                CourseGroup::where('id', $courseGroupId)->delete();
                            }
                        }

                        // Si existe un subgrupo asociado y ya no tiene más reservas, eliminarlo
                        if ($courseSubgroupId) {
                            $remainingSubgroupBookings = BookingUser::where('course_subgroup_id', $courseSubgroupId)->count();
                            if ($remainingSubgroupBookings == 0) {
                                CourseSubgroup::where('id', $courseSubgroupId)->delete();
                            }
                        }
                    });
                } else {
                    // Solo agregar al array los grupos que tienen más de una reserva
                    $clave = "{$dup->course_date_id}-{$dup->client_id}-{$dup->hour_start}-{$dup->hour_end}";
                    $grupos[$clave] = $bookingUsers;
                }
            }
        }

        return response()->json($grupos);


    });
Route::any('/fix-dates', function () {

    DB::beginTransaction();

    try {
        // 1. Encontrar las course_dates duplicadas (mismo course_id y date) - SEGURO
        $duplicates = DB::table('course_dates')
            ->select('course_id', 'date', DB::raw('MIN(id) as keep_id'))
            ->groupBy('course_id', 'date')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $keepId = $dup->keep_id;

            // 2. Obtener todas las course_dates duplicadas excepto la que se quedará
            $toDelete = DB::table('course_dates')
                ->where('course_id', $dup->course_id)
                ->where('date', $dup->date)
                ->where('id', '!=', $keepId)
                ->pluck('id');

            if ($toDelete->isEmpty()) {
                continue;
            }

            // 3. Actualizar booking_users para apuntar al nuevo course_date_id
            DB::table('booking_users')
                ->whereIn('course_date_id', $toDelete)
                ->update(['course_date_id' => $keepId]);

            // 4. Actualizar course_groups y course_subgroups relacionados con booking_users
            $bookingUserDegrees = DB::table('booking_users')
                ->where('course_date_id', $keepId)
                ->pluck('degree_id')
                ->unique();

            DB::table('course_groups')
                ->whereIn('degree_id', $bookingUserDegrees)
                ->whereIn('course_date_id', $toDelete)
                ->update(['course_date_id' => $keepId]);

            DB::table('course_subgroups')
                ->whereIn('degree_id', $bookingUserDegrees)
                ->whereIn('course_date_id', $toDelete)
                ->update(['course_date_id' => $keepId]);

            // 5. Eliminar los course_groups y course_subgroups de las fechas eliminadas
            DB::table('course_groups')
                ->whereIn('course_date_id', $toDelete)
                ->delete();

            DB::table('course_subgroups')
                ->whereIn('course_date_id', $toDelete)
                ->delete();

            // 6. Eliminar course_dates que no tengan booking_users
            DB::table('course_dates')
                ->whereIn('id', $toDelete)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('booking_users')
                        ->whereRaw('booking_users.course_date_id = course_dates.id');
                })
                ->delete();
        }

        DB::commit();
        return 'Gool';
    } catch (\Exception $e) {
        DB::rollBack();
        return 'Error';
    }
});


/*Route::any('/degrees-update', function () {

    // Paso 1: Obtener todos los grados con school_id = 2
    $degreesToDuplicate = Degree::where('school_id', 2)->get();

    // Verificar si hay grados a duplicar
    if ($degreesToDuplicate->isEmpty()) {
        return response()->json(['message' => 'No degrees found for school_id = 2'], 404);
    }

    // Array para almacenar la correlación entre el ID antiguo y el nuevo
    $degreeMapping = [];

    // Paso 2: Crear nuevos grados y almacenar la correlación
    foreach ($degreesToDuplicate as $degree) {
        // Crear un nuevo Degree con school_id = 12
        $newDegree = Degree::create([
            'league' => $degree->league,
            'level' => $degree->level,
            'image' => $degree->image,
            'name' => $degree->name,
            'annotation' => $degree->annotation,
            'degree_order' => $degree->degree_order,
            'progress' => $degree->progress,
            'color' => $degree->color,
            'age_min' => $degree->age_min,
            'age_max' => $degree->age_max,
            'active' => $degree->active,
            'school_id' => 12, // Nuevo school_id
            'sport_id' => $degree->sport_id, // Asumiendo que sport_id se mantiene igual
        ]);

        // Almacenar la correlación
        $degreeMapping[$degree->id] = $newDegree->id; // 'antiguo_id' => 'nuevo_id'
    }

    // Paso 3: Obtener todos los MonitorSportsDegree con school_id = 2
    $monitorSportsDegrees = MonitorSportsDegree::where('school_id', 2)->get();

    // Verificar si hay MonitorSportsDegrees para duplicar
    if ($monitorSportsDegrees->isEmpty()) {
        return response()->json(['message' => 'No MonitorSportsDegrees found for school_id = 2'], 404);
    }

    // Paso 4: Crear nuevos MonitorSportsDegrees y duplicar las autorizaciones
    foreach ($monitorSportsDegrees as $monitorSportsDegree) {
        // Crear un nuevo MonitorSportsDegree con el nuevo degree_id
        $newMonitorSportsDegree = MonitorSportsDegree::create([
            'sport_id' => $monitorSportsDegree->sport_id,
            'school_id' => 12,
            'degree_id' => $degreeMapping[$monitorSportsDegree->degree_id] ?? null, // Usar el nuevo degree_id
            'monitor_id' => $monitorSportsDegree->monitor_id,
            'salary_level' => $monitorSportsDegree->salary_level,
            'allow_adults' => $monitorSportsDegree->allow_adults,
            'is_default' => $monitorSportsDegree->is_default,
        ]);

        // Paso 5: Obtener los MonitorSportAuthorizedDegrees asociados al antiguo MonitorSportsDegree
        $authorizedDegrees = MonitorSportAuthorizedDegree::where('monitor_sport_id', $monitorSportsDegree->id)->get();

        // Paso 6: Duplicar los MonitorSportAuthorizedDegrees
        foreach ($authorizedDegrees as $authorizedDegree) {
            MonitorSportAuthorizedDegree::create([
                'monitor_sport_id' => $newMonitorSportsDegree->id, // Nuevo MonitorSportsDegree
                'degree_id' => $degreeMapping[$authorizedDegree->degree_id] ?? null, // Usar el nuevo degree_id
            ]);
        }
    }

});

Route::any('/update-clients', function () {
    $clients = Client::all();  // Esto obtiene todos los clientes de la base de datos

    // Iterar sobre todos los clientes
    foreach ($clients as $client) {
        // Verificar si el cliente no tiene un email asignado
        if (!$client->email) {
            // Obtener el cliente principal asociado al cliente actual (utilizer)
            $mainClient = $client->main;  // Usamos la relación 'main' para obtener el cliente principal

            // Verificar si el cliente principal existe y tiene un email
            if ($mainClient && $mainClient->email) {
                // Actualizar el email del cliente
                $client->email = $mainClient->email;
                $client->save();  // Guardar los cambios en el cliente

                // Actualizar el correo en el objeto 'User' si es necesario
                if ($client->user && !$client->user->email) {
                    $client->user->email = $mainClient->email;
                    $client->user->save();  // Guardar el correo en el usuario asociado
                }
            }
        }
    }

    // Retornar una respuesta exitosa
    return response()->json(['message' => 'All clients updated successfully']);
});


Route::any('/testAval', function (Request $request) {

    $date = $request->input('date');
    $schoolId = 8;

    // Obtén todos los monitores de la escuela
    $monitors = MonitorsSchool::with(['monitor.sports', 'monitor.courseSubgroups'])
        ->where('school_id', $schoolId)
        ->where('active_school', 1)
        ->get()
        ->pluck('monitor');

    $availableMonitors = [];

    foreach ($monitors as $monitor) {
        // Obtén los NWDS del monitor para ese día
        $nwds = MonitorNwd::where('monitor_id', $monitor->id)
            ->where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        // Obtén los subgrupos asignados al monitor para ese día
        $subgroups = $monitor->courseSubgroups->filter(function ($subgroup) use ($date) {
            return $subgroup->courseDate->date == $date;
        });

        // Define los rangos de horas disponibles para el monitor
        $availableHours = [];

        // Agrega el rango de horas de NWDS al arreglo de horas disponibles
        foreach ($nwds as $nwd) {
            $availableHours[] = [
                'start_time' => $nwd->start_time,
                'end_time' => $nwd->end_time,
            ];
        }

        // Agrega los rangos de horas de los subgrupos al arreglo de horas disponibles
        foreach ($subgroups as $subgroup) {
            $availableHours[] = [
                'start_time' => $subgroup->start_time,
                'end_time' => $subgroup->end_time,
            ];
        }


        $combinedHours = [];

        // Combinar los rangos de horas solapados
        foreach ($availableHours as $hour) {
            $added = false;
            foreach ($combinedHours as &$combinedHour) {
                if ($hour['start_time'] <= $combinedHour['end_time'] && $hour['end_time'] >= $combinedHour['start_time']) {
                    // Solapamiento encontrado, combina los rangos
                    $combinedHour['start_time'] = min($hour['start_time'], $combinedHour['start_time']);
                    $combinedHour['end_time'] = max($hour['end_time'], $combinedHour['end_time']);
                    $added = true;
                    break;
                }
            }
            if (!$added) {
                // No se encontró solapamiento, agrega el rango como nuevo
                $combinedHours[] = $hour;
            }
        }

        // Ordenar los rangos combinados por hora de inicio
        usort($combinedHours, function ($a, $b) {
            return $a['start_time'] <=> $b['start_time'];
        });

        $availableMonitors[] = [
            'monitor' => $monitor,
            'available_hours' => $combinedHours,
        ];
    }

    return collect($availableMonitors);

});

Route::any('/monitors-active', function () {
    $monitors = Monitor::with('monitorsSchools.school.stationsSchools')
        ->get();
    foreach ($monitors as $monitor) {
        if(isset($monitor->monitorsSchools[0])) {
            $monitor->active_school =  $monitor->monitorsSchools[0]->school->id;
        }
        if(isset($monitor->monitorsSchools[0]->school->stationsSchools[0])) {
            $monitor->active_station =  $monitor->monitorsSchools[0]->school->stationsSchools[0]->station_id;
        }
        $monitor->save();

    }
});

Route::any('/clients-active', function () {
    $clients = Client::with('utilizers.clientsSchools', 'clientSports.degree', 'clientSports.sport', 'clientsSchools')
        ->get();
    foreach ($clients as $client) {
        if($client->utilizers->count()){
            foreach ($client->utilizers as $utilizer) {
                $clientSchoolIds = $client->clientsSchools->pluck('school_id')->toArray();
                $utilizerSchoolIds = $utilizer->clientsSchools->pluck('school_id')->toArray();

                foreach ($clientSchoolIds as $clientSchoolId) {
                    if (!in_array($clientSchoolId, $utilizerSchoolIds)) {
                        // El utilizador no tiene este school_id, así que lo creamos
                        ClientsSchool::create([
                            'client_id' => $utilizer->id,
                            'school_id' => $clientSchoolId,
                            'accepted_at' => Carbon::now()
                        ]);
                    }
                }
                foreach ($utilizerSchoolIds as $utilizerSchoolId) {
                    if (!in_array($utilizerSchoolId, $clientSchoolIds)) {
                        ClientsSchool::create([
                            'client_id' => $client->id,
                            'school_id' => $utilizerSchoolId,
                            'accepted_at' => Carbon::now()
                        ]);
                    }
                }
            }

        }

    }
});*/

/*Route::get('/refund', function () {

    $transactionID = '13023439';
    $bookingData = \App\Models\Booking::with('school')->find(2103);
    $schoolData = $bookingData->school;
    $tr = new TransactionRequest();
    $tr->setId($transactionID);
    $tr->setAmount(500 * 100);
    $tr->setCurrency($bookingData->currency);

    $payrexx = new Payrexx(
        'pruebas',
        'vgJrvQ7AYKzpiqmreocpeGYtjFTX39',
        '',
        env('PAYREXX_API_BASE_DOMAIN')
    );
    $response = $payrexx->refund($tr);

    // Status will be "refunded" if the amount was the whole Booking price,
    // or "partially refunded" if just some of its BookingUsers
    $responseStatus = $response->getStatus() ?? '';

    return ($responseStatus == TransactionResponse::REFUNDED ||
        $responseStatus == TransactionResponse::PARTIALLY_REFUNDED);
});

Route::any('/update-clients-schools', function () {

    $oldUserSports = UserSport::whereNotNull('school_id')->get();
    $oldDegrees = \App\Models\OldModels\Degree::all();

    foreach ($oldUserSports as $oldUserSport) {
        $client = Client::where('old_id', $oldUserSport->user_id)->first();
        $oldDegree = $oldDegrees->firstWhere('id', $oldUserSport->degree_id);
        $newDegree = Degree::where('degree_order', $oldDegree->degree_order)
            ->where('school_id', $oldUserSport->school_id)->where('sport_id', $oldUserSport->sport_id)
            ->first();

        if($client) {
            // Encuentra el registro correspondiente en ClientSport
            $clientSports = ClientSport::where('client_id', $client->id)
                ->where('sport_id', $oldUserSport->sport_id)
                ->where('degree_id', $newDegree->id)
                ->get();



            foreach ($clientSports as $clientSport) {
                // Actualiza el campo school_id en ClientSport con el valor de UserSport
                $clientSport->update(['school_id' => $oldUserSport->school_id ?? 1]);
            }
        }

    }

    return response()->json(['message' => 'School IDs updated successfully']);
});*/

Route::any('/mailtest/{bookingId}', function ($bookingId) {

    $bookingData = \App\Models\Booking::find($bookingId);
    $bookingData->loadMissing(['bookingUsers',
        'bookingUsers.client.language1',
        'bookingUsers.degree',
        'bookingUsers.monitor.language1',
        'bookingUsers.courseExtras',
        'bookingUsers.courseSubGroup',
        'bookingUsers.course',
        'bookingUsers.courseDate']);
    $schoolData = \App\Models\School::find(1);
    $userData = Client::find($bookingData->client_main_id);
    //return response()->json($bookingData);
    // Apply that user's language - or default
    $defaultLocale = config('app.fallback_locale');
    $oldLocale = \App::getLocale();
    $userLang = Language::find($userData->language1_id);
    $userLocale = $userLang ? $userLang->code : $defaultLocale;

    \App::setLocale($userLocale);


    $templateView = 'mails.bookingPay';
    $templateView = 'mailsv2.newBookingCreate';

    $footerView = 'mailsv2.newFooter';

    $templateMail = Mail::where('type', 'booking_create')->where('school_id', $schoolData->id)
        ->where('lang', $userLocale)->first();

    $voucherCode = "";
    if (isset($voucherData->code)) $voucherCode = $voucherData->code;
    $voucherAmount = "";
    if (isset($voucherData->quantity)) $voucherAmount = number_format($voucherData->quantity, 2);


    $templateData = [
        'titleTemplate' => $templateMail->title ?? '',
        'bodyTemplate' => $templateMail->body ?? '',
        'userName' => trim($userData->first_name . ' ' . $userData->last_name),
        'schoolName' => $schoolData->name,
        'schoolDescription' => $schoolData->description,
        'schoolLogo' => $schoolData->logo,
        'schoolEmail' => $schoolData->contact_email,
        'schoolPhone' => $schoolData->contact_phone,
        'schoolConditionsURL' => $schoolData->conditions_url,
        'reference' =>  $bookingData->id,
        'bookingNotes' => $bookingData->notes,
        'courses' => $bookingData->parseBookedGroupedWithCourses(),
        'bookings' => $bookingData->bookingUsers,
        'booking' => $bookingData,
        'voucherCode' => $voucherCode,
        'voucherAmount' => $voucherAmount,
        'hasCancellationInsurance' => false,
        'amount' => number_format($bookingData->price_total, 2),
        'currency' => $bookingData->currency,
        'paid' => $bookingData->paid,
        'actionURL' => 'test',
        'footerView' => $footerView
    ];

    //dd($templateData['courses']);


    //  $subject = __('emails.bookingInfo.subject');
    //\App::setLocale($oldLocale);

    return view($templateView)->with($templateData);
});

Route::any('/mailtest', function () {

    $user = User::find(8560);
    $defaultLocale = config('app.fallback_locale');
    $oldLocale = \App::getLocale();
    $userLang = Language::find($user->language1_id);
    $userLocale = $userLang ? $userLang->code : $defaultLocale;
    \App::setLocale($userLocale);

    $templateView = 'mails.recoverPassword'; // Load the view file directly
    $footerView = 'mails.footer'; // Load the view file directly

    $templateData = [
        'userName' => trim($user->first_name . ' ' . $user->last_name),
        'actionURL' => env('APP_RESETPASSWORD_URL') . '/' . $user->recover_token .'?user='. $user->id ,
        'footerView' => $footerView,

        // SCHOOL DATA - none
        'schoolName' => '',
        'schoolLogo' => '',
        'schoolEmail' => '',
        'schoolConditionsURL' => '',
    ];

    $subject = __('emails.recoverPassword.subject');
    \App::setLocale($oldLocale);

    return  view($templateView)->with($templateData);
});

Route::post('/testPayrexx', function (Request $request) {

    $schoolData = \App\Models\School::find($request->school_id);
    $schoolData->setPayrexxInstance($request->payrexx_instance);
    $schoolData->setPayrexxKey($request->payrexx_key);
    $schoolData->save();

    if (!$schoolData->getPayrexxInstance() || !$schoolData->getPayrexxKey()) {
        return 'School Data f';
    }
    try {
        $gr = new GatewayRequest();
        $ref = 'Boukii #' . $this->id;
        $ref = (env('APP_ENV') == 'production') ? $ref : 'TEST ' . $ref;
        $gr->setReferenceId($ref);
        return 'Payrexx works';
    } catch (\Throwable $th) {
        return 'School Data f works';
    }

});

Route::post('/schools/{schoolId}/normalize-courses',  function (Request $request, $schoolId) {
    $courses = Course::with(['courseDates.courseGroups.courseSubgroups'])
        ->where('school_id', $schoolId)
        ->where('course_type', 1) // Solo cursos colectivos
        ->get();

    if ($courses->isEmpty()) {
        return response()->json(['message' => 'No se encontraron cursos colectivos para esta escuela.'], 404);
    }

    foreach ($courses as $course) {
        // Obtener todas las fechas del curso
        $courseDates = $course->courseDates;

        if ($courseDates->isEmpty()) {
            // Si el curso no tiene fechas, pasamos al siguiente
            Log::warning("El curso ID {$course->id} no tiene fechas.");
            continue;
        }

        // Obtener los grupos y subgrupos de referencia (de la primera fecha)
        $referenceGroups = $courseDates->first()->courseGroups;

        foreach ($courseDates as $courseDate) {
            foreach ($referenceGroups as $referenceGroup) {
                // Verificar si el grupo existe en la fecha actual
                $group = $courseDate->courseGroups->where('degree_id', $referenceGroup->degree_id)->first();

                if (!$group) {
                    // Crear el grupo si no existe
                    $group = CourseGroup::create([
                        'course_id' => $course->id,
                        'course_date_id' => $courseDate->id,
                        'degree_id' => $referenceGroup->degree_id,
                        'age_min' => $referenceGroup->age_min,
                        'age_max' => $referenceGroup->age_max,
                        'teachers_min' => $referenceGroup->teachers_min,
                        'teachers_max' => $referenceGroup->teachers_max,
                        'auto' => $referenceGroup->auto,
                    ]);
                    Log::debug("El grupo has ido creado,", $group);
                }

                // Verificar los subgrupos del grupo
                foreach ($referenceGroup->courseSubgroups as $referenceSubgroup) {
                    // Contar subgrupos existentes del mismo `degree_id` en el grupo actual
                    $existingSubgroupsCount = $group->courseSubgroups
                        ->where('degree_id', $referenceSubgroup->degree_id)
                        ->where('max_participants', $referenceSubgroup->max_participants)
                        ->where('monitor_id', $referenceSubgroup->monitor_id)
                        ->count();

                    // Contar subgrupos de referencia del mismo `degree_id`
                    $referenceSubgroupsCount = $referenceGroup->courseSubgroups
                        ->where('degree_id', $referenceSubgroup->degree_id)
                        ->where('max_participants', $referenceSubgroup->max_participants)
                        ->where('monitor_id', $referenceSubgroup->monitor_id)
                        ->count();

                    // Crear los subgrupos faltantes para igualar las cantidades
                    if ($existingSubgroupsCount < $referenceSubgroupsCount) {
                        $subgroupsToCreate = $referenceSubgroupsCount - $existingSubgroupsCount;

                        for ($i = 0; $i < $subgroupsToCreate; $i++) {
                            CourseSubgroup::create([
                                'course_id' => $course->id,
                                'course_date_id' => $courseDate->id,
                                'degree_id' => $referenceSubgroup->degree_id,
                                'course_group_id' => $group->id,
                                'monitor_id' => $referenceSubgroup->monitor_id,
                                'max_participants' => $referenceSubgroup->max_participants,
                            ]);

                            Log::debug("Subgrupo creado para el grupo ID {$group->id} con degree_id={$referenceSubgroup->degree_id}");
                        }
                    }
                }

            }
        }
    }

    return response()->json(['message' => 'Todos los cursos colectivos han sido normalizados correctamente.'], 200);
});


Route::post('payrexxNotification', [\App\Http\Controllers\PayrexxController::class, 'processNotification'])
    ->name('api.payrexx.notification');

Route::get('payrexx/finish', function (Request $request) {
    return response()->make('Payrexx close ' . $request->status, 200);
})->name('api.payrexx.finish');

// Public Gift Voucher Routes (sin autenticación)
Route::prefix('public')->group(function () {
    Route::post('gift-vouchers/purchase', [\App\Http\Controllers\API\PublicGiftVoucherController::class, 'purchase'])
        ->name('api.public.gift-vouchers.purchase');
    Route::get('gift-vouchers/verify/{code}', [\App\Http\Controllers\API\PublicGiftVoucherController::class, 'verify'])
        ->name('api.public.gift-vouchers.verify');
    Route::get('gift-vouchers/templates', [\App\Http\Controllers\API\PublicGiftVoucherController::class, 'templates'])
        ->name('api.public.gift-vouchers.templates');
});

// Webhook para confirmación de pago de Gift Vouchers (sin auth, validación interna)
Route::post('webhooks/payrexx/gift-voucher', [\App\Http\Controllers\API\PublicGiftVoucherController::class, 'payrexxWebhook'])
    ->name('api.webhooks.payrexx.gift-voucher');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('vouchers/{voucher}/apply', [VoucherAPIController::class, 'apply'])
        ->name('api.vouchers.apply');
});

Route::post('migration/newSchool', [\App\Http\Controllers\API\SchoolAPIController::class, 'storeFull'])
    ->name('api.school.newschool.data');

Route::get('migration/data', [\App\Http\Controllers\MigrationController::class, 'migrateInitalData'])
    ->name('api.migration.data');

Route::get('migration/clients', [\App\Http\Controllers\MigrationController::class, 'migrateClients'])
    ->name('api.migration.clients');

Route::get('migration/monitors', [\App\Http\Controllers\MigrationController::class, 'migrateMonitors'])
    ->name('api.migration.monitors');

Route::get('migration/schools', [\App\Http\Controllers\MigrationController::class, 'migrateUsersSchools'])
    ->name('api.migration.schools');

Route::get('migration/courses', [\App\Http\Controllers\MigrationController::class, 'migrateCourses'])
    ->name('api.migration.courses');

Route::get('migration/bookings', [\App\Http\Controllers\MigrationController::class, 'migrateBookings'])
    ->name('api.migration.bookings');

Route::get('migration/all', [\App\Http\Controllers\MigrationController::class, 'migrateAll'])
    ->name('api.migration.all');

/*Route::post('/getCourseData', function (Request $request) {
    $bookingusersReserved = BookingUser::whereBetween('date', [$request->startDate, $request->endDate])
        ->whereHas('booking', function ($query) {
            $query->where('status', '!=', 2);
        })
        ->where('status', 1)
        ->where('school_id', $request->school_id)
        ->get();

    $totalPricesByType = [
        'total_price_type_1' => 0,
        'total_price_type_2' => 0,
        'total_price_type_3' => 0,
    ];

    foreach ($bookingusersReserved as $bookingUser) {
        $price = calculateTotalPrice($bookingUser);

        if ($bookingUser->course->course_type == 1) {
            $totalPricesByType['total_price_type_1'] += $price;
        } elseif ($bookingUser->course->course_type == 2) {
            $totalPricesByType['total_price_type_2'] += $price;
        } else {
            $totalPricesByType['total_price_type_3'] += $price;
        }
    }


    return $totalPricesByType;
});*/




Route::post('/getCourseData', function (Request $request) {
    $bookingusersReserved = BookingUser::whereBetween('date', [$request->startDate, $request->endDate])
        ->whereHas('booking', function ($query) {
            $query->where('status', '!=', 2); // Excluir reservas canceladas
        })
        ->where('status', 1) // Solo reservas confirmadas
        ->where('school_id', $request->school_id)
        ->get();

    $result = [];

    // Agrupar por booking_id
    $bookingGroupedByBooking = $bookingusersReserved->groupBy('booking_id');

    foreach ($bookingGroupedByBooking as $bookingId => $bookingUsers) {

        $booking = $bookingUsers->first()->booking; // Tomamos la primera reserva para obtener la información del booking

        // Crear un array de pagos desglosados por curso
        $paymentsByCourse = [];
        $extrasByCourse = [];

        // Ahora procesamos cada BookingUser
        foreach ($bookingUsers as $bookingUser) {
            $course = $bookingUser->course; // Cada BookingUser tiene un curso relacionado

            if (!$course) continue;
            $extrasByCourse[$course->id] = 0;
            // Inicializamos los pagos y extras por curso si no existen
            if (!isset($paymentsByCourse[$course->id])) {
                $paymentsByCourse[$course->id] = [
                    'cash' => 0,
                    'other' => 0,
                    'boukii' => 0,
                    'online' => 0,
                    'voucher' => 0,
                    'web' => 0,
                    'admin' => 0,
                ];

            }

            // Sumar los pagos por método
            foreach ($booking->payments as $payment) {
                $paymentType = $booking->payment_method_id;
                $amount = $payment->status === 'paid' ? $payment->amount : ($payment->status === 'refund' ? -$payment->amount : 0);

                // Relacionamos el pago con el curso correspondiente
                if ($bookingUser->course_id == $course->id) {
                    if ($payment->notes == 'other') {
                        $paymentsByCourse[$course->id]['other'] += $amount;
                    } else if ($payment->notes == 'cash') {
                        $paymentsByCourse[$course->id]['cash'] += $amount;
                    } else if ($payment->notes == 'voucher') {
                        $paymentsByCourse[$course->id]['voucher'] += $amount;
                    } else if ($payment->payrexx_reference) {
                        switch ($paymentType) {
                            case Booking::ID_BOUKIIPAY:
                                $paymentsByCourse[$course->id]['boukii'] += $amount;
                                break;
                            case Booking::ID_ONLINE:
                                $paymentsByCourse[$course->id]['online'] += $amount;
                                break;
                        }
                    }
                }
            }

            // Sumar los extras por curso
            foreach ($bookingUser->bookingUserExtras as $extra) {
                if ($bookingUser->course_id == $course->id) {
                    $extrasByCourse[$course->id] += $extra->price;
                }
            }
        }

        // Ahora agrupamos los resultados por curso
        foreach ($paymentsByCourse as $courseId => $payments) {
            // Si ya existe un curso con este course_id, sumamos los resultados en lugar de agregarlo de nuevo
            if (!isset($result[$courseId])) {
                $course = Course::find($courseId);
                $result[$courseId] = [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'icon' => $course->icon,
                    'dates' => $bookingUsers->where('course_id', $course->id)->pluck('date')->unique()->values()->toArray(),
                    'payments' => $payments,
                    'extras_total' => $extrasByCourse[$courseId],
                    'total_cost' => array_sum($payments) + $extrasByCourse[$courseId],
                ];
            } else {
                // Si ya existe el curso, sumamos los pagos y extras
                $result[$courseId]['payments'] = array_map(function($a, $b) {
                    return $a + $b;
                }, $result[$courseId]['payments'], $payments);

                $result[$courseId]['extras_total'] += $extrasByCourse[$courseId];
                $result[$courseId]['total_cost'] = array_sum($result[$courseId]['payments']) + $result[$courseId]['extras_total'];
            }
        }
    }

    // Convertir el array asociativo a un array indexado
    return array_values($result);
});


Route::post('/calculateTotalPrices', function (Request $request) {
    $bookingusersReserved = BookingUser::whereBetween('date', [$request->startDate, $request->endDate])
        ->whereHas('booking', function ($query) {
            $query->where('status', '!=', 2); // Excluir reservas canceladas
        })
        ->where('status', 1) // Solo reservas confirmadas
        ->where('school_id', $request->school_id)
        ->get();

    // Método 1: Usando los métodos previos para calcular los precios de los bookingusers
    $method1Total = 0;
    foreach ($bookingusersReserved->groupBy('course_id') as $courseId => $bookingUsers) {
        $course = Course::find($courseId);
        if (!$course) continue;

        if ($course->course_type === 2) {
            $bookingUsersGroupedByGroup = $bookingUsers->groupBy('group_id');

            foreach ($bookingUsersGroupedByGroup as $groupId => $groupUsers) {
                // Sumar el total para cada grupo
                foreach ($groupUsers as $bookingUser) {
                    // Suponiendo que el cálculo de precio total por bookingUser es un método que ya tienes
                    $method1Total += calculateTotalPrice($bookingUser);
                }
            }
        } else {
            // Filtrar los bookingusers del primer día de la reserva
            $firstDate = $bookingUsers->first()->date; // Asumimos que la primera fecha es la correcta
            $firstDayBookingUsers = $bookingUsers->where('date', $firstDate); // Filtramos por el primer día

            foreach ($firstDayBookingUsers as $bookingUser) {
                // Suponiendo que el cálculo de precio total por bookingUser es un método que ya tienes
                $method1Total += calculateTotalPrice($bookingUser);
            }
        }

    }

    // Método 2: Sumando price_total de todos los bookingusers para cada booking único
    $method2Total = 0;
    $bookingGroupedByBooking = $bookingusersReserved->groupBy('booking_id');
    foreach ($bookingGroupedByBooking as $bookingId => $bookingUsers) {
        $booking = $bookingUsers->first()->booking; // Tomamos la primera reserva para obtener la información del booking
        $method2Total += $booking->price_total; // Sumamos el precio total de cada booking
    }

    // Método 3: Sumando los pagos de todos los bookingusers
    $method3Total = 0;
    foreach ($bookingGroupedByBooking as $bookingId => $bookingUsers) {
        $booking = $bookingUsers->first()->booking; // Obtener la primera reserva de la agrupación

        // Sumar los pagos por método, recorriendo los pagos de la reserva
        $payments = [
            'cash' => 0,
            'other' => 0,
            'boukii' => 0,
            'online' => 0,
            'voucher' => 0,
            'web' => 0,
            'admin' => 0,
        ];

        foreach ($booking->payments as $payment) {
            $paymentAmount = $payment->status === 'paid' ? $payment->amount : ($payment->status === 'refund' ? -$payment->amount : 0);

            if ($payment->notes == 'other') {
                $payments['other'] += $paymentAmount;
            } else if ($payment->notes == 'cash') {
                $payments['cash'] += $paymentAmount;
            } else if ($payment->notes == 'voucher') {
                $payments['voucher'] += $paymentAmount;
            } else if ($payment->payrexx_reference) {
                switch ($booking->payment_method_id) {
                    case Booking::ID_BOUKIIPAY:
                        $payments['boukii'] += $paymentAmount;

                        break;
                    case Booking::ID_ONLINE:
                        $payments['online'] += $paymentAmount;
                        break;
                }
            }

        }

        foreach ($booking->vouchersLogs as $voucher) {
            $payments['voucher'] += abs($voucher->amount);
        }

        //dd($payments);

        // Sumar el total de los pagos de la reserva
        $method3Total += array_sum($payments);
    }

    // Comparar los resultados
    return [
        'method1_total' => $method1Total,
        'method2_total' => $method2Total,
        'method3_total' => $method3Total,
        'discrepancies' => [
            'method1_vs_method2' => abs($method1Total - $method2Total),
            'method1_vs_method3' => abs($method1Total - $method3Total),
            'method2_vs_method3' => abs($method2Total - $method3Total),
        ],
    ];
});

Route::get('/calculateTotalPrices1', function (Request $request) {
    $bookingusersReserved = BookingUser::whereBetween('date', [$request->startDate, $request->endDate])
        ->whereHas('booking', function ($query) {
            $query->where('status', '!=', 2); // Excluir reservas canceladas
        })
        ->where('status', 1) // Solo reservas confirmadas
        ->where('school_id', $request->school_id)
        ->get();

    // Método 1: Calcular el precio por curso
    $method1Total = [];
    $extrasByCourse = [];
    $result = [];
    // Agrupar los bookingUsers por course_id
    foreach ($bookingusersReserved->groupBy('course_id') as $courseId => $bookingUsers) {
        $course = Course::find($courseId);
        if (!$course) continue;
        $extrasByCourse[$course->id] = 0;
        // Inicializamos el total del curso
        $courseTotal = 0;

        if ($course->course_type === 2) {
            // Si es un curso privado (tipo 2), calcular los precios de todos los bookingUsers
            foreach ($bookingUsers as $bookingUser) {
                $courseTotal += calculateTotalPrice($bookingUser);
            }
        } else {
            // Si es un curso colectivo (tipo 1)
            $firstDate = $bookingUsers->first()->date; // Tomamos la primera fecha
            $firstDayBookingUsers = $bookingUsers->where('date', $firstDate);

            foreach ($firstDayBookingUsers as $bookingUser) {
                $courseTotal += calculateTotalPrice($bookingUser);
            }
        }

        // Sumar los pagos por método, recorriendo los pagos de la reserva
        $paymentsByCourse[$course->id] = [
            'cash' => 0,
            'other' => 0,
            'boukii' => 0,
            'online' => 0,
            'voucher' => 0,
            'web' => 0,
            'admin' => 0,
        ];


        foreach ($bookingUsers->groupBy('booking_id') as $bookingId => $usersInBooking) {
            $booking = $usersInBooking->first()->booking;

            // Sumar los pagos por método dentro de cada booking
            foreach ($booking->payments as $payment) {
                $paymentType = $payment->booking->payment_method_id;
                $amount = $payment->status === 'paid' ? $payment->amount : ($payment->status === 'refund' ? -$payment->amount : 0);

                if ($payment->notes === 'other') {
                    $paymentsByCourse[$course->id]['other'] += $amount;
                } elseif ($payment->notes === 'cash') {
                    $paymentsByCourse[$course->id]['cash'] += $amount;
                } elseif ($payment->notes === 'voucher') {
                    $paymentsByCourse[$course->id]['voucher'] += $amount;
                } elseif ($payment->payrexx_reference) {
                    switch ($paymentType) {
                        case Booking::ID_BOUKIIPAY:
                            $paymentsByCourse[$course->id]['boukii'] += $amount;
                            break;
                        case Booking::ID_ONLINE:
                            $paymentsByCourse[$course->id]['online'] += $amount;
                            break;
                    }
                }
            }
        }

        // Guardar el resultado del curso
        $result[$course->id] = [
            'course_id' => $course->id,
            'course_name' => $course->name,
            'dates' => $bookingUsers
                ->where('course_id', $course->id)
                ->map(function ($bookingUser) {
                    return [
                        'date' => $bookingUser->date->format('Y-m-d'),
                        'hour_start' => $bookingUser->hour_start,
                        'hour_end' => $bookingUser->hour_end,
                    ];
                })
                ->unique(function ($item) {
                    return $item['date'] . '_' . $item['hour_start'] . '_' . $item['hour_end'];
                })
                ->values()
                ->toArray(),
            'extras_total' => $extrasByCourse[$course->id] ?? 0,
            'total_cost' => $courseTotal,
            'payments' => $paymentsByCourse[$course->id], // Añadimos los pagos al resultado
        ];

    }
    return (new CoursesExport($result))->download('courses_export.xlsx');
});

if (!function_exists('calculateTotalPrice')) {
function calculateTotalPrice($bookingUser)
{
    $courseType = $bookingUser->course->course_type; // 1 = Colectivo, 2 = Privado
    $isFlexible = $bookingUser->course->is_flexible; // Si es flexible o no
    $totalPrice = 0;

    if ($courseType == 1) { // Colectivo
        if ($isFlexible) {
            // Si es colectivo flexible
            $totalPrice = calculateFlexibleCollectivePrice($bookingUser);
        } else {
            // Si es colectivo fijo
            $totalPrice = calculateFixedCollectivePrice($bookingUser);
        }
    } elseif ($courseType == 2) { // Privado
        if ($isFlexible) {
            // Si es privado flexible, calcular precio por `price_range`
            $totalPrice = calculatePrivatePrice($bookingUser, $bookingUser->course->price_range);
        } else {
            // Si es privado no flexible, usar un precio fijo
            $totalPrice = $bookingUser->course->price; // Asumimos que el curso tiene un campo `fixed_price`
        }
    } else {
        throw new Exception("Invalid course type: $courseType");
    }

    // Calcular los extras y sumarlos
    $extrasPrice = calculateExtrasPrice($bookingUser);
    $totalPrice += $extrasPrice;

    return $totalPrice;
}
}

if (!function_exists('calculateFixedCollectivePrice')) {
function calculateFixedCollectivePrice($bookingUser)
{
    $course = $bookingUser->course;

    // Agrupar BookingUsers por participante (course_id, participant_id)
    $participants = BookingUser::select(
        'client_id',
        DB::raw('COUNT(*) as total_bookings'), // Contar cuántos BookingUsers tiene cada participante
        DB::raw('SUM(price) as total_price') // Sumar el precio total por participante
    )
        ->where('course_id', $course->id)
        ->where('client_id', $bookingUser->client_id)
        ->groupBy('client_id')
        ->get();


    // Tomar el precio del curso para cada participante
    return count($participants) ? $course->price : 0;
}
}

if (!function_exists('calculateFlexibleCollectivePrice')) {
function calculateFlexibleCollectivePrice($bookingUser)
{
    $course = $bookingUser->course;
    if (!$course) {
        return 0;
    }

    $clientBookingUsers = BookingUser::where('course_id', $course->id)
        ->where('client_id', $bookingUser->client_id)
        ->where('status', '!=', 2)
        ->get();

    return IntervalDiscountHelper::calculateFlexibleCollectivePrice($course, $clientBookingUsers);
}
}

if (!function_exists('calculatePrivatePrice')) {
function calculatePrivatePrice($bookingUser, $priceRange)
{
    $course = $bookingUser->course;
    $groupId = $bookingUser->group_id;

    // Agrupar BookingUsers por fecha, hora y monitor
    $groupBookings = BookingUser::where('course_id', $course->id)
        ->where('date', $bookingUser->date)
        ->where('hour_start', $bookingUser->hour_start)
        ->where('hour_end', $bookingUser->hour_end)
        ->where('monitor_id', $bookingUser->monitor_id)
        ->where('group_id', $groupId)
        ->where('status', 1)
        ->count();

    $duration = Carbon::parse($bookingUser->hour_end)->diffInMinutes(Carbon::parse($bookingUser->hour_start));
    $interval = getIntervalFromDuration($duration); // Función para mapear duración al intervalo (e.g., "1h 30m").

    // Buscar el precio en el price range
    $priceForInterval = collect($priceRange)->firstWhere('intervalo', $interval);
    $pricePerParticipant = $priceForInterval[$groupBookings] ?? null;

    if (!$pricePerParticipant) {
        throw new Exception("Precio no definido curso $bookingUser->id para $groupBookings participantes en intervalo $interval");
    }

    // Calcular extras
    $extraPrices = $bookingUser->bookingUserExtras->sum(function ($extra) {
        return $extra->price;
    });

    // Calcular precio total
    $totalPrice = $pricePerParticipant + $extraPrices;

    return $totalPrice;
}
}
if (!function_exists('getIntervalFromDuration')) {
function getIntervalFromDuration($duration)
{
    $mapping = [
        15 => "15m",
        30 => "30m",
        45 => "45m",
        60 => "1h",
        75 => "1h 15m",
        90 => "1h 30m",
        120 => "2h",
        180 => "3h",
        240 => "4h",
    ];

    return $mapping[$duration] ?? null;
}
}

if (!function_exists('calculateExtrasPrice')) {
function calculateExtrasPrice($bookingUser)
{
    $extras = $bookingUser->bookingUserExtras; // Relación con BookingUserExtras

    $totalExtrasPrice = 0;
    foreach ($extras as $extra) {
        //Log::debug('extra price:'. $extra->courseExtra->price);
        $extraPrice = $extra->courseExtra->price ?? 0;
        $totalExtrasPrice += $extraPrice;
    }

    return $totalExtrasPrice;
}
}


/*Route::get('/fix-nwds', function () {
    // Obtener todos los MonitorNwds
    $monitorNwds = MonitorNwd::all();

    foreach ($monitorNwds as $monitorNwd) {
        // Verificar si start_date es diferente de end_date
        if ($monitorNwd->start_date != $monitorNwd->end_date) {
            $startDate = \Carbon\Carbon::parse($monitorNwd->start_date);
            $endDate = \Carbon\Carbon::parse($monitorNwd->end_date);

            // Eliminar el registro antiguo
            $monitorNwd->delete();

            // Crear nuevos registros para cada día dentro del rango
            while ($startDate->lte($endDate)) {
                $newMonitorNwd = new MonitorNwd($monitorNwd->toArray());
                $newMonitorNwd->start_date = $startDate->toDateString();
                $newMonitorNwd->end_date = $startDate->toDateString();
                $newMonitorNwd->save();
                $startDate->addDay(); // Avanzar al siguiente día
            }
        }
    }
});




Route::get('/process-images', function () {
    $models = [
        Course::all(), Station::all(), Client::all(),
        Monitor::all(), StationService::all(), User::all()
    ];

    $modelFile = [
        \App\Models\EvaluationFile::all()
    ];

    foreach ($models as $modelCollection) {
        foreach ($modelCollection as $model) {
            $base64Image = $model->image;
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                $imageData = base64_decode($imageData);
                $imageName = 'image_' . time() . '.' . $type[1];
                Storage::disk('public')->put($imageName, $imageData);
                $model->image = Storage::disk('public')->url($imageName);
                $model->save();
            }
        }
    }

    foreach ($modelFile as $modelCollection) {
        foreach ($modelCollection as $model) {
            $base64File = $model->file; // Asumiendo que el campo se llama 'file'

            // Extraer el tipo MIME y decodificar el archivo
            if (preg_match('/^data:(\w+\/[\w\-\+\.]+);base64,/', $base64File, $matches)) {
                $type = $matches[1]; // Tipo MIME del archivo
                $fileData = substr($base64File, strpos($base64File, ',') + 1);
                $fileData = base64_decode($fileData);

                if ($fileData === false) {
                    throw new \Exception('base64_decode failed');
                }

                // Determinar la extensión del archivo a partir del tipo MIME
                $extension = ''; // Extensión predeterminada
                $mimeMap = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/gif' => 'gif',
                    'image/bmp' => 'bmp',
                    'image/tiff' => 'tiff',
                    'image/svg+xml' => 'svg',
                    'application/pdf' => 'pdf',
                    'video/mp4' => 'mp4',
                    'image/webp' => 'webp',
                    // Añadir más tipos MIME según sea necesario
                ];

                if (isset($mimeMap[$type])) {
                    $extension = $mimeMap[$type];
                } else {
                    // Manejar archivos con tipos MIME desconocidos o no soportados
                    continue; // O manejar de otra manera
                }

                $fileName = 'files/' . time() . '.' . $extension;
                Storage::disk('public')->put($fileName, $fileData);
                $model->file = Storage::disk('public')->url($fileName);
                $model->save();
            }
        }
    }

    return 'Proceso completado';
});

*/

Route::post('/fix-booking-users', function (Illuminate\Http\Request $request) {
    $data = $request->all();

    $results = [];
    foreach ($data as $bookingData) {
        // Safety check: ensure course_subgroup_id exists
        if (!isset($bookingData['course_subgroup_id'])) {
            $results[] = [
                'status' => 'error',
                'message' => 'Missing course_subgroup_id in booking data',
                'data' => $bookingData,
            ];
            continue;
        }

        // Obtener el `course_group_id` a través del `course_subgroup_id`
        $courseGroupId = \DB::table('course_subgroups')
            ->where('id', $bookingData['course_subgroup_id'])
            ->value('course_group_id');

        if (!$courseGroupId) {
            $results[] = [
                'status' => 'error',
                'message' => 'Course group not found for course_subgroup_id: ' . $bookingData['course_subgroup_id'],
                'data' => $bookingData,
            ];
            continue;
        }

        // Añadir el `course_group_id` al booking
        $bookingData['course_group_id'] = $courseGroupId;

        // Seguridad: nunca aceptar 'attended' desde este endpoint utilitario
        if (array_key_exists('attended', $bookingData)) {
            unset($bookingData['attended']);
        }

        // Verificar si ya existe
        $exists = BookingUser::where([
            'school_id' => $bookingData['school_id'],
            'booking_id' => $bookingData['booking_id'],
            'client_id' => $bookingData['client_id'],
            'course_id' => $bookingData['course_id'],
            'course_subgroup_id' => $bookingData['course_subgroup_id'],
            'course_group_id' => $bookingData['course_group_id'],
            'course_date_id' => $bookingData['course_date_id'],
            'degree_id' => $bookingData['degree_id'],
            'hour_start' => $bookingData['hour_start'],
            'hour_end' => $bookingData['hour_end'],
            'price' => $bookingData['price'],
            'currency' => $bookingData['currency'],
            'date' => $bookingData['date'],
        ])->exists();

        if ($exists) {
            $results[] = [
                'status' => 'skipped',
                'message' => 'Booking user already exists',
                'data' => $bookingData,
            ];
            continue;
        }

        // Crear el nuevo registro
        BookingUser::create($bookingData);
        $results[] = [
            'status' => 'success',
            'message' => 'Booking user created',
            'data' => $bookingData,
        ];
    }

    return response()->json($results);
});

/* API PAYREXX */
Route::prefix('')
    ->group(base_path('routes/api/payrexx.php'));
/* API PAYREXX */

/* API USER TYPE ADMIN */
Route::prefix('admin')
    ->group(base_path('routes/api/admin.php'));
/* API USER TYPE ADMIN */


/* API USER TYPE SUPERADMIN */
Route::prefix('superadmin')
    ->group(base_path('routes/api/superadmin.php'));
/* API USER TYPE SUPERADMIN */


/* API APP SPORTS */
Route::prefix('sports')
    ->group(base_path('routes/api/sports.php'));

/* API APP SPORTS */


/* API APP TEACH */
Route::prefix('teach')
    ->group(base_path('routes/api/teach.php'));
/* API APP TEACH */


/* API PUBLIC */
Route::prefix('')
    ->group(base_path('routes/api/public.php'));
/* API PUBLIC */

/* API IFRAME */
Route::prefix('slug')
    ->group(base_path('routes/api/bookingPage.php'));
/* API IFRAME */

/* WEATHER */
Route::prefix('weather')
    ->group(base_path('routes/api/weather.php'));
/* WEATHER */

/* COURSE INTERVALS API */
Route::prefix('course-intervals')->group(function () {
    Route::get('/', [App\Http\Controllers\API\CourseIntervalAPIController::class, 'index']);
    Route::post('/', [App\Http\Controllers\API\CourseIntervalAPIController::class, 'store']);
    Route::get('/{id}', [App\Http\Controllers\API\CourseIntervalAPIController::class, 'show']);
    Route::put('/{id}', [App\Http\Controllers\API\CourseIntervalAPIController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\API\CourseIntervalAPIController::class, 'destroy']);
    Route::post('/reorder', [App\Http\Controllers\API\CourseIntervalAPIController::class, 'reorder']);
    Route::post('/{id}/generate-dates', [App\Http\Controllers\API\CourseIntervalAPIController::class, 'generateDates']);
    Route::get('/{id}/discounts', [App\Http\Controllers\API\CourseIntervalDiscountAPIController::class, 'index']);
    Route::put('/{id}/discounts', [App\Http\Controllers\API\CourseIntervalDiscountAPIController::class, 'upsert']);
    Route::post('/{id}/calculate-discount', [AppHttpControllersAPIBookingDiscountAPIController::class, 'calculateDiscount']);
});
/* COURSE INTERVALS API */

/* COURSE INTERVAL GROUPS API */
Route::prefix('course-interval-groups')->group(function () {
    Route::get('/interval/{intervalId}', [App\Http\Controllers\API\CourseIntervalGroupAPIController::class, 'indexForInterval']);
    Route::get('/course/{courseId}', [App\Http\Controllers\API\CourseIntervalGroupAPIController::class, 'indexForCourse']);
    Route::post('/interval', [App\Http\Controllers\API\CourseIntervalGroupAPIController::class, 'storeForInterval']);
    Route::post('/apply-global', [App\Http\Controllers\API\CourseIntervalGroupAPIController::class, 'applyGlobalGroups']);
    Route::delete('/interval/{intervalId}', [App\Http\Controllers\API\CourseIntervalGroupAPIController::class, 'deleteForInterval']);
    Route::get('/has-interval-groups/{courseId}', [App\Http\Controllers\API\CourseIntervalGroupAPIController::class, 'hasIntervalGroups']);
});
/* COURSE INTERVAL GROUPS API */

/* EXTERNAL */
Route::prefix('external')
    ->group(base_path('routes/api/external.php'));
/* EXTERNAL */

/* SYSTEM */
Route::prefix('system')
    ->group(base_path('routes/api/system.php'));
/* SYSTEM */

/* BOOKING DISCOUNTS API */
Route::prefix('bookings')->group(function () {
    Route::post('/calculate-discount', [App\Http\Controllers\API\BookingDiscountAPIController::class, 'calculateDiscount']);
    Route::post('/calculate-multi-interval-discount', [App\Http\Controllers\API\BookingDiscountAPIController::class, 'calculateMultiIntervalDiscount']);
});
Route::get('/courses/{courseId}/intervals/{intervalId}/available-discounts', [App\Http\Controllers\API\BookingDiscountAPIController::class, 'getAvailableDiscounts']);
/* BOOKING DISCOUNTS API */
