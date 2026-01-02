<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Carbon\Carbon;

trait FinanceCacheKeyTrait
{
    /**
     * Generar cache key consistente para dashboard financiero
     */
    protected function generateFinanceCacheKey(
        int $schoolId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $seasonId = null,
        string $optimizationLevel = 'fast',
        string $extraKey = ''
    ): string {

        // Si no hay fechas específicas, obtener fechas de temporada actual
        if (!$startDate || !$endDate) {
            $seasonDates = $this->getCurrentSeasonDates($schoolId, $seasonId);
            $startDate = $seasonDates['start_date'];
            $endDate = $seasonDates['end_date'];
        }

        // Normalizar fechas para consistencia
        $startDate = Carbon::parse($startDate)->format('Y-m-d');
        $endDate = Carbon::parse($endDate)->format('Y-m-d');

        return md5("finance_dashboard_{$schoolId}_{$endDate}_{$startDate}_{$optimizationLevel}_{$extraKey}");
    }

    /**
     * Obtener fechas de temporada actual para una escuela
     */
    private function getCurrentSeasonDates(int $schoolId, ?int $seasonId = null): array
    {
        if ($seasonId) {
            $season = \App\Models\Season::find($seasonId);
            if ($season) {
                return [
                    'start_date' => $season->start_date,
                    'end_date' => $season->end_date,
                    'season_id' => $season->id
                ];
            }
        }

        // Temporada actual por defecto
        $today = Carbon::today();
        $season = \App\Models\Season::where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if ($season) {
            return [
                'start_date' => $season->start_date,
                'end_date' => $season->end_date,
                'season_id' => $season->id
            ];
        }

        // Fallback: últimos 6 meses
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'season_id' => null
        ];
    }

    /**
     * Generar cache key desde Request
     */
    protected function generateCacheKeyFromRequest(Request $request): string
    {
        $extraKey = implode('|', [
            $request->get('date_filter', ''),
            $request->boolean('include_test_detection', true) ? 'test:1' : 'test:0',
            $request->boolean('include_payrexx_analysis', false) ? 'payrexx:1' : 'payrexx:0',
        ]);

        return $this->generateFinanceCacheKey(
            $request->school_id,
            $request->start_date,
            $request->end_date,
            $request->season_id,
            $request->get('optimization_level', 'fast'),
            $extraKey
        );
    }
}
