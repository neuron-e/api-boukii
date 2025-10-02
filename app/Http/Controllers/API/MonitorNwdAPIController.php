<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateMonitorNwdAPIRequest;
use App\Http\Requests\API\UpdateMonitorNwdAPIRequest;
use App\Http\Resources\API\MonitorNwdResource;
use App\Models\BookingUser;
use App\Models\CourseSubgroup;
use App\Models\Monitor;
use App\Models\MonitorNwd;
use App\Repositories\MonitorNwdRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Facades\NwdLog as Log;

/**
 * Class MonitorNwdController
 */

class MonitorNwdAPIController extends AppBaseController
{
    /** @var  MonitorNwdRepository */
    private $monitorNwdRepository;

    public function __construct(MonitorNwdRepository $monitorNwdRepo)
    {
        $this->monitorNwdRepository = $monitorNwdRepo;
    }

    /**
     * @OA\Get(
     *      path="/monitor-nwds",
     *      summary="getMonitorNwdList",
     *      tags={"MonitorNwd"},
     *      description="Get all MonitorNwds",
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
     *                  @OA\Items(ref="#/components/schemas/MonitorNwd")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Log de petición al índice
        Log::info('Lista de MonitorNwds solicitada', [
            'filters' => $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            'search' => $request->get('search'),
            'order' => $request->get('order', 'desc'),
            'orderColumn' => $request->get('orderColumn', 'id')
        ]);

        $monitorNwds = $this->monitorNwdRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($monitorNwds, 'Monitor Nwds retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/monitor-nwds",
     *      summary="createMonitorNwd",
     *      tags={"MonitorNwd"},
     *      description="Create MonitorNwd",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/MonitorNwd")
     *      ),
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
     *                  ref="#/components/schemas/MonitorNwd"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateMonitorNwdAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        try {
            $startDate = new \DateTime($input['start_date']);
            $endDate = new \DateTime($input['end_date']);
            $createdNwds = [];
            $skippedDates = [];

            // Iterar por cada día del rango
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');

                // Verificar si el monitor está ocupado en esta fecha específica
                $isBusy = Monitor::isMonitorBusy(
                    $input['monitor_id'],
                    $dateStr,
                    $input['start_time'] ?? null,
                    $input['end_time'] ?? null
                );

                if (!$isBusy) {
                    // Crear NWD para este día
                    $nwdData = [
                        'monitor_id' => $input['monitor_id'],
                        'school_id' => $input['school_id'] ?? null,
                        'station_id' => $input['station_id'] ?? null,
                        'start_date' => $dateStr,
                        'end_date' => $dateStr,
                        'start_time' => $input['start_time'] ?? null,
                        'end_time' => $input['end_time'] ?? null,
                        'full_day' => $input['full_day'] ?? false,
                        'title' => $input['title'] ?? null,
                    ];

                    $monitorNwd = $this->monitorNwdRepository->create($nwdData);
                    $createdNwds[] = $monitorNwd;

                    Log::info('MonitorNwd creado para fecha específica', [
                        'nwd_id' => $monitorNwd->id,
                        'monitor_id' => $input['monitor_id'],
                        'date' => $dateStr,
                    ]);
                } else {
                    // Guardar fecha omitida
                    $skippedDates[] = $dateStr;

                    Log::warning('Fecha omitida por solapamiento', [
                        'monitor_id' => $input['monitor_id'],
                        'date' => $dateStr,
                        'start_time' => $input['start_time'],
                        'end_time' => $input['end_time'],
                    ]);
                }

                $currentDate->modify('+1 day');
            }

            // Determinar el mensaje de respuesta
            $totalRequested = $startDate->diff($endDate)->days + 1;
            $totalCreated = count($createdNwds);
            $totalSkipped = count($skippedDates);

            if ($totalCreated === 0) {
                // No se creó ningún NWD
                return $this->sendError('No se pudo crear ninguna indisponibilidad. Todas las fechas tienen solapamientos.', 409);
            } elseif ($totalSkipped > 0) {
                // Se crearon algunos NWDs pero otros se omitieron
                $response = [
                    'created' => $createdNwds,
                    'skipped_dates' => $skippedDates,
                    'summary' => [
                        'total_requested' => $totalRequested,
                        'total_created' => $totalCreated,
                        'total_skipped' => $totalSkipped
                    ]
                ];

                return $this->sendResponse(
                    $response,
                    "Se crearon {$totalCreated} indisponibilidad(es). {$totalSkipped} fecha(s) omitida(s) por solapamientos.",
                    206 // 206 Partial Content
                );
            } else {
                // Se crearon todos los NWDs solicitados
                return $this->sendResponse(
                    ['created' => $createdNwds],
                    'Indisponibilidad(es) guardada(s) exitosamente'
                );
            }

        } catch (\Exception $e) {
            // Loguear el error
            Log::error('Error al guardar MonitorNwd', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input' => $input
            ]);

            return $this->sendError('Ocurrió un error al guardar el Monitor Nwd. Inténtalo nuevamente.', 500);
        }
    }


    /**
     * @OA\Get(
     *      path="/monitor-nwds/{id}",
     *      summary="getMonitorNwdItem",
     *      tags={"MonitorNwd"},
     *      description="Get MonitorNwd",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of MonitorNwd",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
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
     *                  ref="#/components/schemas/MonitorNwd"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show($id, Request $request): JsonResponse
    {
        /** @var MonitorNwd $monitorNwd */
        $monitorNwd = $this->monitorNwdRepository->find($id, with: $request->get('with', []));

