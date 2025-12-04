<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VouchersExport;
use App\Http\Controllers\AppBaseController;
use App\Traits\Utils;
use Illuminate\Http\Request;
use Validator;

class VoucherExportController extends AppBaseController
{
    use Utils;

    public function export(Request $request)
    {
        $school = $this->getSchool($request);

        $validator = Validator::make($request->all(), [
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date|after_or_equal:created_from',
            'lang' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $createdFrom = $request->input('created_from');
        $createdTo = $request->input('created_to');
        $lang = $request->input('lang', 'fr');

        app()->setLocale($lang);

        $suffix = ($createdFrom && $createdTo) ? "{$createdFrom}_to_{$createdTo}" : 'all';
        $filename = "vouchers_{$school->id}_{$suffix}.xlsx";

        return (new VouchersExport(
            $school->id,
            $createdFrom,
            $createdTo
        ))->download($filename);
    }
}
