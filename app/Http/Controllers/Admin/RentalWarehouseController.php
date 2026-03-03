<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class RentalWarehouseController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_warehouses');
    }

    public function store(Request $request)
    {
        return $this->storeByTable($request, 'rental_warehouses', [
            'school_id', 'name', 'code', 'address', 'active',
        ]);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_warehouses', $id, [
            'name', 'code', 'address', 'active',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_warehouses', $id);
    }
}

