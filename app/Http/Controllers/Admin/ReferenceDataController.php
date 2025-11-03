<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\Degree;
use App\Models\Monitor;
use App\Models\Sport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Controlador liviano para catálogos usados en el panel de administración.
 *
 * Exponde endpoints optimizados para:
 *  - Niveles (degrees) filtrados por escuela y deporte
 *  - Deportes disponibles para la escuela del usuario
 *  - Monitores activos vinculados a la escuela
 */
class ReferenceDataController extends AppBaseController
{
    public function degrees(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $request->integer('school_id') ?: optional($user?->schools()->first())->id;
        $sportId = $request->integer('sport_id');

        $cacheKey = sprintf(
            'admin_degrees_%s_%s',
            $schoolId ?: 'all',
            $sportId ?: 'all'
        );

        $degrees = Cache::remember($cacheKey, 300, function () use ($schoolId, $sportId) {
            $query = Degree::query()
                ->select([
                    'id',
                    'name',
                    'sport_id',
                    'school_id',
                    'color',
                    'degree_order',
                    'level',
                    'league',
                    'annotation',
                    'active'
                ])
                ->where('active', 1)
                ->orderBy('degree_order')
                ->orderBy('name');

            if ($schoolId) {
                $query->where(function ($q) use ($schoolId) {
                    $q->whereNull('school_id')
                        ->orWhere('school_id', $schoolId);
                });
            }

            if ($sportId) {
                $query->where('sport_id', $sportId);
            }

            return $query->get();
        });

        return $this->sendResponse($degrees, 'Degrees retrieved successfully');
    }

    public function sports(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $request->integer('school_id') ?: optional($user?->schools()->first())->id;
        $search = trim((string) $request->input('search', ''));

        $cacheKey = sprintf('admin_sports_%s_%s', $schoolId ?: 'all', md5($search));

        $sports = Cache::remember($cacheKey, 300, function () use ($schoolId, $search) {
            $query = Sport::query()
                ->select([
                    'id',
                    'name',
                    'sport_type',
                    'icon_collective',
                    'icon_prive',
                    'icon_activity',
                    'icon_selected',
                    'icon_unselected'
                ])
                ->orderBy('name');

            if ($schoolId) {
                $query->whereHas('schools', function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId);
                });
            }

            if ($search !== '') {
                $query->where('name', 'like', '%' . $search . '%');
            }

            return $query->get();
        });

        return $this->sendResponse($sports, 'Sports retrieved successfully');
    }

    public function monitors(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $request->integer('school_id') ?: optional($user?->schools()->first())->id;
        $sportId = $request->integer('sport_id');
        $degreeId = $request->integer('degree_id');
        $search = trim((string) $request->input('search', ''));

        $query = Monitor::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'telephone',
                'active'
            ])
            ->where('active', 1)
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($schoolId) {
            $query->whereHas('monitorsSchools', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId)
                    ->where('active_school', 1);
            });
        }

        if ($sportId) {
            $degreeOrder = null;
            if ($degreeId) {
                $degreeOrder = Degree::where('id', $degreeId)->value('degree_order');
            }

            $query->withSportAndDegree(
                $sportId,
                $schoolId,
                $degreeOrder,
                $request->boolean('allow_adults', false)
            );
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $limit = (int) $request->input('limit', 100);
        if ($limit > 0) {
            $query->limit($limit);
        }

        $monitors = $query->get()
            ->map(function (Monitor $monitor) {
                return [
                    'id' => $monitor->id,
                    'first_name' => $monitor->first_name,
                    'last_name' => $monitor->last_name,
                    'full_name' => trim($monitor->first_name . ' ' . $monitor->last_name),
                    'email' => $monitor->email,
                    'phone' => $monitor->phone ?: $monitor->telephone,
                    'active' => (bool) $monitor->active
                ];
            })
            ->values();

        return $this->sendResponse($monitors, 'Monitors retrieved successfully');
    }
}

