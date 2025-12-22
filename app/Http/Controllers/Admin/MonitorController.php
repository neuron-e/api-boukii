<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Resources\API\BookingResource;
use App\Http\Resources\API\BookingUserResource;
use App\Http\Resources\Teach\HomeAgendaResource;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\CourseSubgroup;
use App\Models\Degree;
use App\Models\Monitor;
use App\Models\MonitorNwd;
use App\Models\MonitorObservation;
use App\Models\MonitorSportsDegree;
use App\Models\MonitorsSchool;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Response;
use Validator;

;

/**
 * Class HomeController
 * @package App\Http\Controllers\Teach
 */
class MonitorController extends AppBaseController
{

    public function __construct()
    {

    }

    /**
     * @OA\Post(
     *      path="/admin/monitor/available",
     *      summary="getMonitorsAvailable",
     *      tags={"Admin"},
     *      description="Get monitors available",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/Monitor")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function getMonitorsAvailable(Request $request): JsonResponse
    {
        $school = $this->getSchool($request);

        $isAnyAdultClient = false;
        $clientLanguages = [];
        $bookingUserIds = collect($request->input('bookingUserIds', []))
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
        $subgroupIds = collect($request->input('subgroupIds', []))
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
        $courseId = $request->input('courseId');
        // Solo obtener todos los subgrupos si no se pasó ninguno para excluir
        if ($courseId && empty($subgroupIds)) {
            $courseSubgroupIds = CourseSubgroup::whereHas('courseGroup.course', function ($query) use ($courseId) {
                $query->where('id', $courseId);
            })->pluck('id')->map(fn($id) => (int) $id)->all();
            // Simplemente asignar, no mergear, para mantener solo las exclusiones pasadas
            $subgroupIds = $courseSubgroupIds;
        }

        if ($request->has('clientIds') && is_array($request->clientIds)) {
            foreach ($request->clientIds as $clientId) {
                $client = Client::find($clientId);
                if ($client) {
                    $clientAge = Carbon::parse($client->birth_date)->age;
                    if ($clientAge >= 18) {
                        $isAnyAdultClient = true;
                    }

                    // Agregar idiomas del cliente al array de idiomas
                    for ($i = 1; $i <= 6; $i++) {
                        $languageField = 'language' . $i . '_id';
                        if (!empty($client->$languageField)) {
                            $clientLanguages[] = $client->$languageField;
                        }
                    }
                }
            }
        }


        $clientLanguages = array_unique($clientLanguages);
        $degreeOrder = $request->minimumDegreeId ? Degree::find($request->minimumDegreeId)->degree_order : null;

        $startTime = $request->startTime ? $this->normalizeTimeFormat($request->startTime) : null;
        $endTime = $request->endTime ? $this->normalizeTimeFormat($request->endTime) : null;

        $availableMonitors = Monitor::query()
            ->withSportAndDegree($request->sportId, $school->id, $degreeOrder, $isAnyAdultClient)
            ->withLanguages($clientLanguages)
            ->availableBetween($request->date, $startTime, $endTime, $bookingUserIds, $subgroupIds)
            ->get()
            ->toArray();

        return $this->sendResponse(array_values($availableMonitors), 'Monitors returned successfully');

    }

    /**
     * @OA\Post(
     *      path="/admin/monitor/available/{id}",
     *      summary="checkIfMonitorIsAvailable",
     *      tags={"Admin"},
     *      description="Get monitor availability",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/Monitor")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function checkIfMonitorIsAvailable(Request $request, $id): JsonResponse
    {
        try {
            // Validar los parámetros
            $validatedData = $request->validate([
                'date' => 'required|date',
                'hour_start' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
                'hour_end' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            ]);

            // Asegurar que los valores tengan segundos (normalizar)
            $validatedData['hour_start'] = $this->normalizeTimeFormat($validatedData['hour_start']);
            $validatedData['hour_end'] = $this->normalizeTimeFormat($validatedData['hour_end']);

            $isBusy = Monitor::isMonitorBusy($id, $validatedData['date'],
                $validatedData['hour_start'], $validatedData['hour_end']);

            return $this->sendResponse(['available' => !$isBusy], 'Monitor availability returned successfully');

        } catch (ValidationException $e) {
            // Manejar errores de validación
             return $this->sendError($e->getMessage(), 400);
        }

    }

    /**
     * Normaliza el formato de la hora asegurando que siempre tenga segundos.
     */
    private function normalizeTimeFormat($time)
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }

}
