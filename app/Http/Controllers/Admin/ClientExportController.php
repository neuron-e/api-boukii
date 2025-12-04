<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ClientsExport;
use App\Http\Controllers\AppBaseController;
use App\Models\Season;
use App\Traits\Utils;
use Illuminate\Http\Request;
use Validator;

class ClientExportController extends AppBaseController
{
    use Utils;

    public function exportClients(Request $request)
    {
        $school = $this->getSchool($request);

        $validator = Validator::make($request->all(), [
            'season_id' => 'nullable|integer|exists:seasons,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'lang' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $seasonId = $request->input('season_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $lang = $request->input('lang', 'fr');

        if ($seasonId) {
            $season = Season::where('id', $seasonId)
                ->where('school_id', $school->id)
                ->first();

            if (!$season) {
                return $this->sendError('Season not found for this school', [], 404);
            }

            $dateFrom = $season->start_date->format('Y-m-d');
            $dateTo = $season->end_date->format('Y-m-d');
        }

        app()->setLocale($lang);

        $suffix = $seasonId ? 'season_' . ($season->name ?? $season->id) : (($dateFrom && $dateTo) ? "{$dateFrom}_to_{$dateTo}" : 'all');
        $safeSuffix = str_replace([' ', '/'], '-', $suffix);
        $filename = "clients_{$school->id}_{$safeSuffix}.xlsx";

        return (new ClientsExport(
            $school->id,
            $seasonId,
            $dateFrom,
            $dateTo
        ))->download($filename);
    }
}
