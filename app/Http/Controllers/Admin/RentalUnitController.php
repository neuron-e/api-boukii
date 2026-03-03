<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class RentalUnitController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_units', [
            'variant_id' => $request->input('variant_id'),
            'warehouse_id' => $request->input('warehouse_id'),
            'status' => $request->input('status'),
        ]);
    }

    public function store(Request $request)
    {
        return $this->storeByTable($request, 'rental_units', [
            'school_id', 'variant_id', 'warehouse_id', 'serial', 'status', 'condition', 'notes',
        ]);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_units', $id, [
            'variant_id', 'warehouse_id', 'serial', 'status', 'condition', 'notes',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_units', $id);
    }
}