        if (empty($monitorNwd)) {
            return $this->sendError('Monitor Nwd not found');
        }

        return $this->sendResponse($monitorNwd, 'Monitor Nwd retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/monitor-nwds/{id}",
     *      summary="updateMonitorNwd",
     *      tags={"MonitorNwd"},
     *      description="Update MonitorNwd",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of MonitorNwd",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/MonitorNwd")
     *      ),
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
     *                  ref="#/components/schemas/MonitorNwd"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateMonitorNwdAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var MonitorNwd $monitorNwd */
        $monitorNwd = $this->monitorNwdRepository->find($id, with: $request->get('with', []));

        if (empty($monitorNwd)) {
            return $this->sendError('Monitor Nwd not found');
        }

        // Verificar si el monitor está ocupado antes de actualizar
        if (Monitor::isMonitorBusy($monitorNwd->monitor_id, $input['start_date'], $input['start_time'], $input['end_time'], $id)) {
            // Log cuando el monitor está ocupado
            Log::warning('Monitor ocupado al intentar actualizar NWD', [
                'nwd_id' => $id,
                'monitor_id' => $monitorNwd->monitor_id,
                'start_date' => $input['start_date'],
                'start_time' => $input['start_time'],
                'end_time' => $input['end_time'],
                'action' => 'update',
                'reason' => 'monitor_busy'
            ]);
            return $this->sendError('El monitor está ocupado durante ese tiempo y no se puede actualizar el MonitorNwd', 409);
        }

        $monitorNwd = $this->monitorNwdRepository->update($input, $id);

        // Log exitoso
        Log::info('MonitorNwd actualizado exitosamente', [
            'nwd_id' => $id,
            'monitor_id' => $monitorNwd->monitor_id,
            'start_date' => $input['start_date'],
            'start_time' => $input['start_time'],
            'end_time' => $input['end_time']
        ]);

        return $this->sendResponse(new MonitorNwdResource($monitorNwd), 'MonitorNwd updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/monitor-nwds/{id}",
     *      summary="deleteMonitorNwd",
     *      tags={"MonitorNwd"},
     *      description="Delete MonitorNwd",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of MonitorNwd",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
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
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function destroy($id): JsonResponse
    {
        /** @var MonitorNwd $monitorNwd */
        $monitorNwd = $this->monitorNwdRepository->find($id);

        if (empty($monitorNwd)) {
            return $this->sendError('Monitor Nwd not found');
        }

        $monitorNwd->delete();

        return $this->sendSuccess('Monitor Nwd deleted successfully');
    }
}

